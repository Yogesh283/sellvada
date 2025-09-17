// resources/js/Pages/Dashboard.jsx
import React, { useEffect, useMemo, useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, usePage } from "@inertiajs/react";

/* -------------------- UI helpers (with colors) -------------------- */
const G = {
  tealBlue: "bg-gradient-to-r from-cyan-400 to-blue-600",
  emerald: "bg-gradient-to-r from-emerald-400 to-green-600",
  orangePink: "bg-gradient-to-r from-orange-400 to-pink-500",
  purpleIndigo: "bg-gradient-to-r from-purple-500 to-indigo-600",
  amber: "bg-gradient-to-r from-amber-400 to-yellow-500",
  sky: "bg-gradient-to-r from-sky-400 to-cyan-600",
  rose: "bg-gradient-to-r from-rose-500 to-red-600",
  lime: "bg-gradient-to-r from-lime-400 to-green-600",
  barTeal: "bg-gradient-to-r from-teal-500 via-cyan-500 to-sky-500",
  barOrange: "bg-gradient-to-r from-orange-500 via-pink-500 to-rose-500",
  barIndigo: "bg-gradient-to-r from-indigo-600 via-purple-600 to-fuchsia-600",
  barEmerald: "bg-gradient-to-r from-green-500 via-emerald-600 to-teal-600",
};

const formatINR = (n) => {
  const num = Number(n);
  if (Number.isNaN(num)) return n ?? "-";
  return new Intl.NumberFormat("en-IN", {
    style: "currency",
    currency: "INR",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num);
};

const formatDT = (s) => {
  if (!s) return "-";
  const d = new Date(s);
  return Number.isNaN(d.getTime()) ? "-" : d.toLocaleString();
};

const pad2 = (n) => (n < 10 ? `0${n}` : `${n}`);

/* -------------------- Small UI components -------------------- */
function StatCard({
  title,
  value = "₹0.00",
  gradient = G.emerald,
  icon = "💳",
  actionText = "View Detail",
  actionHref = "#",
}) {
  return (
    <div
      className={`${gradient} relative overflow-hidden rounded-lg shadow-md flex flex-col justify-between`}
    >
      <div className="px-4 py-4 sm:px-5 sm:py-5 text-white flex-1">
        <div className="flex items-center justify-between">
          <div className="text-xs sm:text-sm font-semibold opacity-95">{title}</div>
          <div className="text-lg sm:text-xl opacity-90">{icon}</div>
        </div>
        <div className="mt-3 text-right text-xl sm:text-2xl font-extrabold tabular-nums break-words">
          {value}
        </div>
        <div className="mt-3">
          <a
            href={actionHref}
            className="inline-flex items-center rounded-md bg-white/20 px-3 py-1 text-xs sm:text-sm font-semibold hover:bg-white/30 transition"
          >
            {actionText}
          </a>
        </div>
      </div>
    </div>
  );
}

function CopyField({ label = null, value = "" }) {
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    try {
      if (!navigator?.clipboard) {
        const tmp = document.createElement("textarea");
        tmp.value = value || "";
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand("copy");
        tmp.remove();
      } else {
        await navigator.clipboard.writeText(value || "");
      }
      setCopied(true);
      setTimeout(() => setCopied(false), 1400);
    } catch (e) {
      console.error("Copy failed", e);
    }
  };

  return (
    <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full">
      {label ? (
        <div className="min-w-[4.5rem] text-sm font-medium text-slate-700">{label}</div>
      ) : null}
      <input
        className="w-full rounded border border-slate-200 bg-white px-2 sm:px-3 py-1.5 text-sm text-slate-800 font-mono"
        value={value || ""}
        readOnly
      />
      <button
        onClick={copy}
        type="button"
        className={`rounded px-2 sm:px-3 py-1.5 text-sm font-semibold transition ${
          copied
            ? "bg-green-600 text-white hover:bg-green-700"
            : "bg-emerald-600 text-white hover:bg-emerald-700"
        }`}
      >
        {copied ? "Copied!" : "Copy"}
      </button>
    </div>
  );
}

/* -------------------- Reward Plan component -------------------- */
function secondsBetween(a, b) {
  return Math.max(0, Math.floor((new Date(b).getTime() - new Date(a).getTime()) / 1000));
}

function humanCountdown(seconds) {
  const d = Math.floor(seconds / (3600 * 24));
  const h = Math.floor((seconds % (3600 * 24)) / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;
  if (d > 0) return `${d}d ${pad2(h)}:${pad2(m)}:${pad2(s)}`;
  return `${pad2(h)}:${pad2(m)}:${pad2(s)}`;
}

function RewardPlan({ plans = [], businessSummary = {}, fristsell = null }) {
  const { matched, placement_combined } = usePage().props;

  const [tick, setTick] = useState(0);
  useEffect(() => {
    const id = setInterval(() => setTick((t) => t + 1), 1000);
    return () => clearInterval(id);
  }, []);

  const startRaw = fristsell?.created_at ?? null;
  const startDate = startRaw ? new Date(startRaw) : null;

  const totalBusiness =
    Number(businessSummary.left || 0) + Number(businessSummary.right || 0);
  const maxThreshold = plans.reduce(
    (mx, p) => Math.max(mx, p.volumeNumber ?? 0),
    0
  );

  /* ---------------- Progress calculation ---------------- */
  const progressPercent =
    maxThreshold > 0 ? Math.min(100, Math.round((matched / maxThreshold) * 100)) : 0;

  /* ---------------- Plan Status ---------------- */
  const planStatuses = plans.map((p) => {
    if (!startDate) {
      return { ...p, status: "no_sell", remainingSecs: 0, endDate: null };
    }
    const endDate = new Date(
      startDate.getTime() + (p.daysNumber || 0) * 24 * 3600 * 1000
    );
    const secs = secondsBetween(new Date().toISOString(), endDate);
    if (secs > 0) return { ...p, status: "active", remainingSecs: secs, endDate };
    return { ...p, status: "expired", remainingSecs: 0, endDate };
  });

  return (
    <div className="rounded-lg bg-white shadow-sm overflow-hidden border border-slate-200">
      <div
        className={`${G.barEmerald} px-4 py-3 text-white font-semibold text-center`}
      >
        Cell Veda — Business Growth Reward
      </div>
      <div className="p-4">

        {/* ✅ Progress bar section */}
        <div className="mb-6">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm font-medium text-slate-700">
              Progress ({placement_combined?.matched ?? 0} / {formatINR(maxThreshold)})
            </span>
            <span className="text-sm font-medium text-slate-600">
              {progressPercent}%
            </span>
          </div>
          <div className="w-full bg-slate-100 h-4 rounded-full overflow-hidden">
            <div
              className="h-4 rounded-full transition-all duration-700 ease-in-out"
              style={{
                width: `${progressPercent}%`,
                background:
                  progressPercent === 100
                    ? "linear-gradient(90deg,#2563eb,#3b82f6)" // blue full
                    : "linear-gradient(90deg,#059669,#10b981)", // green filling
              }}
            />
          </div>
        </div>

        {/* बाकी table same रहेगा */}
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm text-center border border-slate-200">
            <thead className="bg-slate-50">
              <tr className="border-b text-slate-700">
                <th className="py-2 px-2">Timeline</th>
                <th className="py-2 px-2">Business Volume</th>
                <th className="py-2 px-2">Reward</th>
                <th className="py-2 px-2">Remaining Time</th>
              </tr>
            </thead>
            <tbody>
              {planStatuses.map((ps, idx) => (
                <tr key={idx} className="border-b last:border-0">
                  <td className="py-2 px-2 font-medium">{ps.days} Days</td>
                  <td className="py-2 px-2">{ps.volume}</td>
                  <td className="py-2 px-2 font-bold text-emerald-600">
                    {formatINR(ps.reward)}
                  </td>
                  <td className="py-2 px-2">
                    {ps.status === "active"
                      ? humanCountdown(ps.remainingSecs)
                      : ps.status === "expired"
                      ? "Expired"
                      : "00:00:00"}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}


/* -------------------- Page -------------------- */
export default function Dashboard() {
  const props = usePage().props;

  const availableBalance = formatINR(props?.wallet_amount ?? 0);
  const payoutBalance = formatINR(props?.payout_wallet ?? 0);
  const TotalTeam = props?.total_team ?? 0;
  const CurrentPlan = props?.current_plan ?? "-";
  const { left, right, matched, placement_combined } = usePage().props;

  const {
    user = {},
    ref_link = "#",
    businessSummary = { left: 0, right: 0 },
  } = props;

  const userName = user?.name ?? "-";
  const userId = user?.id ?? "-";

  const fristsellObj = props?.fristsell ?? null;
  const createdAtRaw = fristsellObj?.created_at ?? user?.created_at;
  const createdAt = formatDT(createdAtRaw);

  const rewardPlans = useMemo(
    () => [
      { days: "30", daysNumber: 30, volume: "₹3,00,000", volumeNumber: 300000, reward: 10000 },
      { days: "60", daysNumber: 60, volume: "₹10,00,000", volumeNumber: 1000000, reward: 50000 },
      { days: "100", daysNumber: 100, volume: "₹30,00,000", volumeNumber: 3000000, reward: 150000 },
      { days: "150", daysNumber: 150, volume: "₹60,00,000", volumeNumber: 6000000, reward: 400000 },
      { days: "200", daysNumber: 200, volume: "₹1,50,00,000", volumeNumber: 15000000, reward: 1500000 },
    ],
    []
  );

  return (
    <AuthenticatedLayout header={<h2 className="text-lg sm:text-xl font-semibold text-gray-800">Dashboard</h2>}>
      <Head title="Dashboard" />

      <div className="mx-auto max-w-[1400px] px-2 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Welcome */}
        <div className="rounded-md bg-white shadow-sm ring-1 ring-slate-100">
          <div className="p-3 sm:p-4">
            <div className="text-slate-900 font-semibold mb-3 text-base sm:text-lg">
              Welcome {userName}
            </div>

            <div className="overflow-x-auto">
              <table className="min-w-full text-xs sm:text-sm text-center border border-slate-200">
                <tbody>
                  <tr className="border-b">
                    <td className="px-2 py-2 font-medium text-slate-700">User ID</td>
                    <td className="px-2 py-2 font-mono text-slate-900">{userId}</td>
                  </tr>
                  <tr className="border-b">
                    <td className="px-2 py-2 font-medium text-slate-700">Active Plan</td>
                    <td className="px-2 py-2 font-mono text-slate-900 capitalize">{CurrentPlan}</td>
                  </tr>
                  <tr className="border-b">
                    <td className="px-2 py-2 font-medium text-slate-700">Joined</td>
                    <td className="px-2 py-2">{createdAt}</td>
                  </tr>
                  <tr>
                    <td className="px-2 py-2 font-medium text-slate-700">Signup Link</td>
                    <td className="px-2 py-2">
                      <CopyField value={ref_link} />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div className="mt-4 flex flex-col sm:flex-row sm:justify-end gap-2">
              <a href="/profile" className="rounded bg-sky-600 px-3 py-2 text-white text-sm font-semibold hover:bg-sky-700 text-center">View Profile</a>
              <a href="/team" className="rounded bg-emerald-600 px-3 py-2 text-white text-sm font-semibold hover:bg-emerald-700 text-center">My Team</a>
              <a href="/team/tree" className="rounded bg-emerald-600 px-3 py-2 text-white text-sm font-semibold hover:bg-emerald-700 text-center">Tree</a>
            </div>
          </div>
        </div>

        {/* Star Rank */}
        <div className={`relative rounded-lg ${G.purpleIndigo} shadow-sm overflow-hidden border border-slate-200 p-4`}>
          <p className="text-center text-lg sm:text-2xl text-white">Star Rank Value</p>
          <button className="absolute top-3 sm:top-4 right-3 sm:right-4 rounded bg-white px-3 sm:px-5 py-1.5 sm:py-2 text-pink-500 text-xs sm:text-sm font-semibold hover:bg-slate-200">
            <a href="/income/star" className="font-bold">View</a>
          </button>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-6 sm:mt-8">
            <div className="bg-white shadow rounded-xl p-4 sm:p-6 w-full">
              <div className="text-xs text-gray-500">Left Volume</div>
              <div className="text-xl sm:text-2xl font-bold">{Number(left)}</div>
            </div>
            <div className="bg-white shadow rounded-xl p-4 sm:p-6 w-full">
              <div className="text-xs text-gray-500">Right Volume</div>
              <div className="text-xl sm:text-2xl font-bold">{Number(right)}</div>
            </div>
            {/* <div className="bg-white shadow rounded-xl p-4 sm:p-6 w-full">
              <div className="text-xs text-gray-500">Placement Matching</div>
              <div className="text-xl sm:text-2xl font-bold text-purple-600">
                {placement_combined?.matched ?? 0}
              </div>
            </div> */}
          </div>
        </div>

        {/* Stat cards */}
        <div className="grid grid-cols-1 xs:grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard title="VIP Weekly Salary" value="" icon="📍" actionHref="/income/vip-repurchase-salary" gradient={G.amber} />
          <StatCard title="Fresh Matching" value={formatINR(matched)} icon="🧮" actionHref="/income/binary" gradient={G.orangePink} />
          <StatCard title="Wallet Balance" value={availableBalance} icon="👛" gradient={G.tealBlue} />
          <StatCard title="Total Income" value={payoutBalance} icon="💼" actionHref="/payouts" gradient={G.emerald} />
          <StatCard title="MY PROFILE" value="" icon="👤" actionHref="/profile" gradient={G.sky} />
          <StatCard title="MY INCOME" value="" icon="₹" actionHref="/income" gradient={G.rose} />
          <StatCard title="MY TEAM" value={TotalTeam} icon="👥" actionHref="/team" gradient={G.lime} />
        </div>

        {/* Rewards */}
        <RewardPlan
          plans={rewardPlans}
          businessSummary={businessSummary}
          fristsell={fristsellObj}
        />

        {/* Bottom banner */}
        <div className={`${G.barEmerald} rounded-lg py-8 text-center text-white shadow-md`}>
          <div className="text-2xl font-bold">“ News ”</div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
