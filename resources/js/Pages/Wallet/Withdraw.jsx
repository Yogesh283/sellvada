import React, { useState, useEffect } from "react";
import { Head, useForm } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

function inr(n) {
  try {
    return new Intl.NumberFormat("en-IN", { maximumFractionDigits: 2 }).format(Number(n || 0));
  } catch {
    return n;
  }
}

export default function Withdraw({ balance: initialBalance, rows, methods, minAmt = 200, chargePct = 0, chargeFix = 0 }) {
  // local balance state so we can optimistically deduct
  const [localBalance, setLocalBalance] = useState(Number(initialBalance || 0));
  const [method, setMethod] = useState(methods?.[0] || "UPI");
  const [clientError, setClientError] = useState(""); // for client-side messages / restore info

  const { data, setData, post, processing, reset, errors, clearErrors } = useForm({
    amount: "",
    method: method,
    upi_id: "",
    account_name: "",
    bank_name: "",
    account_no: "",
    ifsc: "",
  });

  // keep form method in sync when method changes
  useEffect(() => {
    setData("method", method);
  }, [method, setData]);

  // Update local balance if prop changes externally
  useEffect(() => {
    setLocalBalance(Number(initialBalance || 0));
  }, [initialBalance]);

  const onMethod = (m) => { 
    setMethod(m); 
    setData("method", m); 
  };

  const estCharge = () => {
    const amt = Number(data.amount || 0);
    return Math.max(0, (amt * Number(chargePct || 0) / 100) + Number(chargeFix || 0));
  };
  const estNet = () => Math.max(0, Number(data.amount || 0) - estCharge());

  const validateBeforeSubmit = () => {
    setClientError("");
    clearErrors();
    const amt = Number(data.amount || 0);

    if (!amt || amt <= 0) {
      setClientError("Please enter a valid amount.");
      return false;
    }
    if (amt < Number(minAmt || 0)) {
      setClientError(`Minimum withdrawal amount is ₹${inr(minAmt)}.`);
      return false;
    }
    if (amt > localBalance) {
      setClientError("Insufficient wallet balance for this withdrawal.");
      return false;
    }
    // additional client-side checks (optional)
    return true;
  };

  const submit = (e) => {
    e.preventDefault();
    // client-side validation
    if (!validateBeforeSubmit()) return;

    const amt = Number(data.amount || 0);
    const prevBalance = localBalance;

    // Optimistic update: deduct immediately from UI
    setLocalBalance((b) => Number((b - amt).toFixed(2)));

    // send request
    post(route("wallet.withdraw.store"), {
      onSuccess: () => {
        // success: keep deduction; reset the form fields
        reset();
        setClientError("");
      },
      onError: (serverErrs) => {
        // restore previous balance on error
        setLocalBalance(prevBalance);
        // show server validation errors if provided; use the first few as client error too
        const list = Object.values(serverErrs || {});
        if (list.length) {
          setClientError(Array.isArray(list) ? list.slice(0,3).join(" | ") : String(list));
        } else {
          setClientError("Server error occurred. Please try again.");
        }
      },
      onFinish: () => {
        // no-op (processing will be handled by inertia useForm)
      },
    });
  };

  return (
    <AuthenticatedLayout
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Withdraw</h2>}
    >
      <Head title="Withdraw" />

      <div className="max-w-5xl mx-auto p-4 space-y-6">
        <div className="bg-white rounded-xl shadow p-5 flex items-center justify-between">
          <div>
            <div className="text-sm text-gray-500">Wallet Balance</div>
            <div className="text-3xl font-bold">₹ {inr(localBalance)}</div>
          </div>
        </div>

        <form onSubmit={submit} className="bg-white rounded-xl shadow p-6 space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium">Amount (₹)</label>
              <input
                type="number"
                min={minAmt}
                step="0.01"
                value={data.amount}
                onChange={(e) => {
                  // clear client error as user types
                  setClientError("");
                  setData("amount", e.target.value);
                }}
                className="mt-1 w-full rounded-lg border-gray-300"
                required
              />
              <p className="text-xs text-gray-500 mt-1">Minimum ₹{inr(minAmt)}</p>
              {errors.amount && <p className="text-red-600 text-xs mt-1">{errors.amount}</p>}
            </div>

            <div>
              <label className="block text-sm font-medium">Method</label>
              <select
                className="mt-1 w-full rounded-lg border-gray-300"
                value={method}
                onChange={(e) => onMethod(e.target.value)}
              >
                {methods?.map(m => <option key={m} value={m}>{m}</option>)}
              </select>
              {errors.method && <p className="text-red-600 text-xs mt-1">{errors.method}</p>}
            </div>

            {method === "UPI" ? (
              <div className="md:col-span-2">
                <label className="block text-sm font-medium">UPI ID</label>
                <input
                  type="text"
                  value={data.upi_id}
                  onChange={(e) => setData("upi_id", e.target.value)}
                  className="mt-1 w-full rounded-lg border-gray-300"
                  placeholder="yourname@bank"
                  required
                />
                {errors.upi_id && <p className="text-red-600 text-xs mt-1">{errors.upi_id}</p>}
              </div>
            ) : (
              <>
                <div>
                  <label className="block text-sm font-medium">Account Holder Name</label>
                  <input className="mt-1 w-full rounded-lg border-gray-300" value={data.account_name} onChange={(e) => setData("account_name", e.target.value)} required />
                  {errors.account_name && <p className="text-red-600 text-xs mt-1">{errors.account_name}</p>}
                </div>
                <div>
                  <label className="block text-sm font-medium">Bank Name</label>
                  <input className="mt-1 w-full rounded-lg border-gray-300" value={data.bank_name} onChange={(e) => setData("bank_name", e.target.value)} required />
                  {errors.bank_name && <p className="text-red-600 text-xs mt-1">{errors.bank_name}</p>}
                </div>
                <div>
                  <label className="block text-sm font-medium">Account No.</label>
                  <input className="mt-1 w-full rounded-lg border-gray-300" value={data.account_no} onChange={(e) => setData("account_no", e.target.value)} required />
                  {errors.account_no && <p className="text-red-600 text-xs mt-1">{errors.account_no}</p>}
                </div>
                <div>
                  <label className="block text-sm font-medium">IFSC</label>
                  <input className="mt-1 w-full rounded-lg border-gray-300" value={data.ifsc} onChange={(e) => setData("ifsc", e.target.value)} required />
                  {errors.ifsc && <p className="text-red-600 text-xs mt-1">{errors.ifsc}</p>}
                </div>
              </>
            )}
          </div>

          <div className="mt-2 rounded-lg bg-gray-50 p-3 text-sm">
            <div className="flex items-center justify-between"><span>Estimated charge</span><span>₹ {inr(estCharge())}</span></div>
            <div className="flex items-center justify-between font-semibold"><span>Net payout</span><span>₹ {inr(estNet())}</span></div>
          </div>

          {clientError && <div className="text-sm text-red-600">{clientError}</div>}

          <button
            type="submit"
            disabled={processing}
            className="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold disabled:opacity-50"
          >
            {processing ? "Submitting..." : "Request Withdrawal"}
          </button>
        </form>

        <div className="bg-white rounded-xl shadow p-6">
          <h3 className="text-lg font-semibold mb-3">Recent Requests</h3>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="bg-gray-50 text-left">
                  <th className="px-3 py-2">ID</th>
                  <th className="px-3 py-2">Date</th>
                  <th className="px-3 py-2">Amount</th>
                  <th className="px-3 py-2">Net</th>
                  <th className="px-3 py-2">Method</th>
                  <th className="px-3 py-2">Status</th>
                  <th className="px-3 py-2">Ref</th>
                </tr>
              </thead>
              <tbody>
                {rows?.length ? rows.map(r => (
                  <tr key={r.id} className="border-t">
                    <td className="px-3 py-2">{r.id}</td>
                    <td className="px-3 py-2">{new Date(r.created_at).toLocaleString()}</td>
                    <td className="px-3 py-2 font-semibold">₹ {inr(r.amount)}</td>
                    <td className="px-3 py-2 font-semibold">₹ {inr(r.net_amount ?? (r.amount - r.charge))}</td>
                    <td className="px-3 py-2">{r.method}</td>
                    <td className="px-3 py-2">
                      <span className={
                        r.status === 'paid' ? 'text-green-600'
                          : r.status === 'rejected' ? 'text-red-600'
                          : r.status === 'approved' ? 'text-blue-600'
                          : 'text-yellow-600'
                      }>
                        {r.status}
                      </span>
                    </td>
                    <td className="px-3 py-2">{r.txn_ref || '—'}</td>
                  </tr>
                )) : (
                  <tr><td colSpan="7" className="px-3 py-6 text-center text-gray-500">No withdrawals yet.</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
