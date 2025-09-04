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

function CopyField({ label, value }) {
  const [copied, setCopied] = useState(false);
  const copy = async () => {
    try {
      await navigator.clipboard.writeText(value || "");
      setCopied(true);
      setTimeout(() => setCopied(false), 1400);
    } catch (e) {
      console.error(e);
    }
  };
  return (
    <div className="flex items-center gap-2">
      <div className="min-w-28 text-sm font-medium text-slate-700">{label}</div>
      <input
        className="w-full rounded border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-800"
        value={value || ""}
        readOnly
      />
      <button
        onClick={copy}
        type="button"
        className="rounded bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700"
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
  const Today = formatINR(props?.today_profit ?? 0);
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
  } = props;

  const userName = user?.name ?? "-";
  const userId = user?.id ?? "-";
  const createdAt = formatDT(user?.created_at);

  const directReferrals = Number(stats?.direct_referrals ?? 0);
  const leftTeamCount = Number(stats?.left_team ?? (children?.left ? 1 : 0));
  const rightTeamCount = Number(stats?.right_team ?? (children?.right ? 1 : 0));
  const directPV = Number(stats?.direct_pv ?? 0);

  // ✅ new: businessSummary + timewiseSales
  const businessSummary = props?.businessSummary ?? { left: 0, right: 0 };
  const timewiseSales = props?.timewiseSales ?? {
    morning: { left: 0, right: 0 },
    afternoon: { left: 0, right: 0 },
  };

  return (
    <AuthenticatedLayout
      header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>}
    >
      <Head title="Dashboard" />
      {/* -------------------- Business Summary Table -------------------- */}
      <div className="w-100 rounded-lg bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden">
        <div className="bg-gradient-to-r from-indigo-500 via-sky-500 to-cyan-500 px-4 py-3 text-white font-semibold text-center">
          Business Summary
        </div>
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm text-center border border-slate-200">
            <thead className="bg-slate-50">
              <tr className="border-b text-slate-700">
                <th className="py-3 px-4">Session</th>
                <th className="py-3 px-4">Left Business</th>
                <th className="py-3 px-4">Right Business</th>
              </tr>
            </thead>
            <tbody>
              {/* First Half 12 AM - 12 PM */}
              <tr className="border-b hover:bg-slate-50">
                <td className="py-3 px-4 font-medium text-slate-700">First Half 12 AM - 12 PM</td>
                <td className="py-3 px-4 text-emerald-700 font-semibold">
                  {formatINR(timewiseSales.first_half?.left ?? 0)}
                </td>
                <td className="py-3 px-4 text-blue-700 font-semibold">
                  {formatINR(timewiseSales.first_half?.right ?? 0)}
                </td>
              </tr>

              {/* Second Half 12 PM - 12 AM */}
              <tr className="hover:bg-slate-50">
                <td className="py-3 px-4 font-medium text-slate-700">Second Half 12 PM - 12 AM</td>
                <td className="py-3 px-4 text-emerald-700 font-semibold">
                  {formatINR(timewiseSales.second_half?.left ?? 0)}
                </td>
                <td className="py-3 px-4 text-blue-700 font-semibold">
                  {formatINR(timewiseSales.second_half?.right ?? 0)}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>


      <div className="mx-auto max-w-[1400px] px-3 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Welcome + referral box */}
        <div className="rounded-md bg-white shadow-sm ring-1 ring-slate-100">
          <div className="p-4">
            <div className="text-slate-900 font-semibold mb-3">Welcome {userName}</div>

            <div className="overflow-x-auto">
              <table className="min-w-full text-sm text-center border border-slate-200">
                <tbody>
                  <tr className="border-b">
                    <td className="px-4 py-2 font-medium text-slate-700">User ID</td>
                    <td className="px-4 py-2 font-mono text-slate-900">{userId}</td>
                  </tr>
                  <tr className="border-b">
                    <td className="px-4 py-2 font-medium text-slate-700">Active Plan</td>
                    <td className="px-4 py-2 font-mono text-slate-900">{CurrentPlan}</td>
                  </tr>
                  <tr className="border-b">
                    <td className="px-4 py-2 font-medium text-slate-700">Joined</td>
                    <td className="px-4 py-2">{createdAt}</td>
                  </tr>
                  <tr className="border-b">
                    <td className="px-4 py-2 font-medium text-slate-700">Signup Link</td>
                    <td className="px-4 py-2">
                      <CopyField label="" value={ref_link} />
                    </td>
                  </tr>
                  <tr>
                    <td className="px-4 py-2 font-medium text-slate-700">Quick Links</td>
                    <td className="px-4 py-2 flex justify-center gap-2">
                      <a
                        href="/profile"
                        className="rounded bg-emerald-600 px-3 py-1 text-white text-xs font-semibold hover:bg-emerald-700"
                      >
                        View Profile
                      </a>
                      <a
                        href="/team"
                        className="rounded bg-emerald-600 px-3 py-1 text-white text-xs font-semibold hover:bg-emerald-700"
                      >
                        My Team
                      </a>
                      <a
                        href="/team/tree"
                        className="rounded bg-emerald-600 px-3 py-1 text-white text-xs font-semibold hover:bg-emerald-700"
                        title="View Team Tree"
                      >
                        Tree
                      </a>
                    </td>
                  </tr>
                </tbody>
              </table>
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
        {/* Accounts/Team counters */}
        <div className="rounded-lg shadow-sm ring-1 ring-slate-100 overflow-hidden">
          <div className={`${G.barEmerald} px-4 py-3 text-white text-center font-semibold`}>
            <span className="opacity-95">“ Team Counters ”</span>
          </div>
          <div className="grid gap-4 p-4 md:grid-cols-2">
            <div className="rounded-lg border border-slate-200 bg-white">
              <div className="px-4 py-3 border-b border-slate-100 font-semibold text-slate-800">
                Account details
              </div>
              <div className="p-4">
                <dl className="grid grid-cols-2 gap-3 text-sm">
                  <dt className="text-slate-500">Package Name</dt>
                  <dd className="text-slate-900 font-medium">{user?.package_name ?? "-"}</dd>
                  <dt className="text-slate-500">Register Date & Time</dt>
                  <dd className="text-slate-900 font-medium">{createdAt}</dd>
                  <dt className="text-slate-500">Sponsor</dt>
                  <dd className="text-slate-900 font-medium">
                    {sponsor ? `${sponsor?.name ?? "-"} (#${sponsor?.id ?? "-"})` : "-"}
                  </dd>
                  <dt className="text-slate-500">Last Login</dt>
                  <dd className="text-slate-900 font-medium">{formatDT(user?.last_login_at)}</dd>
                </dl>
              </div>
            </div>

            <div className="rounded-lg border border-slate-200 bg-white">
              <div className="px-4 py-3 border-b border-slate-100 font-semibold text-slate-800">Team Count</div>
              <div className="p-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div className="flex items-center justify-between rounded-md bg-emerald-50 px-3 py-2">
                  <span className="text-slate-700">Direct Referral Count</span>
                  <span className="font-bold text-emerald-700">{directReferrals}</span>
                </div>
                <div className="flex items-center justify-between rounded-md bg-emerald-50 px-3 py-2">
                  <span className="text-slate-700">Direct PV Count</span>
                  <span className="font-bold text-emerald-700">{directPV}</span>
                </div>
                <div className="flex items-center justify-between rounded-md bg-emerald-50 px-3 py-2">
                  <span className="text-slate-700">Left Team</span>
                  <span className="font-bold text-emerald-700">{leftTeamCount}</span>
                </div>
                <div className="flex items-center justify-between rounded-md bg-emerald-50 px-3 py-2">
                  <span className="text-slate-700">Right Team</span>
                  <span className="font-bold text-emerald-700">{rightTeamCount}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Recent Purchases (Your own) */}
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

        {/* Team Purchases (any leg) */}
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

        {/* Split tables: Left / Right */}
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

        {/* Bottom banner (colored) */}
        <div className={`${G.barEmerald} rounded-lg py-8 text-center text-white shadow-md`}>
          <div className="text-2xl font-bold">“ News ”</div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
