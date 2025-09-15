// resources/js/Pages/Income/VipRepurchaseSalary.jsx
import React from "react";
import { Head, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/* ---------------- helpers ---------------- */
const formatINR = (n) =>
  new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR", maximumFractionDigits: 0 }).format(
    Number(n || 0)
  );

const formatINRCompact = (n) => {
  const num = Number(n || 0);
  if (num >= 1_00_00_000) return `${(num / 1_00_00_000).toFixed(2)} Cr`;
  if (num >= 1_00_000) return `${(num / 1_00_000).toFixed(2)} Lac`;
  if (num >= 1_000) return `${(num / 1_000).toFixed(2)} K`;
  return `${num}`;
};

/* ---------------- page ---------------- */
export default function VipRepurchaseSalary({
  slabs = [],
  summary = { left:0,right:0,matched:0, paid_this_month:0, due:null },
  achieved_rank = null,
  team_sells = {left:0,right:0,rows:[]},
  team_repurchases = {left:0,right:0,rows:[]},
  team_combined = {left:0,right:0,matched:0},
  placement_sells = {left:0,right:0,cnt_left:0,cnt_right:0},
  placement_repurchases = {left:0,right:0,cnt_left:0,cnt_right:0},
  placement_combined = {left:0,right:0,matched:0},
  month = ""
}) {
  const [m, setM] = React.useState(month || new Date().toISOString().slice(0, 7));
  const matched = Number(summary?.matched || 0);

  const onMonthChange = (e) => {
    const v = e.target.value;
    setM(v);
    router.visit(`/income/vip-repurchase-salary?month=${encodeURIComponent(v)}`, {
      preserveScroll: true,
      preserveState: true,
    });
  };

  // small helper to render a money card
  function MoneyCard({ title, amount, subtitle }) {
    return (
      <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div className="text-[11px] uppercase tracking-wide text-gray-500">{title}</div>
        <div className="mt-1 text-xl font-semibold text-gray-900">{formatINR(amount)}</div>
        {subtitle && <div className="text-xs text-gray-500">{subtitle}</div>}
      </div>
    );
  }

  // progress to next rank (based on matched)
  const currentIdx =
    slabs
      .map((s) => s.volume)
      .filter((v) => matched >= v)
      .length - 1;
  const next = slabs[currentIdx + 1] || null;
  const base = slabs[Math.max(currentIdx, 0)] || slabs[0] || {volume:1};
  const baseVol = base?.volume || 1;
  const overallProgress = next
    ? Math.min(100, Math.round(((matched - baseVol) / (next.volume - baseVol)) * 100))
    : 100;

  return (
    <AuthenticatedLayout
      header={
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 className="text-lg sm:text-xl md:text-2xl font-semibold text-gray-900">
              VIP Repurchase Salary
            </h1>
            <p className="text-xs text-gray-500">
              Monthly matching volumes decide your VIP rank & 3-month salary. (sell + repurchase combined)
            </p>
          </div>

          <div className="flex items-center gap-2">
            <input
              type="month"
              value={m}
              onChange={onMonthChange}
              className="w-[160px] rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
            />
          </div>
        </div>
      }
    >
      <Head title="VIP Repurchase Salary" />

      <div className="mx-auto max-w-6xl px-3 sm:px-6">
        <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-indigo-600 via-violet-600 to-fuchsia-600 p-4 sm:p-6 text-white shadow-sm">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div className="text-xs/6 opacity-95">Selected Month</div>
              <div className="text-xl sm:text-2xl font-bold tracking-tight">{m}</div>
            </div>

            <div className="grid grid-cols-1 gap-3 xs:grid-cols-2 lg:grid-cols-4 min-w-[220px]">
              <Kpi label="Team Left (sell+repurchase)" value={formatINR(team_combined.left || 0)} />
              <Kpi label="Team Right (sell+repurchase)" value={formatINR(team_combined.right || 0)} />
              <Kpi label="Matched (combined)" value={formatINR(team_combined.matched || 0)} />
              <Kpi label="Paid This Month" value={formatINR(summary?.paid_this_month || 0)} />
            </div>
          </div>

          <div className="mt-5 rounded-xl bg-white/10 p-3 sm:p-4 backdrop-blur-sm">
            <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between text-[11px] sm:text-xs">
              <div className="font-medium">
                {achieved_rank ? `Current Rank: ${achieved_rank}` : `Current Rank: —`}
              </div>
              <div className="opacity-90">
                {next ? `Next: ${next.rank} at ${formatINR(next.volume)}` : "Max rank achieved"}
              </div>
            </div>
            <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-white/20">
              <div
                className="h-full rounded-full bg-white shadow-[0_0_10px_rgba(255,255,255,0.4)]"
                style={{ width: `${overallProgress}%` }}
                title={`${overallProgress}%`}
              />
            </div>
          </div>

          <div className="mt-3">
            {achieved_rank ? (
              <div className="inline-flex items-center gap-2 rounded-full bg-emerald-500/20 px-3 py-1.5 text-[13px] font-medium ring-1 ring-emerald-400/40">
                <span>🎉</span>
                <span>
                  Congrats! You achieved <b>{achieved_rank}</b> in <b>{m}</b>.
                </span>
              </div>
            ) : (
              <div className="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1.5 text-[13px] font-medium ring-1 ring-white/30">
                <span>📈</span>
                <span>Keep pushing to unlock the next VIP rank.</span>
              </div>
            )}
          </div>
        </div>

        {/* top KPI row */}
        <div className="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <MoneyCard title="Team Left (sell)" amount={team_sells.left} subtitle={`Placement: left`} />
          <MoneyCard title="Team Right (sell)" amount={team_sells.right} subtitle={`Placement: right`} />
          <MoneyCard title="Team Left (repurchase)" amount={team_repurchases.left} subtitle={`Placement: left`} />
          <MoneyCard title="Team Right (repurchase)" amount={team_repurchases.right} subtitle={`Placement: right`} />
        </div>

        {/* combined row */}
        <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <MoneyCard title="Team Combined Left (sell+repurchase)" amount={team_combined.left} />
          <MoneyCard title="Team Combined Right (sell+repurchase)" amount={team_combined.right} />
          <MoneyCard title="Team Combined Matched" amount={team_combined.matched} />
        </div>

        {/* Placement combined */}
        <div className="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
          <MoneyCard title="Placement Combined Left" amount={placement_combined.left} />
          <MoneyCard title="Placement Combined Right" amount={placement_combined.right} />
        </div>

        {/* Slab table (uses summary.matched which is combined) */}
        <div className="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow">
          <div className="bg-gradient-to-r from-indigo-600 to-violet-600 h-12 flex items-center px-4">
            <h3 className="text-sm font-semibold text-white">Salary Slabs (3 Months)</h3>
          </div>

          <div className="hidden md:block overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="bg-gray-50 text-left text-gray-700 uppercase text-xs tracking-wider">
                  <Th className="w-36 rounded-tl-2xl">Rank</Th>
                  <Th>Monthly Matching Volume</Th>
                  <Th>Salary</Th>
                  <Th className="w-56 text-center rounded-tr-2xl">Progress / Status</Th>
                </tr>
              </thead>

              <tbody className="divide-y divide-gray-100">
                {slabs.map((s, idx) => {
                  const hit = (team_combined.matched || 0) >= s.volume;
                  const pct = Math.min(100, Math.round(((team_combined.matched || 0) / s.volume) * 100));
                  const shownPct = isFinite(pct) ? pct : 0;
                  const shownAmount = Math.min(team_combined.matched || 0, s.volume);
                  return (
                    <tr key={idx} className={`transition-colors duration-200 ${hit ? "bg-emerald-50 hover:bg-emerald-100" : idx % 2 ? "bg-gray-50 hover:bg-gray-100" : "hover:bg-gray-50"}`}>
                      <Td className="font-semibold px-4 py-3">{s.rank}</Td>
                      <Td className="whitespace-nowrap px-4 py-3">
                        <div className="flex items-center gap-2">
                          <span className="font-mono">{formatINR(s.volume)}</span>
                          <span className="text-gray-400 text-xs">({formatINRCompact(s.volume)})</span>
                        </div>
                      </Td>
                      <Td className="whitespace-nowrap font-semibold px-4 py-3">
                        <span className="font-mono">{formatINR(s.salary)}</span>
                      </Td>
                      <Td className="px-4 py-3">
                        <div className="mb-2 text-[13px] flex items-center justify-between">
                          <div className="text-sm font-medium">
                            {hit ? (
                              <span className="inline-flex items-center gap-2 text-emerald-700"><strong>Achieved</strong></span>
                            ) : (
                              <span className="text-gray-700 font-medium">{formatINR(shownAmount)} / {formatINR(s.volume)}</span>
                            )}
                          </div>
                          <div className="text-xs text-gray-500">{shownPct}%</div>
                        </div>
                        <div className="h-3 w-full rounded-full bg-gray-100 overflow-hidden">
                          <div
                            className={`h-full rounded-full ${hit ? 'bg-emerald-500' : 'bg-amber-400'}`}
                            style={{ width: `${shownPct}%` }}
                            title={`${shownPct}% full`}
                          />
                        </div>
                      </Td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* mobile */}
          <div className="md:hidden divide-y">
            {slabs.map((s, idx) => {
              const hit = (team_combined.matched || 0) >= s.volume;
              const pct = Math.min(100, Math.round(((team_combined.matched || 0) / s.volume) * 100));
              const shownPct = isFinite(pct) ? pct : 0;
              const shownAmount = Math.min(team_combined.matched || 0, s.volume);
              return (
                <div key={idx} className="p-4 bg-white hover:bg-gray-50 transition-colors duration-150">
                  <div className="flex items-center justify-between">
                    <div className="text-sm font-semibold">{s.rank}</div>
                    <div className="text-xs">{hit ? <span className="text-emerald-700 font-semibold">Achieved</span> : `${shownPct}%`}</div>
                  </div>

                  <div className="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div className="text-gray-500">Monthly Matching</div>
                    <div className="text-right font-medium">
                      <span className="font-mono">{formatINR(s.volume)}</span>{" "}
                      <span className="text-gray-400">({formatINRCompact(s.volume)})</span>
                    </div>

                    <div className="text-gray-500">Salary</div>
                    <div className="text-right font-semibold">
                      <span className="font-mono">{formatINR(s.salary)}</span>
                    </div>

                    <div className="text-gray-500">Progress</div>
                    <div className="text-right font-medium">
                      {hit ? formatINR(s.volume) : `${formatINR(shownAmount)} / ${formatINR(s.volume)}`}
                    </div>
                  </div>

                  <div className="mt-3 h-3 w-full rounded-full bg-gray-100 overflow-hidden">
                    <div
                      className={`h-full rounded-full ${hit ? 'bg-emerald-500' : 'bg-amber-400'}`}
                      style={{ width: `${shownPct}%` }}
                      title={`${shownPct}% full`}
                    />
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* debug / details: show raw team/placement numbers in a compact table */}
        <div className="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="rounded-xl border bg-white p-4">
            <h4 className="font-semibold mb-2">Team (referral) Breakdown</h4>
            <div className="text-sm text-gray-600 mb-2">Sell: Left {formatINR(team_sells.left)} — Right {formatINR(team_sells.right)}</div>
            <div className="text-sm text-gray-600 mb-2">Repurchase: Left {formatINR(team_repurchases.left)} — Right {formatINR(team_repurchases.right)}</div>
            <div className="text-sm text-gray-800 font-medium">Combined: Left {formatINR(team_combined.left)} — Right {formatINR(team_combined.right)} — Matched {formatINR(team_combined.matched)}</div>
          </div>

          <div className="rounded-xl border bg-white p-4">
            <h4 className="font-semibold mb-2">Placement Breakdown</h4>
            <div className="text-sm text-gray-600 mb-2">Sell: Left {formatINR(placement_sells.left)} — Right {formatINR(placement_sells.right)}</div>
            <div className="text-sm text-gray-600 mb-2">Repurchase: Left {formatINR(placement_repurchases.left)} — Right {formatINR(placement_repurchases.right)}</div>
            <div className="text-sm text-gray-800 font-medium">Combined: Left {formatINR(placement_combined.left)} — Right {formatINR(placement_combined.right)} — Matched {formatINR(placement_combined.matched)}</div>
          </div>
        </div>

        <div className="mt-8 mb-12 text-sm text-gray-500">
          <b>Note:</b> Above totals are computed for the selected month and include both <i>sell</i> and <i>repurchase</i> transactions (status='paid'). Matched = min(left, right) using combined values.
        </div>
      </div>
    </AuthenticatedLayout>
  );
}

/* ---------------- UI bits ---------------- */
function Kpi({ label, value }) {
  return (
    <div className="rounded-xl bg-white/10 px-4 py-3 text-white shadow-sm ring-1 ring-white/20">
      <div className="text-[11px] opacity-90">{label}</div>
      <div className="truncate text-lg font-semibold" title={value}>
        {value}
      </div>
    </div>
  );
}
function Th({ children, className = "" }) {
  return <th className={`px-4 py-3 text-xs font-semibold uppercase tracking-wide ${className}`}>{children}</th>;
}
function Td({ children, className = "" }) {
  return <td className={`px-4 py-3 text-gray-800 ${className}`}>{children}</td>;
}
