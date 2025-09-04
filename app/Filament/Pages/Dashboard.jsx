import React from "react";
import { Head } from "@inertiajs/react";

export default function Dashboard() {
  return (
    <>
      <Head title="Admin Dashboard" />

      <div className="p-6">
        <h1 className="text-2xl font-bold text-gray-800 mb-6">
          Welcome Admin 👋
        </h1>

        {/* Stats Section */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div className="bg-white rounded-xl shadow p-6 text-center">
            <h2 className="text-lg font-semibold">Users</h2>
            <p className="text-3xl font-bold text-indigo-600">120</p>
          </div>

          <div className="bg-white rounded-xl shadow p-6 text-center">
            <h2 className="text-lg font-semibold">Orders</h2>
            <p className="text-3xl font-bold text-green-600">85</p>
          </div>

          <div className="bg-white rounded-xl shadow p-6 text-center">
            <h2 className="text-lg font-semibold">Wallet Balance</h2>
            <p className="text-3xl font-bold text-emerald-600">₹50,000</p>
          </div>
        </div>

        {/* Recent Activity */}
        <div className="mt-8 bg-white rounded-xl shadow p-6">
          <h2 className="text-lg font-semibold mb-4">Recent Activity</h2>
          <ul className="space-y-2 text-gray-700">
            <li>✅ User <b>Yogesh</b> registered</li>
            <li>💰 Payment of ₹5,000 received</li>
            <li>📦 Order #102 shipped</li>
          </ul>
        </div>
      </div>
    </>
  );
}
