import React from "react";
import { usePage, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";


export default function Binary() {
  const { asOf, filters, totals, matrix, recent } = usePage().props;

  const ranks = ["silver", "gold", "diamond", "other"];
  const fmt = (n) =>
    Number(n || 0).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });

  const [from, setFrom] = React.useState(filters?.from || "");
  const [to, setTo] = React.useState(filters?.to || "");

  const queryUrl = () => {
    const p = new URLSearchParams();
    if (from) p.set("from", from);
    if (to) p.set("to", to);
    const qs = p.toString();
    return qs ? `/income/binary?${qs}` : `/income/binary`;
  };

  return (
    <AuthenticatedLayout>
    <div className="p-6 max-w-6xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Binary Business</h1>
        <div className="text-sm text-gray-500">As of: {asOf}</div>
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
            href={queryUrl()}
            className="inline-flex items-center px-4 py-2 rounded bg-black text-white hover:opacity-90"
          >
            Apply
          </a>
          <Link
            href="/income/binary"
            className="inline-flex items-center px-4 py-2 rounded border hover:bg-gray-50"
          >
            Reset
          </Link>
        </div>
      </div>

      {/* Totals */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div className="bg-white rounded-xl shadow p-5">
          <div className="text-sm text-gray-500">Left Business</div>
          <div className="mt-1 text-2xl font-bold">{fmt(totals?.left)}</div>
        </div>
        <div className="bg-white rounded-xl shadow p-5">
          <div className="text-sm text-gray-500">Right Business</div>
          <div className="mt-1 text-2xl font-bold">{fmt(totals?.right)}</div>
        </div>
      </div>

      {/* Rank-wise matrix */}
      <div className="bg-white rounded-xl shadow overflow-hidden">
        <div className="px-5 py-4 border-b">
          <h2 className="font-semibold">Rank-wise Summary</h2>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-gray-50 text-left">
                <th className="px-5 py-3">Rank</th>
                <th className="px-5 py-3">Left Business</th>
                <th className="px-5 py-3">Right Business</th>
                <th className="px-5 py-3">Total</th>
                <th className="px-5 py-3">Orders L</th>
                <th className="px-5 py-3">Orders R</th>
              </tr>
            </thead>
            <tbody>
              {ranks.map((r) => {
                const row = matrix?.[r] || {};
                const total = (row.left || 0) + (row.right || 0);
                return (
                  <tr key={r} className="border-t">
                    <td className="px-5 py-3 capitalize">{r}</td>
                    <td className="px-5 py-3">{fmt(row.left)}</td>
                    <td className="px-5 py-3">{fmt(row.right)}</td>
                    <td className="px-5 py-3 font-medium">{fmt(total)}</td>
                    <td className="px-5 py-3">{row.orders_left || 0}</td>
                    <td className="px-5 py-3">{row.orders_right || 0}</td>
                  </tr>
                );
              })}
            </tbody>
            <tfoot>
              <tr className="border-t bg-gray-50">
                <td className="px-5 py-3 font-semibold">Grand Total</td>
                <td className="px-5 py-3 font-semibold">{fmt(totals?.left)}</td>
                <td className="px-5 py-3 font-semibold">{fmt(totals?.right)}</td>
                <td className="px-5 py-3 font-semibold">
                  {fmt((totals?.left || 0) + (totals?.right || 0))}
                </td>
                <td className="px-5 py-3"></td>
                <td className="px-5 py-3"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      {/* Recent activity (optional context) */}
      <div className="bg-white rounded-xl shadow overflow-hidden">
        <div className="px-5 py-4 border-b">
          <h2 className="font-semibold">Recent Paid Orders (Last 20)</h2>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-gray-50 text-left">
                <th className="px-5 py-3">Date</th>
                <th className="px-5 py-3">Buyer</th>
                <th className="px-5 py-3">Product</th>
                <th className="px-5 py-3">Rank</th>
                <th className="px-5 py-3">Leg</th>
                <th className="px-5 py-3">Amount</th>
              </tr>
            </thead>
            <tbody>
              {(recent || []).map((r, i) => (
                <tr key={i} className="border-t">
                  <td className="px-5 py-3">{r.created_at}</td>
                  <td className="px-5 py-3">#{r.buyer_id}</td>
                  <td className="px-5 py-3">{r.product}</td>
                  <td className="px-5 py-3 capitalize">{r.type}</td>
                  <td className="px-5 py-3">{r.leg}</td>
                  <td className="px-5 py-3">{fmt(r.amount)}</td>
                </tr>
              ))}
              {!recent?.length && (
                <tr>
                  <td className="px-5 py-6 text-center text-gray-500" colSpan={6}>
                    No data found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
    </AuthenticatedLayout>
  );
}
