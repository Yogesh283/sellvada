// resources/js/Pages/Income/VipRepurchaseSalary.jsx
import React from "react";
import { Head, router, usePage } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/* helpers */
const formatINR = (n) =>
  new Intl.NumberFormat("en-IN", {
    style: "currency",
    currency: "INR",
    maximumFractionDigits: 0,
  }).format(Number(n || 0));

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

  const placementCombined = server.placement_combined ?? { left: 0, right: 0 };
  const placementSells = server.placement_sells ?? { left: 0, right: 0 };
  const placementRepurchases = server.placement_repurchases ?? { left: 0, right: 0 };

  const matched = Number(server.placement_matched ?? 0);
  const pending = Number(server.placement_pending ?? 0);

  const volumes = slabs.map((s) => Number(s.volume || 0));
  const prevThreshold = (i) => (i === 0 ? 0 : volumes[i - 1]);

  const currentIdx = volumes.filter((v) => matched >= v).length - 1;
  const next = slabs[currentIdx + 1] || null;

  return (
    <AuthenticatedLayout
      header={
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 className="text-xl md:text-2xl font-bold text-gray-900">
              🎖️ VIP Weekly Salary
            </h1>
            <p className="text-xs text-gray-500">
              Your Weekly matching decides your VIP rank & 3-weekly salary.
            </p>
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
        {/* Top cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
          <div className="rounded-xl border bg-white p-5 shadow-sm">
            <div className="text-xs text-gray-500">Placement Left</div>
            <div className="mt-2 text-2xl font-bold text-indigo-700">
              {formatINR(placementCombined.left)}
            </div>
            <div className="text-xs text-gray-500 mt-1">
              Sell: {formatINR(placementSells.left)} • Repurchase:{" "}
              {formatINR(placementRepurchases.left)}
            </div>
            {/* 🔥 Highlight Carry Forward Left */}
            <div className="mt-2">
              <span className="inline-block rounded-md bg-green-400 px-2 py-1 text-xs font-semibold text-black-700">
                Carry Forward: {formatINR(server?.carry_forward?.left ?? 0)}
              </span>
            </div>
          </div>

          <div className="rounded-xl border bg-white p-5 shadow-sm">
            <div className="text-xs text-gray-500">Placement Right</div>
            <div className="mt-2 text-2xl font-bold text-indigo-700">
              {formatINR(placementCombined.right)}
            </div>
            <div className="text-xs text-gray-500 mt-1">
              Sell: {formatINR(placementSells.right)} • Repurchase:{" "}
              {formatINR(placementRepurchases.right)}
            </div>
            {/* 🔥 Highlight Carry Forward Right */}
            <div className="mt-2">
              <span className="inline-block rounded-md bg-green-500 px-2 py-1 text-xs font-semibold text-black-700">
                Carry Forward: {formatINR(server?.carry_forward?.right ?? 0)}
              </span>
            </div>
          </div>
        </div>

        {/* Progress bar */}
        <div className="mb-6 bg-white p-4 rounded-xl border shadow-sm">
          <div className="flex justify-between mb-2 text-sm text-gray-700">
            <span>
              Matched: <b className="text-emerald-700">{formatINR(matched)}</b>
            </span>
            {next ? (
              <span className="text-amber-600">
                Next slab ({next.rank}) needs{" "}
                {formatINR(Math.max(0, Number(next.volume) - matched))}
              </span>
            ) : (
              <span className="text-emerald-600">🎉 Max slab achieved</span>
            )}
          </div>

          <div className="w-full bg-gray-200 h-3 rounded-full overflow-hidden">
            <div
              className="h-3 bg-gradient-to-r from-emerald-400 to-emerald-600"
              style={{
                width: `${Math.min(
                  100,
                  Math.round((matched / (next?.volume || matched)) * 100)
                )}%`,
              }}
            ></div>
          </div>
        </div>

        {/* Slab table */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow">
          <div className="bg-gradient-to-r from-indigo-600 to-violet-600 h-12 flex items-center px-4">
            <h3 className="text-sm font-semibold text-white">
              Salary Slabs (3 Week)
            </h3>
          </div>

          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="bg-gray-50 text-left text-gray-700 uppercase text-xs tracking-wider">
                  <th className="px-4 py-3 w-40">Rank</th>
                  <th className="px-4 py-3">Matching Volume</th>
                  <th className="px-4 py-3">Salary</th>
                  <th className="px-4 py-3 w-36 text-center">Status</th>
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

                  return (
                    <tr
                      key={idx}
                      className={
                        pct === 100
                          ? "bg-emerald-50"
                          : pct > 0
                          ? "bg-amber-50"
                          : ""
                      }
                    >
                      <td className="px-4 py-3 font-semibold">{s.rank}</td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          <span className="font-mono">{formatINR(vol)}</span>
                        </div>
                      </td>
                      <td className="px-4 py-3 font-semibold">
                        {formatINR(s.salary)}
                      </td>
                      <td className="px-4 py-3 text-center">
                        {pct === 100 ? (
                          <span className="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold bg-emerald-100 text-emerald-700">
                            Achieved
                          </span>
                        ) : pct > 0 ? (
                          <div className="w-full bg-gray-200 h-2 rounded-full">
                            <div
                              className="h-2 bg-amber-500 rounded-full"
                              style={{ width: `${pct}%` }}
                            ></div>
                          </div>
                        ) : (
                          <span className="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold bg-gray-100 text-gray-600">
                            Pending
                          </span>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>

        {/* Note box */}
        <div className="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          <b>
            VIP Weekly Salary Plan ✔ Salary Cycle: Every Monday to Sunday ✔
            Salary Credited: After Weekly Closing
          </b>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
