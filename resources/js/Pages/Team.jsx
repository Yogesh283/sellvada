import React from "react";
import { Head, usePage } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

function CountPill({ label, value }) {
  return (
    <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700">
      <span className="opacity-70">{label}:</span> <span>{value ?? 0}</span>
    </span>
  );
}

function InfoRow({ label, value }) {
  return (
    <div className="flex items-center justify-between py-1 text-sm">
      <span className="text-slate-600">{label}</span>
      <span className="font-medium text-slate-900 break-all">{value ?? "-"}</span>
    </div>
  );
}

function TeamTable({ title, items = [], showLevel = true }) {
  return (
    <div className="rounded-2xl bg-white shadow ring-1 ring-slate-100 p-4 sm:p-6">
      <div className="mb-3 flex items-center justify-between">
        <h3 className="text-lg font-semibold text-slate-900">{title}</h3>
        <span className="text-sm text-slate-600">Count: {items.length}</span>
      </div>

      {items.length === 0 ? (
        <div className="text-sm text-slate-500">No users found.</div>
      ) : (
        <div className="overflow-auto">
          <table className="min-w-full text-sm">
            <thead>
              <tr className="border-b text-left text-slate-500">
                <th className="py-2 pr-3">#</th>
                <th className="py-2 pr-3">User ID</th>
                <th className="py-2 pr-3">Name</th>
                <th className="py-2 pr-3">Email</th>
                <th className="py-2 pr-3">Position</th>
                {showLevel && <th className="py-2 pr-3">Level</th>}
                <th className="py-2 pr-3">Joined</th>
              </tr>
            </thead>
            <tbody>
              {items.map((u, i) => (
                <tr key={`${u.id}-${i}`} className="border-b last:border-0">
                  <td className="py-2 pr-3">{i + 1}</td>
                  <td className="py-2 pr-3">{u.id}</td>
                  <td className="py-2 pr-3 font-medium text-slate-900">{u.name ?? `User ${u.id}`}</td>
                  <td className="py-2 pr-3">{u.email ?? "-"}</td>
                  <td className="py-2 pr-3">{u.position ?? "-"}</td>
                  {showLevel && <td className="py-2 pr-3">L{u.lvl}</td>}
                  <td className="py-2 pr-3">
                    {u.created_at ? new Date(u.created_at).toLocaleDateString() : "-"}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

export default function Team() {
  const {
    auth,
    me = null,
    counts = { total: 0, left: 0, right: 0, direct: 0 },
    team_all = [],
    left = [],
    right = [],
    direct_list = [],
  } = usePage().props;

  return (
    <AuthenticatedLayout>
      <Head title="My Team" />

      <div className="bg-slate-50 min-h-screen">
        <div className="mx-auto max-w-6xl px-4 sm:px-6 py-6 sm:py-8">
          {/* Header */}
          <div className="mb-6">
            <h1 className="text-2xl sm:text-3xl font-bold text-slate-900">My Team</h1>
            <p className="text-slate-600">
              {auth?.user?.name ? `Hello, ${auth.user.name}. ` : ""}See your counts and complete lists.
            </p>
          </div>

          {/* Summary + Profile */}
          <div className="mb-6 rounded-2xl bg-white shadow ring-1 ring-slate-100 p-4 sm:p-5">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <div className="text-lg font-semibold text-slate-900">
                  {me?.name ?? auth?.user?.name ?? "You"}
                </div>
                <div className="text-sm text-slate-600">
                  ID: {me?.id} • Email: {me?.email ?? "-"} • Joined:{" "}
                  {me?.created_at ? new Date(me.created_at).toLocaleDateString() : "-"}
                </div>
                <div className="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 max-w-xl">
                  <InfoRow label="Referral Code" value={me?.referral_code} />
                  <InfoRow label="Upline (referral_id)" value={me?.referral_id} />
                  <InfoRow label="Sponsor ID" value={me?.sponsor_id} />
                  <InfoRow label="Position" value={me?.position ?? "-"} />
                  <InfoRow label="Left Child ID" value={me?.left_user_id} />
                  <InfoRow label="Right Child ID" value={me?.right_user_id} />
                </div>
              </div>
              <div className="flex flex-wrap gap-2">
                <CountPill label="Total" value={counts.total} />
                <CountPill label="Left" value={counts.left} />
                <CountPill label="Right" value={counts.right} />
                <CountPill label="Direct" value={counts.direct} />
              </div>
            </div>
          </div>

          {/* Lists */}
          <div className="grid grid-cols-1 gap-6">
            <TeamTable title="All Team (Left + Right • All Levels)" items={team_all} showLevel />
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <TeamTable title="Left Team (All Levels)" items={left} showLevel />
              <TeamTable title="Right Team (All Levels)" items={right} showLevel />
            </div>
            <TeamTable title="Direct Team (Level 1 Only)" items={direct_list} showLevel={false} />
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
