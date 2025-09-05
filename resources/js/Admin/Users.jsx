import React from "react";
import { Head, Link, router } from "@inertiajs/react";

export default function Users({ users, filters }) {
  const onSearch = (e) => {
    router.get(
      route("admin.users.index"),
      { search: e.target.value },
      { preserveState: true, replace: true }
    );
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <Head title="Admin • All Users" />

      <header className="sticky top-0 z-10 bg-white/80 backdrop-blur border-b">
        <div className="mx-auto max-w-7xl px-4 py-4 flex items-center justify-between">
          <h1 className="text-xl font-semibold">All Users</h1>
          <input
            type="text"
            placeholder="Search name or email..."
            defaultValue={filters?.search ?? ""}
            onChange={onSearch}
            className="w-72 rounded-xl border px-4 py-2 outline-none focus:ring"
          />
        </div>
      </header>

      <main className="mx-auto max-w-7xl p-4">
        <div className="overflow-hidden rounded-2xl border bg-white shadow-sm">
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-gray-100 text-gray-700">
                <tr className="text-left">
                  <th className="px-4 py-3">ID</th>
                  <th className="px-4 py-3">Name</th>
                  <th className="px-4 py-3">Email</th>
                  <th className="px-4 py-3">Created</th>
                  <th className="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {(users?.data ?? []).map((u) => (
                  <tr key={u.id} className="border-t hover:bg-gray-50">
                    <td className="px-4 py-3">{u.id}</td>
                    <td className="px-4 py-3">{u.name}</td>
                    <td className="px-4 py-3">{u.email}</td>
                    <td className="px-4 py-3">
                      {new Date(u.created_at).toLocaleDateString()}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <button className="rounded-xl border px-3 py-1.5 hover:bg-gray-100">
                        View
                      </button>
                    </td>
                  </tr>
                ))}

                {(users?.data ?? []).length === 0 && (
                  <tr>
                    <td className="px-4 py-8 text-center text-gray-500" colSpan={5}>
                      No users found.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          <div className="flex items-center justify-between p-4">
            <div className="text-xs text-gray-500">
              Showing {users.from ?? 0}–{users.to ?? 0} of {users.total ?? 0}
            </div>
            <div className="flex gap-2">
              {users.links?.map((l, i) => (
                <Link
                  key={i}
                  href={l.url || "#"}
                  dangerouslySetInnerHTML={{ __html: l.label }}
                  className={
                    "rounded-xl border px-3 py-1.5 " +
                    (l.active
                      ? "bg-gray-900 text-white"
                      : l.url
                      ? "hover:bg-gray-100"
                      : "opacity-50 cursor-not-allowed")
                  }
                  onClick={(e) => {
                    if (!l.url) e.preventDefault();
                  }}
                />
              ))}
            </div>
          </div>
        </div>
      </main>
    </div>
  );
}
