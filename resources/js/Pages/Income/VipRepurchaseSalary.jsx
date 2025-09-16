// resources/js/Pages/Income/VipRepurchaseSalary.jsx
import React from "react";
import { Head, router, usePage } from "@inertiajs/react";
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
export default function VipRepurchaseSalary() {
  const page = usePage();
  const server = page.props || {};

  // slabs fallback
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

  // server props (controller से आने चाहिए)
  const placementCombined = server.placement_combined ?? { left: 0, right: 0 };
  const placementSells = server.placement_sells ?? { left: 0, right: 0 };
  const placementRepurchases = server.placement_repurchases ?? { left: 0, right: 0 };

  // carry forward values
  const carry = server.carry ?? { left: 0, right: 0 };

  const left = Number(placementCombined.left || 0);
  const right = Number(placementCombined.right || 0);
  const matched = Math.min(left, right);
  const pending = Math.abs(left - right);

  // Precompute per-slab thresholds
  const volumes = slabs.map((s) => Number(s.volume || 0));
  const prevThreshold = (i) => (i === 0 ? 0 : volumes[i - 1]);

  const currentIdx = volumes.filter((v) => matched >= v).length - 1;
  const next = slabs[currentIdx + 1] || null;

  return (
    <AuthenticatedLayout
      header={
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 className="text-lg sm:text-xl md:text-2xl font-semibold text-gray-900">
              VIP Repurchase Salary
            </h1>
            <p className="text-xs text-gray-500">
              Matching volumes decide your VIP rank & 3-month salary. (Showing <b>Placement combined</b> only)
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
        {/* Placement Combined cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
          <div className="rounded-xl border bg-white p-4 shadow-sm">
            <div className="text-xs text-gray-500">Placement Combined Left</div>
            <div className="mt-2 text-xl font-bold">{formatINR(left)}</div>
            <div className="text-xs text-gray-400 mt-1">
              Sell: {formatINR(placementSells.left)} • Repurchase: {formatINR(placementRepurchases.left)}
            </div>
            <div className="text-xs text-amber-600 mt-1">
              Carry Forward: {formatINR(carry.left)}
            </div>
          </div>

          <div className="rounded-xl border bg-white p-4 shadow-sm">
            <div className="text-xs text-gray-500">Placement Combined Right</div>
            <div className="mt-2 text-xl font-bold">{formatINR(right)}</div>
            <div className="text-xs text-gray-400 mt-1">
              Sell: {formatINR(placementSells.right)} • Repurchase: {formatINR(placementRepurchases.right)}
            </div>
            <div className="text-xs text-amber-600 mt-1">
              Carry Forward: {formatINR(carry.right)}
            </div>
          </div>
        </div>

        {/* Matched + Carry Info */}
        <div className="mb-4">
          <div className="rounded-lg border bg-white p-3 shadow-sm text-sm">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
              <div className="text-gray-700">
                <strong>Matched This Period:</strong> {formatINR(matched)} &nbsp;•&nbsp; 
                <span className="text-gray-500">Pending: {formatINR(pending)}</span>
              </div>

              <div className="text-sm text-gray-600">
                {next ? (
                  <span className="ml-4 text-amber-600">
                    Next slab ({next.rank}) needs: {formatINR(Math.max(0, Number(next.volume) - matched))}
                  </span>
                ) : (
                  <span className="ml-4 text-emerald-600">Max slab reached</span>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Slabs Table (same as before) */}
        {/* ... आपके slabs का वही table रहेगा ... */}

        {/* Note */}
        <div className="mt-4 mb-8 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          <div><b>Note:</b> Placement combined = Sell + Repurchase. Matching = <i>min(Left, Right)</i>. Pending = |Left − Right|.</div>
          <div className="mt-1"><b>Carry Forward:</b> Unmatched volume automatically forwarded to next closing.</div>
          <div className="mt-1"><b>Weekly closing:</b> Salary week is Monday → Sunday. Weekly installments generated on next Monday.</div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}

/* ---------------- small UI helpers ---------------- */
function StatusPill({ achieved = false }) {
  return (
    <span
      className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ${
        achieved ? "bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200" : "bg-gray-100 text-gray-600 ring-1 ring-gray-200"
      }`}
    >
      {achieved ? "Achieved" : "Pending"}
    </span>
  );
}

function Th({ children, className = "" }) {
  return <th className={`px-4 py-3 text-xs font-semibold uppercase tracking-wide ${className}`}>{children}</th>;
}
