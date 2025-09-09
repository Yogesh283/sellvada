import React from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, usePage } from "@inertiajs/react";

const formatINR = (n) => {
  const num = Number(n);
  if (Number.isNaN(num)) return "-";
  return new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR" }).format(num);
};

export default function MyOrders() {
  const { orders = {} } = usePage().props;

  return (
    <AuthenticatedLayout header={<h2 className="text-xl font-semibold">My Orders</h2>}>
      <Head title="My Orders" />

      <div className="max-w-4xl mx-auto p-4">
        <div className="rounded-lg bg-white shadow border overflow-hidden">
          <div className="bg-slate-50 px-4 py-3 font-semibold">My Orders</div>

          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-white border-b">
                <tr className="text-left text-slate-600">
                  <th className="py-3 px-4">Order No</th>
                  <th className="py-3 px-4">Product</th>
                  <th className="py-3 px-4">Amount</th>
                  <th className="py-3 px-4">Status</th>
                  <th className="py-3 px-4">Date</th>
                </tr>
              </thead>

              <tbody>
                {orders.data && orders.data.length ? (
                  orders.data.map((o) => (
                    <tr key={o.id} className="border-b last:border-0">
                      <td className="py-3 px-4 font-medium">{o.id}</td>
                      <td className="py-3 px-4 font-mono text-xs">{o.order_no}</td>
                      <td className="py-3 px-4">{o.product}</td>
                      <td className="py-3 px-4">{formatINR(o.amount)}</td>
                      <td className="py-3 px-4">
                        <span className="inline-flex items-center px-2 py-1 text-xs font-semibold rounded bg-slate-100 text-slate-800">
                          {o.status}
                        </span>
                      </td>
                      <td className="py-3 px-4">{o.created_at ? new Date(o.created_at).toLocaleString() : "-"}</td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={6} className="p-6 text-center text-slate-500">
                      No orders found.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          {/* simple prev/next links */}
          <div className="px-4 py-3 bg-white flex items-center justify-between">
            <div className="text-sm text-slate-600">
              Showing {orders.from ?? 0} to {orders.to ?? 0} of {orders.total ?? 0}
            </div>
            <div className="flex items-center gap-2">
              {orders.prev_page_url ? (
                <a href={orders.prev_page_url} className="px-3 py-1 rounded border text-sm">Prev</a>
              ) : (
                <span className="px-3 py-1 rounded border text-sm opacity-50">Prev</span>
              )}
              {orders.next_page_url ? (
                <a href={orders.next_page_url} className="px-3 py-1 rounded border text-sm">Next</a>
              ) : (
                <span className="px-3 py-1 rounded border text-sm opacity-50">Next</span>
              )}
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
