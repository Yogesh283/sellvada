// resources/js/Pages/Income/Binary.jsx
import React from "react";
import { usePage, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/* ---------------- helpers ---------------- */
const fmtAmt = (n) =>
  Number(n || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

// RANKS now includes 'starter' as requested
const RANKS = ["starter", "silver", "gold", "diamond"];

const rankTone = {
  starter: "bg-red-100 text-red-800 ring-red-200",
  silver: "bg-gray-100 text-gray-700 ring-gray-200",
  gold: "bg-amber-100 text-amber-800 ring-amber-200",
  diamond: "bg-emerald-100 text-emerald-800 ring-emerald-200",
  other: "bg-slate-100 text-slate-700 ring-slate-200",
};

function RankPill({ rank }) {
  const tone = rankTone[rank] || rankTone.other;
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 capitalize ${tone}`}
    >
      {rank}
    </span>
  );
}

/** Colored KPI (left/right tones) */
function StatCard({ label, value, tone = "slate" }) {
  const map = {
    left: {
      ring: "ring-emerald-200",
      border: "border-emerald-200",
      bg: "bg-gradient-to-br from-emerald-50 to-white",
      text: "text-emerald-700",
      chip: "bg-emerald-100 text-emerald-700 ring-emerald-200",
    },
    right: {
      ring: "ring-sky-200",
      border: "border-sky-200",
      bg: "bg-gradient-to-br from-sky-50 to-white",
      text: "text-sky-700",
      chip: "bg-sky-100 text-sky-700 ring-sky-200",
    },
    slate: {
      ring: "ring-gray-200",
      border: "border-gray-200",
      bg: "bg-white",
      text: "text-gray-900",
      chip: "bg-gray-100 text-gray-700 ring-gray-200",
    },
  }[tone] || map?.slate;

  return (
    <div
      className={`rounded-2xl ${map.bg} border ${map.border} p-5 shadow-sm ring-1 ${map.ring}`}
    >
      <div className="text-[11px] uppercase tracking-wide text-gray-500"> {label} </div>
      <div className={`mt-1 text-2xl font-semibold tabular-nums ${map.text}`}>
        ₹{fmtAmt(value)}
      </div>
    </div>
  );
}

/** Small colored pill for amounts in table cells */
function AmountPill({ value, side }) {
  const tone =
    side === "L"
      ? "bg-emerald-50 text-emerald-700 ring-emerald-200"
      : side === "R"
      ? "bg-sky-50 text-sky-700 ring-sky-200"
      : "bg-gray-50 text-gray-700 ring-gray-200";
  return (
    <span className={`inline-flex rounded-full px-2 py-1 text-xs font-semibold tabular-nums ring-1 ${tone}`}>
      ₹{fmtAmt(value)}
    </span>
  );
}

export default function Binary() {
  // props from controller
  const { asOf, filters, totals = {}, matrix = {}, recent = [] } = usePage().props;

  const [from, setFrom] = React.useState(filters?.from || "");
  const [to, setTo] = React.useState(filters?.to || "");

  const queryUrl = () => {
    const p = new URLSearchParams();
    if (from) p.set("from", from);
    if (to) p.set("to", to);
    const qs = p.toString();
    return qs ? `/income/binary?${qs}` : `/income/binary`;
  };

  // grand totals calculated from matrix as fallback (keeps previous behavior)
  const grand = RANKS.reduce(
    (g, r) => {
      const row = matrix?.[r] || {};
      g.oL += row.orders_left || 0;
      g.oR += row.orders_right || 0;
      g.aL += row.left || 0;
      g.aR += row.right || 0;
      g.m += row.matched || 0;
      g.cfL += row.cf_left || 0;
      g.cfR += row.cf_right || 0;
      return g;
    },
    { oL: 0, oR: 0, aL: 0, aR: 0, m: 0, cfL: 0, cfR: 0 }
  );

  // Prefer controller-provided totals (authoritative left/right team business)
  const leftBusiness = totals?.left ?? grand.aL;
  const rightBusiness = totals?.right ?? grand.aR;

  return (
    <AuthenticatedLayout
      header={
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 className="text-lg sm:text-xl md:text-2xl font-semibold text-gray-900">Fresh Matching</h1>
            <p className="text-xs text-gray-500">
              Rank-wise left/right business, matched volume & carry forward.
            </p>
          </div>
          <div className="text-xs sm:text-sm text-gray-500">
            As of: <span className="font-medium">{asOf}</span>
          </div>
        </div>
      }
    >
      <div className="mx-auto max-w-6xl px-3 sm:px-6 py-6 space-y-6">
        {/* Filters */}
        <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
          <div className="flex flex-wrap items-end gap-3">
            <div>
              <label className="block text-xs text-gray-500 mb-1">From</label>
              <input
                type="date"
                value={from}
                onChange={(e) => setFrom(e.target.value)}
                className="w-[170px] rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
              />
            </div>
            <div>
              <label className="block text-xs text-gray-500 mb-1">To</label>
              <input
                type="date"
                value={to}
                onChange={(e) => setTo(e.target.value)}
                className="w-[170px] rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
              />
            </div>
            <a
              href={queryUrl()}
              className="inline-flex items-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-90"
            >
              Apply
            </a>
            <Link
              href="/income/binary"
              className="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
            >
              Reset
            </Link>
          </div>
        </div>

        {/* KPI cards with colors */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <StatCard label="Left Business" value={leftBusiness} tone="left" />
          <StatCard label="Right Business" value={rightBusiness} tone="right" />
        </div>

        {/* Rank-wise table */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
          <div className="flex items-center justify-between border-b px-5 py-4">
            <h2 className="font-semibold text-gray-900">Binary-wise Summary</h2>
            <div className="hidden sm:flex items-center gap-2 text-xs">
              {RANKS.map((r) => (
                <RankPill key={r} rank={r} />
              ))}
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="sticky top-0 z-10">
                <tr className="bg-gray-50/80 backdrop-blur text-left text-gray-600">
                  <th className="px-5 py-3 w-36">Rank</th>
                  <th className="px-5 py-3">Left (Orders • Amount)</th>
                  <th className="px-5 py-3">Right (Orders • Amount)</th>
                  <th className="px-5 py-3">Matched (Amount)</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {RANKS.map((r, i) => {
                  const row = matrix?.[r] || {};
                  const zebra = i % 2 ? "bg-gray-50/60" : "bg-white";
                  return (
                    <tr key={r} className={`transition-colors hover:bg-gray-50 ${zebra}`}>
                      <td className="px-5 py-3 capitalize">
                        <RankPill rank={r} />
                      </td>

                      {/* LEFT cell with green accents */}
                      <td className="px-5 py-3">
                        <div className="text-emerald-700/80 text-xs">
                          Orders: <span className="font-medium">{row.orders_left || 0}</span>
                        </div>
                        <div className="mt-1">
                          <AmountPill side="L" value={row.left} />
                        </div>
                      </td>

                      {/* RIGHT cell with blue accents */}
                      <td className="px-5 py-3">
                        <div className="text-sky-700/80 text-xs">
                          Orders: <span className="font-medium">{row.orders_right || 0}</span>
                        </div>
                        <div className="mt-1">
                          <AmountPill side="R" value={row.right} />
                        </div>
                      </td>

                      <td className="px-5 py-3 font-semibold tabular-nums">₹{fmtAmt(row.matched)}</td>
                    </tr>
                  );
                })}
              </tbody>

              <tfoot>
                <tr className="border-t bg-gray-50/80">
                  <td className="px-5 py-3 font-semibold">Grand Total</td>
                  <td className="px-5 py-3 font-semibold">
                    <div className="text-emerald-700/80 text-xs">
                      Orders: <span className="font-medium">{grand.oL}</span>
                    </div>
                    <div className="mt-1">
                      <AmountPill side="L" value={grand.aL} />
                    </div>
                  </td>
                  <td className="px-5 py-3 font-semibold">
                    <div className="text-sky-700/80 text-xs">
                      Orders: <span className="font-medium">{grand.oR}</span>
                    </div>
                    <div className="mt-1">
                      <AmountPill side="R" value={grand.aR} />
                    </div>
                  </td>
                  <td className="px-5 py-3 font-semibold tabular-nums">₹{fmtAmt(grand.m)}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        {/* Recent orders */}
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
          <div className="px-5 py-4 border-b">
            <h2 className="font-semibold text-gray-900">Recent Paid Orders (Last 20)</h2>
          </div>

          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="sticky top-0 z-10">
                <tr className="bg-gray-50/80 backdrop-blur text-left text-gray-600">
                  <th className="px-5 py-3">Date</th>
                  <th className="px-5 py-3">Buyer</th>
                  <th className="px-5 py-3">Product</th>
                  <th className="px-5 py-3">Rank</th>
                  <th className="px-5 py-3">Leg</th>
                  <th className="px-5 py-3">Amount</th>
                </tr>
              </thead>

              <tbody className="divide-y divide-gray-100">
                {(recent || []).map((r, i) => (
                  <tr key={i} className="transition-colors hover:bg-gray-50">
                    <td className="px-5 py-3 text-gray-700">{r.created_at}</td>
                    <td className="px-5 py-3 text-gray-800">#{r.buyer_id}</td>
                    <td className="px-5 py-3">{r.product}</td>
                    <td className="px-5 py-3 capitalize">
                      <RankPill rank={String(r.type || "other").toLowerCase()} />
                    </td>
                    <td className="px-5 py-3 font-semibold">
                      <span
                        className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ${
                          r.leg === "L"
                            ? "bg-emerald-50 text-emerald-700 ring-emerald-200"
                            : r.leg === "R"
                            ? "bg-sky-50 text-sky-700 ring-sky-200"
                            : "bg-gray-50 text-gray-700 ring-gray-200"
                        }`}
                      >
                        {r.leg}
                      </span>
                    </td>
                    <td className="px-5 py-3 font-semibold tabular-nums">₹{fmtAmt(r.amount)}</td>
                  </tr>
                ))}

                {!recent?.length && (
                  <tr>
                    <td className="px-5 py-10 text-center text-gray-500" colSpan={6}>
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
