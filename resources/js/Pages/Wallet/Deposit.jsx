import React, { useMemo, useState, useEffect } from "react";
import { Head, useForm, Link, usePage } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

function formatINR(n) {
  try {
    return new Intl.NumberFormat("en-IN", { maximumFractionDigits: 2 }).format(
      Number(n || 0)
    );
  } catch {
    return n;
  }
}

// Simple modal
function Modal({ open, title, message, type = "info", onClose }) {
  if (!open) return null;
  const color =
    type === "success" ? "text-green-700" :
    type === "error" ? "text-red-700" :
    "text-gray-800";
  const border =
    type === "success" ? "border-green-200" :
    type === "error" ? "border-red-200" :
    "border-gray-200";
  const bg =
    type === "success" ? "bg-green-50" :
    type === "error" ? "bg-red-50" :
    "bg-white";

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className={`w-full max-w-md rounded-xl border ${border} ${bg} shadow-xl`}>
        <div className="px-5 pt-5">
          <h3 className={`text-lg font-semibold ${color}`}>{title}</h3>
        </div>
        <div className="px-5 py-4 text-sm text-gray-700 whitespace-pre-line">
          {Array.isArray(message) ? message.map((m, i) => <div key={i}>{m}</div>) : message}
        </div>
        <div className="px-5 pb-5 flex justify-end">
          <button
            onClick={onClose}
            className="inline-flex items-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800"
          >
            OK
          </button>
        </div>
      </div>
    </div>
  );
}

export default function Deposit({ balance, deposits, methods }) {
  const page = usePage();
  const flash = page.props?.flash || {};
  const [modal, setModal] = useState({
    open: false,
    title: "",
    message: "",
    type: "info",
  });

  const { data, setData, post, processing, reset, errors, clearErrors } = useForm({
    amount: "",
    method: methods?.[0] || "UPI",
    reference: "",
    note: "",
    receipt: null,
  });

  // If your backend uses session()->flash('success'|'error'), show popup automatically
  useEffect(() => {
    if (flash.success) {
      setModal({ open: true, title: "Success", message: flash.success, type: "success" });
    } else if (flash.error) {
      setModal({ open: true, title: "Error", message: flash.error, type: "error" });
    }
  }, [flash.success, flash.error]);

  const disableSubmit = useMemo(() => {
    const amt = Number(data.amount);
    return processing || !amt || amt <= 0;
  }, [processing, data.amount]);

  const submit = (e) => {
    e.preventDefault();
    clearErrors();

    post(route("wallet.deposit"), {
      forceFormData: true,
      onSuccess: () => {
        reset();
        setModal({
          open: true,
          title: "Deposit submitted",
          message:
            "Your deposit request has been submitted successfully. It will be reviewed shortly.",
          type: "success",
        });
      },
      onError: (errs) => {
        // Collect first few errors for the popup
        const list = Object.values(errs || {}).slice(0, 5);
        setModal({
          open: true,
          title: "Please fix the errors",
          message: list.length ? list : "Validation failed.",
          type: "error",
        });
      },
      onFinish: () => {
        // no-op
      },
    });
  };

  return (
    <AuthenticatedLayout>
      <Head title="Deposit" />

      {/* Popup */}
      <Modal
        open={modal.open}
        title={modal.title}
        message={modal.message}
        type={modal.type}
        onClose={() => setModal((m) => ({ ...m, open: false }))}
      />

      <div className="max-w-4xl mx-auto p-4 space-y-6">
        {/* Balance */}
        <div className="bg-white rounded-xl shadow p-5 flex items-center justify-between">
          <div>
            <h2 className="text-lg font-semibold">Wallet Balance</h2>
            <p className="text-3xl font-bold mt-1">₹ {formatINR(balance)}</p>
          </div>
          <Link href="/dashboard" className="text-indigo-600 hover:underline text-sm">
            ← Back to Dashboard
          </Link>
        </div>

        {/* Deposit form */}
        <form
          onSubmit={submit}
          className="bg-white rounded-xl shadow p-6 space-y-4"
          encType="multipart/form-data"
        >
          <h3 className="text-lg font-semibold">New Deposit Request</h3>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium">Amount (INR)</label>
              <input
                type="number"
                min="1"
                step="0.01"
                className="mt-1 w-full rounded-lg border-gray-300"
                value={data.amount}
                onChange={(e) => setData("amount", e.target.value)}
                required
              />
              {errors.amount && (
                <p className="text-red-600 text-xs mt-1">{errors.amount}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium">Method</label>
              <select
                className="mt-1 w-full rounded-lg border-gray-300"
                value={data.method}
                onChange={(e) => setData("method", e.target.value)}
              >
                {(methods || ["UPI", "Bank", "Cash"]).map((m) => (
                  <option key={m} value={m}>
                    {m}
                  </option>
                ))}
              </select>
              {errors.method && (
                <p className="text-red-600 text-xs mt-1">{errors.method}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium">
                Reference / Txn ID (optional)
              </label>
              <input
                type="text"
                className="mt-1 w-full rounded-lg border-gray-300"
                value={data.reference}
                onChange={(e) => setData("reference", e.target.value)}
              />
              {errors.reference && (
                <p className="text-red-600 text-xs mt-1">{errors.reference}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium">Receipt (image, optional)</label>
              <input
                type="file"
                accept="image/*"
                className="mt-1 w-full"
                onChange={(e) => setData("receipt", e.target.files?.[0] || null)}
              />
              {errors.receipt && (
                <p className="text-red-600 text-xs mt-1">{errors.receipt}</p>
              )}
            </div>

            <div className="md:col-span-2">
              <label className="block text-sm font-medium">Note (optional)</label>
              <textarea
                rows="2"
                className="mt-1 w-full rounded-lg border-gray-300"
                value={data.note}
                onChange={(e) => setData("note", e.target.value)}
              />
              {errors.note && (
                <p className="text-red-600 text-xs mt-1">{errors.note}</p>
              )}
            </div>
          </div>

          <div className="pt-2">
            <button
              type="submit"
              disabled={disableSubmit}
              className="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold disabled:opacity-50"
            >
              {processing ? "Submitting..." : "Submit Deposit"}
            </button>
          </div>
        </form>

        {/* History */}
        <div className="bg-white rounded-xl shadow p-6">
          <h3 className="text-lg font-semibold mb-3">Recent Deposits</h3>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="text-left bg-gray-50">
                  <th className="px-3 py-2">#</th>
                  <th className="px-3 py-2">Date</th>
                  <th className="px-3 py-2">Amount</th>
                  <th className="px-3 py-2">Method</th>
                  <th className="px-3 py-2">Reference</th>
                  <th className="px-3 py-2">Status</th>
                  <th className="px-3 py-2">Receipt</th>
                </tr>
              </thead>
              <tbody>
                {deposits?.length ? (
                  deposits.map((d) => (
                    <tr key={d.id} className="border-t">
                      <td className="px-3 py-2">{d.id}</td>
                      <td className="px-3 py-2">
                        {new Date(d.created_at).toLocaleString()}
                      </td>
                      <td className="px-3 py-2 font-semibold">₹ {formatINR(d.amount)}</td>
                      <td className="px-3 py-2">{d.method}</td>
                      <td className="px-3 py-2">{d.reference || "-"}</td>
                      <td className="px-3 py-2">
                        <span
                          className={
                            d.status === "approved"
                              ? "text-green-600"
                              : d.status === "rejected"
                              ? "text-red-600"
                              : "text-yellow-700"
                          }
                        >
                          {d.status}
                        </span>
                      </td>
                      <td className="px-3 py-2">
                        {d.receipt_path ? (
                          <a
                            href={`/storage/${d.receipt_path}`}
                            className="text-indigo-600 hover:underline"
                            target="_blank"
                            rel="noreferrer"
                          >
                            View
                          </a>
                        ) : (
                          "—"
                        )}
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td className="px-3 py-6 text-center text-gray-500" colSpan="7">
                      No deposits yet.
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
