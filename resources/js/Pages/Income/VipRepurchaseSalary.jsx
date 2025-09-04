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
  slabs = [
    { rank: "VIP 1", volume: 30000, salary: 1000 },
    { rank: "VIP 2", volume: 100000, salary: 3000 },
    { rank: "VIP 3", volume: 200000, salary: 5000 },
    { rank: "VIP 4", volume: 500000, salary: 10000 },
    { rank: "VIP 5", volume: 1000000, salary: 25000 },
    { rank: "VIP 6", volume: 2000000, salary: 50000 },
    { rank: "VIP 7", volume: 5000000, salary: 100000 },
  ],
  summary = {
    left: 0,
    right: 0,
    matched: 0,
    achieved_rank: null,
    paid_this_month: 0,
    due: null,
  },
  month = "",
}) {
  const [m, setM] = React.useState(month || new Date().toISOString().slice(0, 7));

  const onMonthChange = (e) => {
    const v = e.target.value;
    setM(v);
    router.visit(`/income/vip-repurchase-salary?month=${encodeURIComponent(v)}`, {
      preserveScroll: true,
      preserveState: true,
    });
  };

  const achieved = summary?.achieved_rank ?? null;

  // progress to next rank (based on matched)
  const currentIdx =
    slabs
      .map((s) => s.volume)
      .filter((v) => (summary?.matched || 0) >= v)
      .length - 1;
  const next = slabs[currentIdx + 1] || null;
  const base = slabs[Math.max(currentIdx, 0)] || slabs[0];
  const baseVol = base?.volume || 1;
  const progress =
    next
      ? Math.min(100, Math.round(((summary?.matched - baseVol) / (next.volume - baseVol)) * 100))
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
              Monthly matching volumes decide your VIP rank & 3-month salary.
            </p>
          </div>

          <div className="flex items-center gap-2">
            <label className="text-xs text-gray-600" />
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

      {/* Gradient hero */}
      <div className="mx-auto max-w-6xl px-3 sm:px-6">
        <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-indigo-600 via-violet-600 to-fuchsia-600 p-4 sm:p-6 text-white shadow-sm">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div className="text-xs/6 opacity-95">Selected Month</div>
              <div className="text-xl sm:text-2xl font-bold tracking-tight">{m}</div>
            </div>

            {/* KPIs → 1/2/4 responsive grid */}
            <div className="grid grid-cols-1 gap-3 xs:grid-cols-2 lg:grid-cols-4 min-w-[220px]">
              <Kpi label="Left Volume" value={formatINR(summary?.left || 0)} />
              <Kpi label="Right Volume" value={formatINR(summary?.right || 0)} />
              <Kpi label="Matched" value={formatINR(summary?.matched || 0)} />
              <Kpi label="Paid This Month" value={formatINR(summary?.paid_this_month || 0)} />
            </div>
          </div>

          {/* Progress to next rank */}
          <div className="mt-5 rounded-xl bg-white/10 p-3 sm:p-4 backdrop-blur-sm">
            <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between text-[11px] sm:text-xs">
              <div className="font-medium">
                {achieved ? `Current Rank: ${achieved}` : `Current Rank: —`}
              </div>
              <div className="opacity-90">
                {next ? `Next: ${next.rank} at ${formatINR(next.volume)}` : "Max rank achieved"}
              </div>
            </div>
            <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-white/20">
              <div
                className="h-full rounded-full bg-white shadow-[0_0_10px_rgba(255,255,255,0.4)]"
                style={{ width: `${progress}%` }}
                title={`${progress}%`}
              />
            </div>
          </div>

          {/* Congrats / info pill */}
          <div className="mt-3">
            {achieved ? (
              <div className="inline-flex items-center gap-2 rounded-full bg-emerald-500/20 px-3 py-1.5 text-[13px] font-medium ring-1 ring-emerald-400/40">
                <span>🎉</span>
                <span>
                  Congrats! You achieved <b>{achieved}</b> in <b>{m}</b>.
                </span>
              </div>
            ) : (
              <div className="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1.5 text-[13px] font-medium ring-1 ring-white/30">
                <span>📈</span>
                <span>Keep pushing to unlock the next VIP rank.</span>
              </div>
            )}
          </div>

          <div className="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-white/20 blur-2xl" />
        </div>

        {/* Cards row */}
        <div className="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard
            title="Left Volume (Monthly)"
            main={formatINR(summary?.left || 0)}
            sub={`(${formatINRCompact(summary?.left || 0)})`}
          />
          <StatCard
            title="Right Volume (Monthly)"
            main={formatINR(summary?.right || 0)}
            sub={`(${formatINRCompact(summary?.right || 0)})`}
          />
          <StatCard title="Matched Volume (Monthly)" main={formatINR(summary?.matched || 0)} sub={`min(Left, Right)`} />
          <StatCard
            title="Paid This Month"
            main={formatINR(summary?.paid_this_month || 0)}
            sub={
              summary?.due && !summary?.due?.paid_at
                ? `Due: ${formatINR(summary?.due?.amount)} (installment)`
                : "—"
            }
          />
        </div>

        {/* Slab table (responsive & pretty) */}
        <div className="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
          <div className="bg-relative overflow-hidden rounded-2xl bg-gradient-to-r from-indigo-600 via-violet-600 to-fuchsia-600 p-4 sm:p-6 text-white shadow-sm backdrop-blur text-left">
            Salary Slabs (3 Months)
          </div>

          {/* Desktop table — prettier */}
          <div className="hidden md:block overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="sticky top-0 z-10">
                <tr className="bg-white/80 backdrop-blur text-left text-gray-600">
                  <Th className="w-36 rounded-tl-2xl">Rank</Th>
                  <Th>Monthly Matching Volume</Th>
                  <Th>Salary</Th>
                  <Th className="w-28 text-center rounded-tr-2xl">Status</Th>
                </tr>
              </thead>

              <tbody className="divide-y divide-gray-100">
                {slabs.map((s, idx) => {
                  const hit = (summary?.matched || 0) >= s.volume;
                  return (
                    <tr
                      key={idx}
                      className={`transition-colors ${
                        hit
                          ? "bg-emerald-50/70 hover:bg-emerald-50"
                          : idx % 2
                          ? "bg-gray-50/60 hover:bg-gray-50"
                          : "hover:bg-gray-50/60"
                      }`}
                    >
                      <Td className="font-semibold">{s.rank}</Td>

                      <Td className="whitespace-nowrap">
                        <span className="font-mono">{formatINR(s.volume)}</span>{" "}
                        <span className="text-gray-500">({formatINRCompact(s.volume)})</span>
                      </Td>

                      <Td className="whitespace-nowrap font-semibold">
                        <span className="font-mono">{formatINR(s.salary)}</span>
                      </Td>

                      <Td className="text-center">
                        <StatusPill hit={hit} />
                      </Td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* Mobile cards — tighter & clean */}
          <div className="md:hidden divide-y">
            {slabs.map((s, idx) => {
              const hit = (summary?.matched || 0) >= s.volume;
              return (
                <div key={idx} className="p-4">
                  <div className="flex items-center justify-between">
                    <div className="text-sm font-semibold">{s.rank}</div>
                    <StatusPill hit={hit} />
                  </div>

                  <div className="mt-3 grid grid-cols-2 gap-2 text-[13px]">
                    <div className="text-gray-500">Monthly Matching</div>
                    <div className="text-right font-medium">
                      <span className="font-mono">{formatINR(s.volume)}</span>{" "}
                      <span className="text-gray-500">({formatINRCompact(s.volume)})</span>
                    </div>

                    <div className="text-gray-500">Salary</div>
                    <div className="text-right font-semibold">
                      <span className="font-mono">{formatINR(s.salary)}</span>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* Small note */}
        <div className="mt-4 mb-8 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          <b>Note:</b> VIP salary is paid for 3 months once a slab is achieved. Matching volume is calculated as{" "}
          <i>min(Left, Right)</i> for the selected month.
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

function StatusPill({ hit }) {
  return (
    <span
      className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ${
        hit ? "bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200" : "bg-gray-100 text-gray-600 ring-1 ring-gray-200"
      }`}
    >
      {hit ? "Achieved" : "Pending"}
    </span>
  );
}

function StatCard({ title, main, sub }) {
  return (
    <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <div className="text-[11px] uppercase tracking-wide text-gray-500">{title}</div>
      <div className="mt-1 text-xl font-semibold text-gray-900">{main}</div>
      <div className="text-xs text-gray-500">{sub}</div>
    </div>
  );
}

function Th({ children, className = "" }) {
  return <th className={`px-4 py-3 text-xs font-semibold uppercase tracking-wide ${className}`}>{children}</th>;
}
function Td({ children, className = "" }) {
  return <td className={`px-4 py-3 text-gray-800 ${className}`}>{children}</td>;
}
