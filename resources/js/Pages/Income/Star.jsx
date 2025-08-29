import React from "react";
import { usePage, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";


export default function Star() {
  const { asOf, filters, left, right, matched, rows } = usePage().props;

  const [from, setFrom] = React.useState(filters?.from || "");
  const [to, setTo] = React.useState(filters?.to || "");

  const fmtINR = (n) =>
    Number(n || 0).toLocaleString("en-IN", {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    });

  const fmtMoney = (n) =>
    Number(n || 0).toLocaleString("en-IN", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });

  const buildUrl = () => {
    const p = new URLSearchParams();
    if (from) p.set("from", from);
    if (to) p.set("to", to);
    const qs = p.toString();
    return qs ? `/income/star?${qs}` : `/income/star`;
  };

  return (
    <AuthenticatedLayout>
    <div className="p-6 max-w-6xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-blue-700">Star Rank Income</h1>
        <div className="text-sm text-gray-500">As of: {asOf}</div>
      </div>

      {/* Summary strip (user volumes) */}
      <div className="grid md:grid-cols-3 gap-4">
        <div className="bg-white shadow rounded-xl p-4">
          <div className="text-xs text-gray-500">Left Volume</div>
          <div className="text-xl font-semibold">{fmtINR(left)}</div>
        </div>
        <div className="bg-white shadow rounded-xl p-4">
          <div className="text-xs text-gray-500">Right Volume</div>
          <div className="text-xl font-semibold">{fmtINR(right)}</div>
        </div>
        <div className="bg-white shadow rounded-xl p-4">
          <div className="text-xs text-gray-500">Total Matching Volume</div>
          <div className="text-xl font-semibold">{fmtINR(matched)}</div>
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-xl shadow p-4">
        <div className="flex flex-wrap gap-3 items-end">
          <div>
            <label className="block text-xs text-gray-500 mb-1">From</label>
            <input
              type="date"
              value={from || ""}
              onChange={(e) => setFrom(e.target.value)}
              className="border rounded px-3 py-2"
            />
          </div>
          <div>
            <label className="block text-xs text-gray-500 mb-1">To</label>
            <input
              type="date"
              value={to || ""}
              onChange={(e) => setTo(e.target.value)}
              className="border rounded px-3 py-2"
            />
          </div>
          <a
            href={buildUrl()}
            className="inline-flex items-center px-4 py-2 rounded bg-black text-white hover:opacity-90"
          >
            Apply
          </a>
          <Link
            href="/income/star"
            className="inline-flex items-center px-4 py-2 rounded border hover:bg-gray-50"
          >
            Reset
          </Link>
        </div>
      </div>

      {/* Table (image style) */}
      <div className="bg-white rounded-xl shadow overflow-hidden">
        <div className="px-5 py-4 border-b">
          <h2 className="font-semibold">Ranks</h2>
          <p className="text-xs text-gray-500">
            New IDs (Silver, Gold, Diamond) + Re-purchases ka matching volume count hota hai.
          </p>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-green-600 text-white text-left">
                <th className="px-5 py-3">Rank</th>
                <th className="px-5 py-3">Total Matching Volume</th>
                <th className="px-5 py-3">Income (INR)</th>
                <th className="px-5 py-3">Status</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => {
                const achieved = r.achieved;
                return (
                  <tr
                    key={r.no}
                    className={`border-t ${achieved ? "bg-green-50" : ""}`}
                  >
                    <td className="px-5 py-3 font-medium">{r.name}</td>
                    <td className="px-5 py-3">{fmtINR(r.threshold)}</td>
                    <td className="px-5 py-3">{fmtINR(r.income)}</td>
                    <td className="px-5 py-3">
                      {achieved ? (
                        <span className="inline-flex items-center gap-2 text-green-700 font-medium">
                          ✓ Achieved
                        </span>
                      ) : (
                        <div className="flex items-center gap-3">
                          <span className="text-gray-600">Pending</span>
                          <div className="w-32 h-2 bg-gray-200 rounded">
                            <div
                              className="h-2 bg-green-500 rounded"
                              style={{ width: `${r.progress.toFixed(0)}%` }}
                              title={`${r.progress.toFixed(0)}%`}
                            />
                          </div>
                          <span className="text-xs text-gray-500">
                            Need: {fmtINR(r.remaining)}
                          </span>
                        </div>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>
    </div>
    </AuthenticatedLayout>
  );
}
