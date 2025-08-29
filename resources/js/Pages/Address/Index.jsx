import React, { useMemo, useState } from "react";
import { Head, useForm, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

function Row({ a, onEdit, onDefault, onDelete }) {
  return (
    <tr className="border-t">
      <td className="px-3 py-2">{a.id}</td>
      <td className="px-3 py-2">
        <div className="font-medium">{a.name}</div>
        <div className="text-xs text-gray-500">{a.phone}</div>
      </td>
      <td className="px-3 py-2">
        <div>{a.line1}</div>
        {a.line2 && <div>{a.line2}</div>}
        <div className="text-gray-600 text-sm">
          {a.city}, {a.state} {a.pincode}
        </div>
        <div className="text-gray-500 text-xs">{a.country}</div>
      </td>
      <td className="px-3 py-2">
        {a.is_default ? (
          <span className="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">
            Default
          </span>
        ) : (
          <button
            onClick={() => onDefault(a)}
            className="text-indigo-600 text-sm hover:underline"
          >
            Make default
          </button>
        )}
      </td>
      <td className="px-3 py-2 text-right">
        <button
          onClick={() => onEdit(a)}
          className="text-blue-600 text-sm hover:underline mr-4"
        >
          Edit
        </button>
        <button
          onClick={() => onDelete(a)}
          className="text-red-600 text-sm hover:underline"
        >
          Delete
        </button>
      </td>
    </tr>
  );
}

export default function AddressIndex({ addresses = [], countries = [] }) {
  const [editing, setEditing] = useState(null);

  const { data, setData, post, put, processing, reset, errors } = useForm({
    id: null,
    name: "",
    phone: "",
    line1: "",
    line2: "",
    city: "",
    state: "",
    pincode: "",
    country: countries[0] || "India",
    is_default: false,
  });

  const startCreate = () => {
    reset();
    setData("country", countries[0] || "India");
    setEditing("create");
  };

  const startEdit = (a) => {
    setData({
      id: a.id,
      name: a.name ?? "",
      phone: a.phone ?? "",
      line1: a.line1 ?? "",
      line2: a.line2 ?? "",
      city: a.city ?? "",
      state: a.state ?? "",
      pincode: a.pincode ?? "",
      country: a.country ?? "India",
      is_default: !!a.is_default,
    });
    setEditing("edit");
  };

  const closeForm = () => {
    reset();
    setEditing(null);
  };

  const submit = (e) => {
    e.preventDefault();

    if (editing === "edit" && data.id) {
      put(route("address.update", data.id), {
        preserveScroll: true,
        onSuccess: closeForm,
      });
    } else {
      post(route("address.store"), {
        preserveScroll: true,
        onSuccess: closeForm,
      });
    }
  };

  const makeDefault = (a) => {
    router.post(route("address.default", a.id), {}, { preserveScroll: true });
  };

  const remove = (a) => {
    if (!confirm("Delete this address?")) return;
    router.delete(route("address.delete", a.id), { preserveScroll: true });
  };

  const Header = useMemo(
    () => (
      <div className="flex items-center justify-between mb-4">
        <h1 className="text-xl font-semibold">My Addresses</h1>
        <button
          onClick={startCreate}
          className="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-500"
        >
          + Add New Address
        </button>
      </div>
    ),
    []
  );

  return (
    <AuthenticatedLayout header={Header}>
      <Head title="Addresses" />

      <div className="max-w-5xl mx-auto p-4 space-y-6">
        {/* Form (create/edit) */}
        {editing && (
          <div className="bg-white rounded-xl shadow p-5">
            <div className="flex items-center justify-between mb-3">
              <h2 className="text-lg font-semibold">
                {editing === "edit" ? "Edit Address" : "Add Address"}
              </h2>
              <button onClick={closeForm} className="text-gray-600 hover:underline text-sm">
                Close
              </button>
            </div>

            <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium">Name</label>
                <input
                  className="mt-1 w-full rounded-lg border-gray-300"
                  value={data.name}
                  onChange={(e) => setData("name", e.target.value)}
                  required
                />
                {errors.name && <p className="text-red-600 text-xs">{errors.name}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium">Phone</label>
                <input
                  className="mt-1 w-full rounded-lg border-gray-300"
                  value={data.phone}
                  onChange={(e) => setData("phone", e.target.value)}
                  required
                />
                {errors.phone && <p className="text-red-600 text-xs">{errors.phone}</p>}
              </div>

              <div className="md:col-span-2">
                <label className="block text-sm font-medium">Address Line 1</label>
                <input
                  className="mt-1 w-full rounded-lg border-gray-300"
                  value={data.line1}
                  onChange={(e) => setData("line1", e.target.value)}
                  required
                />
                {errors.line1 && <p className="text-red-600 text-xs">{errors.line1}</p>}
              </div>

              <div className="md:col-span-2">
                <label className="block text-sm font-medium">Address Line 2 (optional)</label>
                <input
                  className="mt-1 w-full rounded-lg border-gray-300"
                  value={data.line2}
                  onChange={(e) => setData("line2", e.target.value)}
                />
                {errors.line2 && <p className="text-red-600 text-xs">{errors.line2}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium">City</label>
                <input
                  className="mt-1 w-full rounded-lg border-gray-300"
                  value={data.city}
                  onChange={(e) => setData("city", e.target.value)}
                  required
                />
                {errors.city && <p className="text-red-600 text-xs">{errors.city}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium">State</label>
                <input
                  className="mt-1 w-full rounded-lg border-gray-300"
                  value={data.state}
                  onChange={(e) => setData("state", e.target.value)}
                  required
                />
                {errors.state && <p className="text-red-600 text-xs">{errors.state}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium">Pincode</label>
                <input
                  className="mt-1 w-full rounded-lg border-gray-300"
                  value={data.pincode}
                  onChange={(e) => setData("pincode", e.target.value)}
                  required
                />
                {errors.pincode && <p className="text-red-600 text-xs">{errors.pincode}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium">Country</label>
                <select
                  className="mt-1 w-full rounded-lg border-gray-300"
                  value={data.country}
                  onChange={(e) => setData("country", e.target.value)}
                >
                  {(countries?.length ? countries : ["India"]).map((c) => (
                    <option key={c} value={c}>{c}</option>
                  ))}
                </select>
                {errors.country && <p className="text-red-600 text-xs">{errors.country}</p>}
              </div>

              <div className="md:col-span-2">
                <label className="inline-flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={data.is_default}
                    onChange={(e) => setData("is_default", e.target.checked)}
                  />
                  <span className="text-sm">Set as default</span>
                </label>
              </div>

              <div className="md:col-span-2 pt-2">
                <button
                  type="submit"
                  disabled={processing}
                  className="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold disabled:opacity-50"
                >
                  {processing ? "Saving..." : (editing === "edit" ? "Update Address" : "Add Address")}
                </button>
              </div>
            </form>
          </div>
        )}

        {/* List */}
        <div className="bg-white rounded-xl shadow p-5">
          <h2 className="text-lg font-semibold mb-3">Saved Addresses</h2>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="text-left bg-gray-50">
                  <th className="px-3 py-2">#</th>
                  <th className="px-3 py-2">Contact</th>
                  <th className="px-3 py-2">Address</th>
                  <th className="px-3 py-2">Default</th>
                  <th className="px-3 py-2 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {addresses?.length ? (
                  addresses.map((a) => (
                    <Row
                      key={a.id}
                      a={a}
                      onEdit={startEdit}
                      onDefault={makeDefault}
                      onDelete={remove}
                    />
                  ))
                ) : (
                  <tr>
                    <td className="px-3 py-6 text-center text-gray-500" colSpan="5">
                      No addresses yet.
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
