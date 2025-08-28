import React from "react";
import { Head, usePage } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/* ---------- helpers ---------- */
const INR = (n) =>
  Number(n ?? 0).toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const DT = (s) => (s ? new Date(s).toLocaleString() : "-");
const DMON = (s) => (s ? new Date(s).toLocaleDateString(undefined, { year: "numeric", month: "short" }) : "-");

/* ---------- UI bits (white theme) ---------- */
const Card = ({ title, value }) => (
  <div className="rounded-2xl border border-slate-200 bg-white p-4">
    <div className="text-slate-500 text-sm">{title}</div>
    <div className="mt-1 text-2xl font-semibold text-slate-900">{value}</div>
  </div>
);

const Box = ({ title, children }) => (
  <div className="rounded-2xl border border-slate-200 bg-white overflow-hidden">
    <div className="px-4 py-3 border-b border-slate-200 font-semibold text-slate-800">{title}</div>
    <div className="p-4">{children}</div>
  </div>
);

const Th = ({ children, className = "" }) => (
  <th className={`px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600 ${className}`}>{children}</th>
);
const Td = ({ children, className = "" }) => <td className={`px-4 py-3 text-sm text-slate-800 ${className}`}>{children}</td>;

/* ---------- Page ---------- */
export default function Overview() {
  const { star = {}, repurchase_salary = {} } = usePage().props;

  return (
    <AuthenticatedLayout header={<h2 className="font-semibold text-xl text-slate-800">My Income</h2>}>
      <Head title="Income" />
      <div className="py-6">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

          {/* Top stats */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Card title="Star Income (Total Paid)" value={`₹ ${INR(star.total_paid)}`} />
            <Card title="Repurchase Salary (Total Paid)" value={`₹ ${INR(repurchase_salary.total_paid)}`} />
            <Card
              title="Next Repurchase Salary Due"
              value={
                repurchase_salary.next_due
                  ? `${DMON(repurchase_salary.next_due.month)} — ₹ ${INR(repurchase_salary.next_due.amount)}`
                  : "—"
              }
            />
          </div>

          {/* Star awards */}
          <Box title="Star Awards">
            {!star.awards || star.awards.length === 0 ? (
              <div className="text-slate-600 text-sm">No star awards yet.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200">
                  <thead className="bg-slate-50">
                    <tr>
                      <Th>Rank</Th>
                      <Th>Reward (₹)</Th>
                      <Th>Awarded At</Th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {star.awards.map((a, idx) => (
                      <tr key={idx} className="hover:bg-slate-50">
                        <Td className="font-semibold">VIP {a.rank_no}</Td>
                        <Td>₹ {INR(a.reward_amount)}</Td>
                        <Td>{DT(a.awarded_at)}</Td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Box>

          {/* Repurchase salary: pending + history */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <Box title="Pending Repurchase Salary (Next Months)">
              {!repurchase_salary.pending || repurchase_salary.pending.length === 0 ? (
                <div className="text-slate-600 text-sm">No pending installments.</div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-slate-200">
                    <thead className="bg-slate-50">
                      <tr>
                        <Th>Due Month</Th>
                        <Th>VIP</Th>
                        <Th>For Period</Th>
                        <Th className="text-right">Amount (₹)</Th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {repurchase_salary.pending.map((r) => (
                        <tr key={r.id} className="hover:bg-slate-50">
                          <Td>{DMON(r.due_month)}</Td>
                          <Td className="font-semibold">VIP {r.vip_no}</Td>
                          <Td>{DMON(r.period_month)}</Td>
                          <Td className="text-right">₹ {INR(r.amount)}</Td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </Box>

            <Box title="Repurchase Salary — Last 12 Months">
              {!repurchase_salary.history || repurchase_salary.history.length === 0 ? (
                <div className="text-slate-600 text-sm">No history.</div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-slate-200">
                    <thead className="bg-slate-50">
                      <tr>
                        <Th>Due Month</Th>
                        <Th>VIP</Th>
                        <Th>For Period</Th>
                        <Th>Status</Th>
                        <Th className="text-right">Amount (₹)</Th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {repurchase_salary.history.map((h, i) => (
                        <tr key={i} className="hover:bg-slate-50">
                          <Td>{DMON(h.due_month)}</Td>
                          <Td>VIP {h.vip_no}</Td>
                          <Td>{DMON(h.period_month)}</Td>
                          <Td>{h.paid_at ? "Paid" : "Unpaid"}</Td>
                          <Td className="text-right">₹ {INR(h.amount)}</Td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </Box>
          </div>

        </div>
      </div>
    </AuthenticatedLayout>
  );
}
