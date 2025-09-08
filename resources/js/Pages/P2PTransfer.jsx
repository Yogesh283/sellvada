import React, { useState, useEffect, useRef } from "react";
import { Head } from "@inertiajs/react";
import axios from "axios";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

export default function P2PTransfer({ initialBalance = null, csrf, debug = {} }) {
  const [form, setForm] = useState({ recipient: "", amount: "", remark: "" });
  const [errors, setErrors] = useState({});
  const [loading, setLoading] = useState(false);

  const propHasValue = initialBalance !== null && initialBalance !== undefined;
  const [balance, setBalance] = useState(Number(propHasValue ? initialBalance : 0));

  const [notif, setNotif] = useState({ open: false, type: "info", message: "" });
  const notifTimer = useRef(null);

  useEffect(() => {
    console.log("P2P page props:", { initialBalance, csrf, debug });
    if (propHasValue) {
      setBalance(Number(initialBalance ?? 0));
    } else {
      fetchBalance();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const showNotif = (type, message, autoClose = 4000) => {
    if (notifTimer.current) clearTimeout(notifTimer.current);
    setNotif({ open: true, type, message });
    notifTimer.current = setTimeout(() => setNotif((n) => ({ ...n, open: false })), autoClose);
  };

  const fetchBalance = async () => {
    try {
      const res = await axios.get("/p2p/balance", { headers: { "X-CSRF-TOKEN": csrf, Accept: "application/json" } });
      if (res?.data?.balance !== undefined) {
        setBalance(Number(res.data.balance));
        console.log("Fetched balance via API:", res.data.balance);
      }
    } catch (err) {
      console.error("Failed to fetch balance:", err);
      showNotif("error", "Failed to load balance. Refresh page.");
    }
  };

  const submit = async (ev) => {
    ev.preventDefault();
    // normalize amount to a number before validation
    const normalizedAmount = form.amount !== "" ? Number(String(form.amount).replace(/,/g, "")) : form.amount;
    const e = {};
    if (!form.recipient) e.recipient = "Recipient is required.";
    if (normalizedAmount === "" || normalizedAmount === null || isNaN(normalizedAmount)) e.amount = "Amount is required.";
    else if (Number(normalizedAmount) <= 0) e.amount = "Enter a valid amount.";
    else if (Number(normalizedAmount) > balance) e.amount = "Insufficient balance.";
    setErrors(e);
    if (Object.keys(e).length) {
      showNotif("error", Object.values(e)[0]);
      return;
    }

    setLoading(true);
    try {
      // send numeric amount rounded to 2 decimals
      const payload = { ...form, amount: Number(Number(normalizedAmount).toFixed(2)) };
      const res = await axios.post("/p2p/transfer", payload, {
        headers: { "X-CSRF-TOKEN": csrf, Accept: "application/json" },
      });

      if (res?.data?.balance !== undefined) {
        setBalance(Number(res.data.balance));
      } else {
        await fetchBalance();
      }

      setForm({ recipient: "", amount: "", remark: "" });
      setErrors({});
      showNotif("success", res.data.message || "Transfer successful.");
    } catch (err) {
      console.error("Transfer error:", err);
      if (err.response && err.response.data) {
        const msg = err.response.data.message || "Transfer failed.";
        showNotif("error", msg);
        if (err.response.data.errors) {
          // Laravel validation error structure: { field: [messages...] }
          // convert to single-string or keep array — here we keep the first message for each field
          const parsed = {};
          for (const key in err.response.data.errors) {
            if (Array.isArray(err.response.data.errors[key])) {
              parsed[key] = err.response.data.errors[key][0];
            } else {
              parsed[key] = err.response.data.errors[key];
            }
          }
          setErrors(parsed);
        }
      } else {
        showNotif("error", "Network error.");
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <AuthenticatedLayout>
      <Head title="P2P Transfer" />
      <div className="max-w-2xl mx-auto p-6 bg-white rounded-lg shadow">
        <h1 className="text-2xl font-bold mb-4">P2P Transfer</h1>

        <div className="mb-4">
          <span className="text-sm text-gray-500">Available Balance</span>
          <div className="text-3xl font-semibold">{Number(balance || 0).toFixed(2)}</div>
        </div>

        <form onSubmit={submit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium">Recipient (login id / username / email)</label>
            <input
              value={form.recipient}
              onChange={(e) => setForm({ ...form, recipient: e.target.value })}
              className="mt-1 block w-full border rounded p-2"
              placeholder="Enter recipient login id"
            />
            {errors.recipient && <p className="text-red-600 text-sm mt-1">{errors.recipient}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium">Amount</label>
            <input
              value={form.amount}
              onChange={(e) => setForm({ ...form, amount: e.target.value })}
              className="mt-1 block w-full border rounded p-2"
              placeholder="0.00"
              inputMode="decimal"
            />
            {errors.amount && <p className="text-red-600 text-sm mt-1">{errors.amount}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium">Remark (optional)</label>
            <input
              value={form.remark}
              onChange={(e) => setForm({ ...form, remark: e.target.value })}
              className="mt-1 block w-full border rounded p-2"
              placeholder="For what purpose"
            />
          </div>

          <div className="flex gap-2 items-center">
            <button type="submit" className="px-4 py-2 bg-green-600 text-white rounded disabled:opacity-60" disabled={loading}>
              {loading ? "Processing..." : "Send"}
            </button>
            <button
              type="button"
              className="px-4 py-2 border rounded"
              onClick={() => {
                setForm({ recipient: "", amount: "", remark: "" });
                setErrors({});
              }}
            >
              Reset
            </button>
          </div>
        </form>
      </div>

      {notif.open && (
        <div className="fixed bottom-5 right-5 z-50">
          <div className={`px-4 py-3 rounded shadow text-white ${notif.type === "success" ? "bg-green-600" : "bg-red-600"}`}>
            {notif.message}
          </div>
        </div>
      )}
    </AuthenticatedLayout>
  );
}
