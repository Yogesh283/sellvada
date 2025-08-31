// resources/js/Pages/Dashboard.jsx
import React, { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, usePage } from "@inertiajs/react";

/* -------------------- UI helpers (with colors) -------------------- */
const G = {
  // Card gradients
  tealBlue: "bg-gradient-to-r from-cyan-400 to-blue-600",
  emerald: "bg-gradient-to-r from-emerald-400 to-green-600",
  orangePink: "bg-gradient-to-r from-orange-400 to-pink-500",
  purpleIndigo: "bg-gradient-to-r from-purple-500 to-indigo-600",
  amber: "bg-gradient-to-r from-amber-400 to-yellow-500",
  sky: "bg-gradient-to-r from-sky-400 to-cyan-600",
  rose: "bg-gradient-to-r from-rose-500 to-red-600",
  lime: "bg-gradient-to-r from-lime-400 to-green-600",

  // Bars (section headers)
  barTeal: "bg-gradient-to-r from-teal-500 via-cyan-500 to-sky-500",
  barOrange: "bg-gradient-to-r from-orange-500 via-pink-500 to-rose-500",
  barIndigo: "bg-gradient-to-r from-indigo-600 via-purple-600 to-fuchsia-600",
  barEmerald: "bg-gradient-to-r from-green-500 via-emerald-600 to-teal-600",

  // Kept from your original (unused now but harmless to keep)
  emerald: "bg-gradient-to-r from-green-300 via-green-400 to-green-500",
  emeraldBar: "bg-gradient-to-r from-green-400 via-green-500 to-green-600",
  whiteGreen: "bg-gradient-to-b from-white via-green-200 to-green-700",
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
  gradient = G.emerald, // default (but we pass custom ones below)
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
        <div className="mt-3 text-right text-2xl font-extrabold tabular-nums drop-shadow-[0_1px_0_rgba(0,0,0,0.25)]">
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
      <div className="pointer-events-none absolute -right-12 -top-10 h-40 w-40 rounded-full bg-white/20 blur-2xl" />
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
  const { wallet_amount } = usePage().props;
  const availableBalance = formatINR(wallet_amount ?? 0);

  const { payout_wallet } = usePage().props;
  const payoutBalance = formatINR(payout_wallet ?? 0);

  const { today_profit } = usePage().props;
  const Today = formatINR(today_profit ?? 0);

  const { total_team } = usePage().props;
  const TotalTeam = total_team ?? 0;

  const {
    user = {},
    user_all: userAll = {},
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
    wallets = {},
  } = usePage().props;

  const userName = user?.name ?? "-";
  const userId = user?.id ?? "-";
  const referralId = user?.referral_id ?? "-";
  const createdAt = formatDT(user?.created_at);

  const moneyOut = formatINR(wallets?.withdraw_total ?? 0);

  const directReferrals = Number(stats?.direct_referrals ?? 0);
  const leftTeamCount = Number(stats?.left_team ?? (children?.left ? 1 : 0));
  const rightTeamCount = Number(stats?.right_team ?? (children?.right ? 1 : 0));
  const directPV = Number(stats?.direct_pv ?? 0);

  return (
    <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>}>
      <Head title="Dashboard" />

      <div className="mx-auto max-w-[1400px] px-3 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Welcome + referral box */}
        <div className="rounded-md bg-white shadow-sm ring-1 ring-slate-100">
          <div className="grid gap-4 p-4 md:grid-cols-2">
            <div className="text-sm text-slate-700">
              <div className="text-slate-900 font-semibold">Welcome {userName}</div>
              <div>
                User ID: <span className="font-mono text-slate-900">{userId}</span>
              </div>
              <div className="mt-1">
                Referral ID: <span className="font-mono text-slate-900">{referralId}</span>
              </div>
              <div className="mt-1">
                Joined: <span className="font-medium">{createdAt}</span>
              </div>

              <div className="mt-3 text-slate-500 text-xs">Quick Copy</div>
              <div className="mt-2 space-y-2">
                <CopyField label="Signup Link" value={ref_link} />
                <CopyField label="Referral ID" value={referralId} />
              </div>
            </div>

            <div className="flex items-end justify-end gap-2">
              <a
                href="/profile"
                className="rounded bg-emerald-600 px-3 py-2 text-white text-sm font-semibold hover:bg-emerald-700"
              >
                View Profile
              </a>
              <a
                href="/team"
                className="rounded bg-emerald-600 px-3 py-2 text-white text-sm font-semibold hover:bg-emerald-700"
              >
                My Team
              </a>
            </div>
          </div>
        </div>

        {/* Top metric cards (colorful) */}
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          <StatCard title="Wallet Balance" value={availableBalance} icon="👛" gradient={G.tealBlue} />
          <StatCard title="Total Income" value={payoutBalance} icon="💼" actionHref="/payouts" gradient={G.emerald} />
          <StatCard title="Binary Matching" value="" icon="🧮" actionHref="/income/binary" gradient={G.orangePink} />
          <StatCard title="Star Matching" value="" icon="💸" actionHref="/income/star" gradient={G.purpleIndigo} />
        </div>

        {/* Profile & My Team row (colorful) */}
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          <StatCard title="VIP Repurchase Salary" value=" " icon="📍" actionHref="/income/vip-repurchase-salary" gradient={G.amber} />
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
