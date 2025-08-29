// resources/js/Pages/Income/VipRepurchaseSalary.jsx
import React from "react";
import { Head, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";


/** INR helpers */
function formatINR(n) {
  const num = Number(n || 0);
  return new Intl.NumberFormat("en-IN", {
    style: "currency",
    currency: "INR",
    maximumFractionDigits: 0,
  }).format(num);
}
function formatINRCompact(n) {
  const num = Number(n || 0);
  if (num >= 1_00_00_000) return `${(num / 1_00_00_000).toFixed(2)} Cr`;
  if (num >= 1_00_000) return `${(num / 1_00_000).toFixed(2)} Lac`;
  if (num >= 1_000) return `${(num / 1_000).toFixed(2)} K`;
  return `${num}`;
}

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

  const achieved = summary?.achieved_rank;

  return (
  <AuthenticatedLayout>

 
      <Head title="VIP Repurchase Salary" />
      <div className="mx-auto max-w-6xl px-4 sm:px-6 py-6">
        {/* Header */}
        <div className="flex flex-col md:flex-row md:items-end gap-4 md:gap-6 mb-6">
          <div className="flex-1">
            <h1 className="text-2xl sm:text-3xl font-semibold tracking-tight">
              VIP Repurchase Salary
            </h1>
            
          </div>

          <div className="flex items-center gap-3">
            <label className="text-sm text-gray-600">Month</label>
            <input
              type="month"
              value={m}
              onChange={onMonthChange}
              className="border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
        </div>

        {/* Summary cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
          <Card title="Left Volume (Monthly)">
            <div className="text-xl font-semibold">{formatINR(summary?.left || 0)}</div>
            <div className="text-xs text-gray-500">
              ({formatINRCompact(summary?.left || 0)})
            </div>
          </Card>

          <Card title="Right Volume (Monthly)">
            <div className="text-xl font-semibold">{formatINR(summary?.right || 0)}</div>
            <div className="text-xs text-gray-500">
              ({formatINRCompact(summary?.right || 0)})
            </div>
          </Card>

          <Card title="Matched Volume (Monthly)">
            <div className="text-xl font-semibold">{formatINR(summary?.matched || 0)}</div>
            <div className="text-xs text-gray-500">
              min(Left, Right)
            </div>
          </Card>

          <Card title="Paid This Month">
            <div className="text-xl font-semibold">
              {formatINR(summary?.paid_this_month || 0)}
            </div>
            {summary?.due && !summary?.due?.paid_at && (
              <div className="mt-1 text-xs text-amber-600">
                Due: {formatINR(summary?.due?.amount)} (installment)
              </div>
            )}
          </Card>
        </div>

        {/* Achieved banner */}
        <div className="mb-6">
          {achieved ? (
            <div className="rounded-xl border bg-green-50 text-green-800 px-4 py-3">
              🎉 Congratulations! You achieved <b>{achieved}</b> in {m}.
            </div>
          ) : (
            <div className="rounded-xl border bg-gray-50 text-gray-700 px-4 py-3">
              Current month achieved rank: <b>—</b>
            </div>
          )}
        </div>

        {/* Table */}
        <div className="overflow-x-auto rounded-xl border bg-white">
          <table className="min-w-full text-sm">
            <thead>
              <tr className="bg-indigo-600 text-white">
                <Th className="w-32 text-left rounded-tl-xl">Rank</Th>
                <Th className="text-left">Monthly Matching Volume</Th>
                <Th className="text-left rounded-tr-xl">Salary (3 Months)</Th>
              </tr>
            </thead>
            <tbody>
              {slabs.map((s, idx) => {
                const hit = summary?.matched >= s.volume;
                return (
                  <tr
                    key={idx}
                    className={
                      "border-t " +
                      (hit
                        ? "bg-emerald-50/70"
                        : idx % 2
                        ? "bg-gray-50"
                        : "bg-white")
                    }
                  >
                    <Td className="font-semibold">{s.rank}</Td>
                    <Td>
                      {formatINR(s.volume)}{" "}
                      <span className="text-gray-500">
                        ({formatINRCompact(s.volume)})
                      </span>
                    </Td>
                    <Td className="font-semibold">{formatINR(s.salary)}</Td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        {/* Note */}
      
      </div>
     </AuthenticatedLayout>
  );
}

/** small presentational helpers */
function Card({ title, children }) {
  return (
    <div className="rounded-xl border bg-white px-4 py-3 shadow-sm">
      <div className="text-xs uppercase tracking-wide text-gray-500">{title}</div>
      <div className="mt-1">{children}</div>
    </div>
  );
}
function Th({ children, className = "" }) {
  return (
    <th className={`px-4 py-3 text-xs font-semibold uppercase tracking-wide ${className}`}>
      {children}
    </th>
  );
}
function Td({ children, className = "" }) {
  return <td className={`px-4 py-3 ${className}`}>{children}</td>;
}
