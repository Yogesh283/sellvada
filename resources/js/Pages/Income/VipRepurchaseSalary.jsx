// resources/js/Pages/Income/VipRepurchaseSalary.jsx
import React from "react";
import { Head, router, usePage } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/* helpers */
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

export default function VipRepurchaseSalary() {
  const page = usePage();
  const server = page.props || {};

  const slabs = server.slabs ?? [
    { rank: "VIP 1", volume: 30000, salary: 1000 },
    { rank: "VIP 2", volume: 100000, salary: 3000 },
    { rank: "VIP 3", volume: 200000, salary: 5000 },
    { rank: "VIP 4", volume: 500000, salary: 10000 },
    { rank: "VIP 5", volume: 1000000, salary: 25000 },
    { rank: "VIP 6", volume: 2000000, salary: 50000 },
    { rank: "VIP 7", volume: 5000000, salary: 100000 },
  ];

  const monthProp = server.month ?? new Date().toISOString().slice(0, 7);
  const [m, setM] = React.useState(monthProp);
  const onMonthChange = (e) => {
    const v = e.target.value;
    setM(v);
    router.visit(`/income/vip-repurchase-salary?month=${encodeURIComponent(v)}`, {
      preserveScroll: true,
      preserveState: true,
    });
  };

  const placementCombined = server.placement_combined ?? { left: server.left ?? 0, right: server.right ?? 0 };
  const placementSells = server.placement_sells ?? { left: 0, right: 0 };
  const placementRepurchases = server.placement_repurchases ?? { left: 0, right: 0 };

  const left = Number(placementCombined.left || 0);
  const right = Number(placementCombined.right || 0);
  const matched = Math.min(left, right);
  const pending = Math.abs(left - right);

  // helpers for per-row progress
  const volumes = slabs.map((s) => Number(s.volume || 0));
  const prevThreshold = (i) => (i === 0 ? 0 : volumes[i - 1]);

  // decide overall current index for header next-slab
  const currentIdx = volumes.filter((v) => matched >= v).length - 1;
  const next = slabs[currentIdx + 1] || null;
  const achieved = server.achieved_rank ?? null;

  return (
    <AuthenticatedLayout
      header={
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 className="text-lg sm:text-xl md:text-2xl font-semibold text-gray-900">VIP Repurchase Salary</h1>
            <p className="text-xs text-gray-500">Matching volumes decide your VIP rank & 3-month salary. (Showing Placement combined)</p>
          </div>

          <div className="flex items-center gap-2">
            <input
              type="month"
              value={m}
              onChange={onMonthChange}
              className="w-[160px] rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm"
            />
          </div>
        </div>
      }
    >
      <Head title="VIP Repurchase Salary" />

      <div className="mx-auto max-w-6xl px-3 sm:px-6">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
          <div className="rounded-xl border bg-white p-4 shadow-sm">
            <div className="text-xs text-gray-500">Placement Combined Left</div>
            <div className="mt-2 text-xl font-bold">{formatINR(left)}</div>
            <div className="text-xs text-gray-400 mt-1">Sell: {formatINR(placementSells.left)} • Repurchase: {formatINR(placementRepurchases.left)}</div>
            <div className="text-xs text-gray-400 mt-2">Carry Forward: {formatINR(server?.carry_forward?.left ?? 0)}</div>
          </div>

          <div className="rounded-xl border bg-white p-4 shadow-sm">
            <div className="text-xs text-gray-500">Placement Combined Right</div>
            <div className="mt-2 text-xl font-bold">{formatINR(right)}</div>
            <div className="text-xs text-gray-400 mt-1">Sell: {formatINR(placementSells.right)} • Repurchase: {formatINR(placementRepurchases.right)}</div>
            <div className="text-xs text-gray-400 mt-2">Carry Forward: {formatINR(server?.carry_forward?.right ?? 0)}</div>
          </div>
        </div>

        <div className="mb-4">
          <div className="rounded-lg border bg-white p-3 shadow-sm text-sm">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
              <div className="text-gray-700">
                <strong>Placement Matched:</strong> {formatINR(matched)} &nbsp;•&nbsp; <span className="text-gray-500">Pending: {formatINR(pending)}</span>
              </div>

              <div className="text-sm text-gray-600">
                {next ? <span className="ml-4 text-amber-600">Next slab ({next.rank}) needs: {formatINR(Math.max(0, Number(next.volume) - matched))}</span> : <span className="ml-4 text-emerald-600">Max slab reached</span>}
              </div>
            </div>
          </div>
        </div>

        {/* SLAB TABLE — ALWAYS VISIBLE */}
        <div className="mt-2 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow">
          <div className="bg-gradient-to-r from-indigo-600 to-violet-600 h-12 flex items-center px-4">
            <h3 className="text-sm font-semibold text-white">Salary Slabs (3 Months)</h3>
          </div>

          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="bg-gray-50 text-left text-gray-700 uppercase text-xs tracking-wider">
                  <th className="px-4 py-3 w-40">Rank</th>
                  <th className="px-4 py-3">Monthly Matching Volume</th>
                  <th className="px-4 py-3">Salary</th>
                  <th className="px-4 py-3 w-36 text-center">Progress / Status</th>
                </tr>
              </thead>

              <tbody className="divide-y divide-gray-100">
                {slabs.map((s, idx) => {
                  const vol = Number(s.volume || 0);
                  const prev = prevThreshold(idx);
                  let pct = 0;
                  if (matched >= vol) pct = 100;
                  else if (matched <= prev) pct = 0;
                  else pct = Math.round(((matched - prev) / (vol - prev)) * 100);

                  const isFull = pct === 100;
                  const isPartial = pct > 0 && pct < 100;

                  return (
                    <tr key={idx} className={`${isFull ? "bg-emerald-50" : isPartial ? "bg-amber-50" : ""}`}>
                      <td className="px-4 py-3 font-semibold">{s.rank}</td>

                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          <span className="font-mono">{formatINR(vol)}</span>
                          <span className="text-gray-400 text-xs">({formatINRCompact(vol)})</span>
                        </div>
                      </td>

                      <td className="px-4 py-3 font-semibold">{formatINR(s.salary)}</td>

                      <td className="px-4 py-3 text-center">
                        {isFull ? (
                          <span className="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold bg-emerald-100 text-emerald-700">Achieved</span>
                        ) : isPartial ? (
                          <div className="text-amber-600 font-medium">{pct}%</div>
                        ) : (
                          <span className="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold bg-gray-100 text-gray-600">Pending</span>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>

        <div className="mt-4 mb-8 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          <div><b>Note:</b> Placement combined = Sell + Repurchase (placement tree). Matching = <i>min(Left, Right)</i>. Pending = |Left − Right|.</div>
          <div className="mt-2"><b>Carry Forward:</b> unmatched volume from previous month forwarded automatically.</div>
          <div className="mt-2"><b>Weekly closing:</b> Salary week is Monday → Sunday — closing command generates weekly installments on next Monday (server-side).</div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
