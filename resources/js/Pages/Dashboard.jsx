import React, { useState } from "react";
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
    <div className={`${gradient} relative overflow-hidden rounded-lg shadow-md`}>
      <div className="px-5 py-4 text-white">
        <div className="flex items-center justify-between">
          <div className="text-sm font-semibold opacity-95">{title}</div>
          <div className="text-xl opacity-90">{icon}</div>
        </div>
        <div className="mt-3 text-right text-2xl font-extrabold tabular-nums">
          {value}
        </div>
        <div className="mt-3">
          <a
            href={actionHref}
            className="inline-flex items-center rounded-md bg-white/20 px-3 py-1.5 text-xs font-semibold hover:bg-white/30 transition"
          >
            {actionText}
          </a>
        </div>
      </div>
    </div>
  );
}

/**
 * CopyField - reusable copy-to-clipboard field
 * Props:
 *  - label: (string) optional left label
 *  - value: (string) value to show & copy
 */
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
    <div className="flex items-center gap-2 w-full">
      {label ? (
        <div className="min-w-[4.5rem] text-sm font-medium text-slate-700">{label}</div>
      ) : null}
      <input
        className="w-full rounded border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-800 font-mono"
        value={value || ""}
        readOnly
      />
      <button
        onClick={copy}
        type="button"
        className={`rounded px-3 py-1.5 text-sm font-semibold transition ${
          copied ? "bg-green-600 text-white hover:bg-green-700" : "bg-emerald-600 text-white hover:bg-emerald-700"
        }`}
      >
        {copied ? "Copied!" : "Copy"}
      </button>
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

  const {
    user = {},
    sponsor = null,
    children = {},
    stats = {},
    recent_sells = [],
    team_sells = [],
    team_left_sells = [],
    team_right_sells = [],
    left_user = null,
    right_user = null,
    ref_link = "#",
    // controller-provided summary props
    businessSummary = { left: 0, right: 0 }, // lifetime
    timewiseSales = { first_half: { left: 0, right: 0 }, second_half: { left: 0, right: 0 } },
    carryTotals = { cf_left: 0, cf_right: 0 },
  } = props;

  const userName = user?.name ?? "-";
  const userId = user?.id ?? "-";
  const createdAt = formatDT(user?.created_at);

  const directReferrals = Number(stats?.direct_referrals ?? 0);
  const leftTeamCount = Number(stats?.left_team ?? (children?.left ? 1 : 0));
  const rightTeamCount = Number(stats?.right_team ?? (children?.right ? 1 : 0));
  const directPV = Number(stats?.direct_pv ?? 0);

  // minimal debug (client-side)
  if (typeof window !== "undefined") {
    console.log("businessSummary:", businessSummary, "timewiseSales:", timewiseSales, "carryTotals:", carryTotals);
  }

  return (
    <AuthenticatedLayout
      header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>}
    >
      <Head title="Dashboard" />

      

      {/* Main page body (welcome, cards, lists) */}
      <div className="mx-auto max-w-[1400px] px-3 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Welcome + referral box */}
        <div className="rounded-md bg-white shadow-sm ring-1 ring-slate-100">
          <div className="p-4">
            <div className="text-slate-900 font-semibold mb-3">Welcome {userName}</div>

            <div className="overflow-x-auto">
              <table className="min-w-full text-sm text-center border border-slate-200">
                <tbody>
                  <tr className="border-b">
                    <td className="px-2 py-2 font-medium text-slate-700 sm:px-4">User ID</td>
                    <td className="px-2 py-2 font-mono text-slate-900 sm:px-4">{userId}</td>
                  </tr>

                  <tr className="border-b">
                    <td className="px-2 py-2 font-medium text-slate-700 sm:px-4">Active Plan</td>
                    <td className="px-2 py-2 font-mono text-slate-900 sm:px-4 capitalize">
                      {CurrentPlan}
                    </td>
                  </tr>

                  <tr className="border-b">
                    <td className="px-2 py-2 font-medium text-slate-700 sm:px-4">Joined</td>
                    <td className="px-2 py-2 sm:px-4">{createdAt}</td>
                  </tr>

                  <tr className="border-b">
                    <td className="px-2 py-2 font-medium text-slate-700 sm:px-4">Signup Link</td>
                    <td className="px-2 py-2 sm:px-4">
                      <CopyField value={ref_link} />
                    </td>
                  </tr>

                </tbody>
              </table>
            </div>

            {/* ADDED: Buttons from first code (no other changes) */}
            <div className="mt-4 flex items-end justify-end gap-2">
              <a href="/profile" className="rounded bg-sky-600 px-3 py-2 text-white text-sm font-semibold hover:bg-sky-700">View Profile</a>
              <a href="/team" className="rounded bg-emerald-600 px-3 py-2 text-white text-sm font-semibold hover:bg-emerald-700">My Team</a>
              <a href="/team/tree" className="rounded bg-emerald-600 px-3 py-2 text-white text-sm font-semibold hover:bg-emerald-700">Tree</a>
            </div>
          </div>
        </div>

        {/* Top metric cards (colorful) */}
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          <StatCard title="Binary Matching" value="" icon="🧮" actionHref="/income/binary" gradient={G.orangePink} />
          <StatCard title="VIP Repurchase Salary" value=" " icon="📍" actionHref="/income/vip-repurchase-salary" gradient={G.amber} />
          <StatCard title="Star Matching" value="" icon="💸" actionHref="/income/star" gradient={G.purpleIndigo} />
          <StatCard title="Wallet Balance" value={availableBalance} icon="👛" gradient={G.tealBlue} />
          <StatCard title="Total Income" value={payoutBalance} icon="💼" actionHref="/payouts" gradient={G.emerald} />
          <StatCard title="MY PROFILE" value=" " icon="👤" actionHref="/profile" gradient={G.sky} />
          <StatCard title="MY INCOME" value=" " icon="₹" actionHref="/income" gradient={G.rose} />
          <StatCard title="MY TEAM" value={TotalTeam} icon="👥" actionHref="/team" gradient={G.lime} />
        </div>

        {/* Recent Purchases */}
        <div className="rounded-lg bg-white shadow-sm overflow-hidden border border-slate-200">
          <div className={`${G.barTeal} px-4 py-3 text-white font-semibold`}>Recent Purchases</div>
          <div className="p-4">
            {!(recent_sells && recent_sells.length) ? (
              <div className="text-slate-600 text-sm">No orders yet.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full text-sm">
                  <thead className="bg-slate-50">
                    <tr className="text-left text-slate-600 border-b">
                      <th className="py-2 pr-4">#ID</th>
                      <th className="py-2 pr-4">Product</th>
                      <th className="py-2 pr-4">Type</th>
                      <th className="py-2 pr-4">Amount</th>
                      <th className="py-2 pr-4">Status</th>
                      <th className="py-2">Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    {recent_sells.map((r) => (
                      <tr key={r.id} className="border-b last:border-0">
                        <td className="py-2 pr-4 font-medium">{r.id}</td>
                        <td className="py-2 pr-4">{r.product}</td>
                        <td className="py-2 pr-4 uppercase">{r.type}</td>
                        <td className="py-2 pr-4">{formatINR(r.amount)}</td>
                        <td className="py-2 pr-4">{r.status}</td>
                        <td className="py-2">{formatDT(r.created_at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>

        {/* Team Purchases (combined) */}
        <div className="rounded-lg bg-white shadow-sm overflow-hidden border border-slate-200">
          <div className={`${G.barOrange} px-4 py-3 text-white font-semibold`}>
            Team Purchases (Your Left/Right)
          </div>
          <div className="p-4">
            {!(team_sells && team_sells.length) ? (
              <div className="text-slate-600 text-sm">No team purchases yet.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full text-sm">
                  <thead className="bg-slate-50">
                    <tr className="text-left text-slate-600 border-b">
                      <th className="py-2 pr-4">#ID</th>
                      <th className="py-2 pr-4">Buyer</th>
                      <th className="py-2 pr-4">Leg</th>
                      <th className="py-2 pr-4">Product</th>
                      <th className="py-2 pr-4">Type</th>
                      <th className="py-2 pr-4">Amount</th>
                      <th className="py-2 pr-4">Status</th>
                      <th className="py-2">Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    {team_sells.map((r) => (
                      <tr key={r.id} className="border-b last:border-0">
                        <td className="py-2 pr-4 font-medium">{r.id}</td>
                        <td className="py-2 pr-4">
                          {r.buyer_name} <span className="text-slate-400">#{r.buyer_id}</span>
                        </td>
                        <td className="py-2 pr-4 font-semibold">
                          {r.leg === "R" ? "Right" : r.leg === "L" ? "Left" : r.leg || "-"}
                        </td>
                        <td className="py-2 pr-4">{r.product}</td>
                        <td className="py-2 pr-4 uppercase">{r.type}</td>
                        <td className="py-2 pr-4">{formatINR(r.amount)}</td>
                        <td className="py-2 pr-4">{r.status}</td>
                        <td className="py-2">{formatDT(r.created_at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>

        {/* Split tables: Left / Right recent purchases */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* Left Leg */}
          <div className="rounded-lg bg-white shadow-sm overflow-hidden border border-slate-200">
            <div className={`${G.barIndigo} px-4 py-3 text-white font-semibold`}>
              Left Leg Purchases {left_user ? `— ${left_user.name} (#${left_user.id})` : ""}
            </div>
            <div className="p-4">
              {!(team_left_sells && team_left_sells.length) ? (
                <div className="text-slate-600 text-sm">No left-leg purchases.</div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="min-w-full text-sm">
                    <thead className="bg-slate-50">
                      <tr className="text-left text-slate-600 border-b">
                        <th className="py-2 pr-4">#ID</th>
                        <th className="py-2 pr-4">Buyer</th>
                        <th className="py-2 pr-4">Product</th>
                        <th className="py-2 pr-4">Amount</th>
                        <th className="py-2 pr-4">Status</th>
                        <th className="py-2">Date</th>
                      </tr>
                    </thead>
                    <tbody>
                      {team_left_sells.map((r) => (
                        <tr key={r.id} className="border-b last:border-0">
                          <td className="py-2 pr-4 font-medium">{r.id}</td>
                          <td className="py-2 pr-4">
                            {r.buyer_name} <span className="text-slate-400">#{r.buyer_id}</span>
                          </td>
                          <td className="py-2 pr-4">{r.product}</td>
                          <td className="py-2 pr-4">{formatINR(r.amount)}</td>
                          <td className="py-2 pr-4">{r.status}</td>
                          <td className="py-2">{formatDT(r.created_at)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>

          {/* Right Leg */}
          <div className="rounded-lg bg-white shadow-sm overflow-hidden border border-slate-200">
            <div className={`${G.barTeal} px-4 py-3 text-white font-semibold`}>
              Right Leg Purchases {right_user ? `— ${right_user.name} (#${right_user.id})` : ""}
            </div>
            <div className="p-4">
              {!(team_right_sells && team_right_sells.length) ? (
                <div className="text-slate-600 text-sm">No right-leg purchases.</div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="min-w-full text-sm">
                    <thead className="bg-slate-50">
                      <tr className="text-left text-slate-600 border-b">
                        <th className="py-2 pr-4">#ID</th>
                        <th className="py-2 pr-4">Buyer</th>
                        <th className="py-2 pr-4">Product</th>
                        <th className="py-2 pr-4">Amount</th>
                        <th className="py-2 pr-4">Status</th>
                        <th className="py-2">Date</th>
                      </tr>
                    </thead>
                    <tbody>
                      {team_right_sells.map((r) => (
                        <tr key={r.id} className="border-b last:border-0">
                          <td className="py-2 pr-4 font-medium">{r.id}</td>
                          <td className="py-2 pr-4">
                            {r.buyer_name} <span className="text-slate-400">#{r.buyer_id}</span>
                          </td>
                          <td className="py-2 pr-4">{r.product}</td>
                          <td className="py-2 pr-4">{formatINR(r.amount)}</td>
                          <td className="py-2 pr-4">{r.status}</td>
                          <td className="py-2">{formatDT(r.created_at)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Bottom banner */}
        <div className={`${G.barEmerald} rounded-lg py-8 text-center text-white shadow-md`}>
          <div className="text-2xl font-bold">“ News ”</div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
