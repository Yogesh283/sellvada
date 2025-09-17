import React, { useMemo } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

function Badge({ status }) {
  const base = "px-2 py-0.5 text-xs border rounded-full";
  const color =
    status === "paid"
      ? "bg-emerald-50 text-emerald-700 border-emerald-300"
      : status === "pending"
      ? "bg-amber-50 text-amber-700 border-amber-300"
      : "bg-rose-50 text-rose-700 border-rose-300";
  return <span className={`${base} ${color}`}>{status}</span>;
}

function formatINR(n) {
  const num = typeof n === "number" ? n : parseFloat(n ?? 0);
  return num.toLocaleString("en-IN", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

export default function Payouts() {
  const { payouts, stats, filters } = usePage().props;

  const rows = payouts?.data ?? [];
  const links = payouts?.links ?? [];

  return (
    <AuthenticatedLayout
      header={<h2 className="font-semibold text-xl text-slate-800">Payouts</h2>}
    >
      <Head title="Payouts" />

      <div className="py-6">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
          {/* Stats */}
          <div className="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Card title="Total Paid" value={`₹ ${formatINR(stats.sumPaid)}`} />
            <Card title="Pending" value={`₹ ${formatINR(stats.sumPending)}`} />
            <Card title="Today Paid" value={`₹ ${formatINR(stats.todayPaid)}`} />
            <Card
              title="This Month Paid"
              value={`₹ ${formatINR(stats.monthPaid)}`}
            />
          </div>

          {/* Filters */}
          <div className="flex flex-wrap items-center gap-2 text-sm">
            <FilterLink
              label="All"
              qs={{}}
              active={!filters.status && !filters.method && !filters.type}
            />
            <FilterLink
              label="Paid"
              qs={{ status: "paid" }}
              active={filters.status === "paid"}
            />
            <FilterLink
              label="Pending"
              qs={{ status: "pending" }}
              active={filters.status === "pending"}
            />
            <span className="mx-2 h-5 w-px bg-slate-200 hidden sm:block" />
            <FilterLink
              label="Closing 1"
              qs={{ method: "closing_1" }}
              active={filters.method === "closing_1"}
            />
            <FilterLink
              label="Closing 2"
              qs={{ method: "closing_2" }}
              active={filters.method === "closing_2"}
            />
            <span className="mx-2 h-5 w-px bg-slate-200 hidden sm:block" />
            <FilterLink
              label="Binary Matching"
              qs={{ type: "binary_matching" }}
              active={filters.type === "binary_matching"}
            />
          </div>

          {/* Table (Desktop) */}
          <div className="hidden sm:block overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <table className="min-w-full divide-y divide-slate-200">
              <thead className="bg-slate-50">
                <tr>
                  <Th>Date</Th>
                  <Th>Type</Th>
                  <Th>Method</Th>
                  <Th>Status</Th>
                  <Th className="text-right">Amount (₹)</Th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {rows.length === 0 ? (
                  <tr>
                    <td
                      colSpan={5}
                      className="py-10 text-center text-slate-500"
                    >
                      No payouts found.
                    </td>
                  </tr>
                ) : (
                  rows.map((r) => (
                    <tr key={r.id} className="hover:bg-slate-50">
                      <Td>{new Date(r.created_at).toLocaleString()}</Td>
                      <Td className="capitalize">
                        {r.type?.replaceAll("_", " ") || "-"}
                      </Td>
                      <Td className="capitalize">
                        {r.method?.replaceAll("_", " ") || "-"}
                      </Td>
                      <Td>
                        <Badge status={r.status} />
                      </Td>
                      <Td className="text-right font-medium">
                        ₹ {formatINR(r.amount)}
                      </Td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {/* Card List (Mobile) */}
          <div className="grid sm:hidden gap-3">
            {rows.length === 0 ? (
              <div className="text-center text-slate-500 py-6 bg-white rounded-lg border">
                No payouts found.
              </div>
            ) : (
              rows.map((r) => (
                <div
                  key={r.id}
                  className="bg-white rounded-xl border p-4 shadow-sm space-y-2"
                >
                  <div className="flex justify-between text-sm text-slate-500">
                    <span>{new Date(r.created_at).toLocaleDateString()}</span>
                    <Badge status={r.status} />
                  </div>
                  <div className="text-slate-800 font-medium capitalize">
                    {r.type?.replaceAll("_", " ") || "-"}
                  </div>
                  <div className="text-xs text-slate-500 capitalize">
                    Method: {r.method?.replaceAll("_", " ") || "-"}
                  </div>
                  <div className="text-lg font-bold text-right text-emerald-700">
                    ₹ {formatINR(r.amount)}
                  </div>
                </div>
              ))
            )}
          </div>

          {/* Pagination */}
          <div className="flex items-center justify-end gap-2 flex-wrap">
            {links.map((l, i) => (
              <Link
                key={i}
                href={l.url || "#"}
                preserveScroll
                preserveState
                className={`px-3 py-1 rounded-md border border-slate-200 text-sm ${
                  l.active
                    ? "bg-slate-100 text-slate-800"
                    : "text-slate-600 hover:text-slate-800 hover:bg-slate-50"
                } ${!l.url ? "opacity-50 pointer-events-none" : ""}`}
                dangerouslySetInnerHTML={{ __html: l.label }}
              />
            ))}
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}

function Card({ title, value }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-4 text-center sm:text-left">
      <div className="text-slate-500 text-sm">{title}</div>
      <div className="mt-1 text-lg sm:text-2xl font-semibold text-slate-900">
        {value}
      </div>
    </div>
  );
}
function Th({ children, className = "" }) {
  return (
    <th
      className={`px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600 ${className}`}
    >
      {children}
    </th>
  );
}
function Td({ children, className = "" }) {
  return (
    <td className={`px-4 py-3 text-sm text-slate-800 ${className}`}>
      {children}
    </td>
  );
}
function FilterLink({ label, qs, active }) {
  const href = useMemo(() => {
    const url = new URL(window.location.href);
    if ("status" in qs) url.searchParams.set("status", qs.status);
    else url.searchParams.delete("status");
    if ("method" in qs) url.searchParams.set("method", qs.method);
    else url.searchParams.delete("method");
    if ("type" in qs) url.searchParams.set("type", qs.type);
    else url.searchParams.delete("type");
    const search = url.searchParams.toString();
    return url.pathname + (search ? "?" + search : "");
  }, [qs]);

  return (
    <Link
      href={href}
      preserveScroll
      preserveState
      className={`px-3 py-1.5 rounded-full text-sm border ${
        active
          ? "border-slate-300 bg-slate-100 text-slate-900"
          : "border-slate-200 text-slate-600 hover:text-slate-800 hover:bg-slate-50"
      }`}
    >
      {label}
    </Link>
  );
}
