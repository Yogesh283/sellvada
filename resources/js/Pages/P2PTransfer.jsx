import React, { useEffect, useRef, useState } from "react";
import { Head } from "@inertiajs/react";
import { Inertia } from "@inertiajs/inertia";
import axios from "axios";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/* ---------- Helpers ---------- */
const formatINR = (n) =>
  new Intl.NumberFormat("en-IN", {
    style: "currency",
    currency: "INR",
    maximumFractionDigits: 2,
  }).format(Number(n || 0));

const fmtDate = (d) => {
  if (!d) return "—";
  try {
    const dt = new Date(d);
    return dt.toLocaleString("en-IN");
  } catch (e) {
    return d;
  }
};

/**
 * P2PTransfer Page
 *
 * Props expected:
 *  - initialBalance: number
 *  - csrf: string
 *  - debug: { user_id: number }
 *  - transactions: paginator-object OR array
 */
export default function P2PTransfer({ initialBalance = null, csrf, debug = {}, transactions = [] }) {
  // form
  const [form, setForm] = useState({ recipient: "", amount: "", remark: "" });
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);

  // balance
  const propHasValue = initialBalance !== null && initialBalance !== undefined;
  const [balance, setBalance] = useState(Number(propHasValue ? initialBalance : 0));

  // transactions state (normalize to paginator shape if possible)
  const normalize = (txs) => {
    if (!txs) return { data: [], links: [], meta: null };
    if (Array.isArray(txs)) return { data: txs, links: [], meta: null };
    // assume paginator object
    return {
      data: Array.isArray(txs.data) ? txs.data : [],
      links: Array.isArray(txs.links) ? txs.links : [],
      meta: txs.meta ?? null,
      current_page: txs.current_page ?? (txs.meta?.current_page ?? null),
      last_page: txs.last_page ?? (txs.meta?.last_page ?? null),
    };
  };

  const [txState, setTxState] = useState(normalize(transactions));
  const [txLoading, setTxLoading] = useState(false);

  // notifications
  const [notif, setNotif] = useState({ open: false, type: "info", message: "" });
  const notifTimer = useRef(null);

  useEffect(() => {
    setTxState(normalize(transactions));
    if (propHasValue) setBalance(Number(initialBalance ?? 0));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    return () => {
      if (notifTimer.current) clearTimeout(notifTimer.current);
    };
  }, []);

  const showNotif = (type, message, autoClose = 4500) => {
    if (notifTimer.current) clearTimeout(notifTimer.current);
    setNotif({ open: true, type, message });
    notifTimer.current = setTimeout(() => setNotif((n) => ({ ...n, open: false })), autoClose);
  };

  /* ---------- API helpers ---------- */
  const fetchBalance = async () => {
    try {
      const res = await axios.get("/p2p/balance", { headers: { "X-CSRF-TOKEN": csrf, Accept: "application/json" } });
      if (res?.data?.balance !== undefined) setBalance(Number(res.data.balance));
    } catch (err) {
      console.error("fetchBalance:", err);
    }
  };

  // Optional server JSON endpoint to refresh transactions without full Inertia navigation
  // If you have /p2p/transactions-json, this will populate txState.data; otherwise do nothing.
  const fetchTransactionsJson = async () => {
    setTxLoading(true);
    try {
      const res = await axios.get("/p2p/transactions-json", { headers: { "X-CSRF-TOKEN": csrf, Accept: "application/json" } });
      if (res?.data?.rows && Array.isArray(res.data.rows)) {
        setTxState({ data: res.data.rows, links: [], meta: null });
      } else if (res?.data?.transactions && Array.isArray(res.data.transactions)) {
        setTxState({ data: res.data.transactions, links: [], meta: null });
      } else {
        // try to find first array in response
        const arr = Object.values(res.data).find((v) => Array.isArray(v));
        if (arr) setTxState({ data: arr, links: [], meta: null });
      }
    } catch (err) {
      // endpoint may not exist — ignore quietly
      console.debug("fetchTransactionsJson failed (endpoint might be absent).");
    } finally {
      setTxLoading(false);
    }
  };

  /* ---------- Pagination via Inertia ---------- */
  // When server returns paginator, it provides `links` array with url for each page.
  // Use Inertia.visit(url) so Laravel will render the page and return new Inertia props.
  const goToPage = (url) => {
    if (!url) return;
    setTxLoading(true);
    Inertia.visit(url, {
      method: "get",
      preserveScroll: true,
      onFinish: () => setTxLoading(false),
    });
  };

  /* ---------- Form submit ---------- */
  const handleSubmit = async (ev) => {
    ev.preventDefault();

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

    setSubmitting(true);
    try {
      const payload = { ...form, amount: Number(Number(normalizedAmount).toFixed(2)) };
      const res = await axios.post("/p2p/transfer", payload, {
        headers: { "X-CSRF-TOKEN": csrf, Accept: "application/json" },
      });

      if (res?.data?.balance !== undefined) setBalance(Number(res.data.balance));
      else await fetchBalance();

      setForm({ recipient: "", amount: "", remark: "" });
      setErrors({});
      showNotif("success", res.data.message || "Transfer successful.");

      // Prefer in-order: try JSON refresh endpoint first, fallback to Inertia page reload for pagination
      await fetchTransactionsJson();
      // If txState still empty and transactions prop was paginator, we can trigger Inertia to reload current page:
      if ((!txState.data || txState.data.length === 0) && window.location) {
        // reload current Inertia page to get fresh paginator props
        Inertia.reload();
      }
    } catch (err) {
      console.error("Transfer error:", err);
      if (err.response && err.response.data) {
        const msg = err.response.data.message || "Transfer failed.";
        showNotif("error", msg);
        if (err.response.data.errors) {
          const parsed = {};
          for (const key in err.response.data.errors) {
            if (Array.isArray(err.response.data.errors[key])) parsed[key] = err.response.data.errors[key][0];
            else parsed[key] = err.response.data.errors[key];
          }
          setErrors(parsed);
        }
      } else {
        showNotif("error", "Network error.");
      }
    } finally {
      setSubmitting(false);
    }
  };

  const currentUserId = Number(debug?.user_id ?? debug?.authId ?? 0);

  return (
    <AuthenticatedLayout>
      <Head title="P2P Transfer" />

      <div className="max-w-2xl mx-auto p-6 bg-white rounded-lg shadow">
        <h1 className="text-2xl font-bold mb-4">P2P Transfer</h1>

        <div className="mb-4">
          <span className="text-sm text-gray-500">Available Balance</span>
          <div className="text-3xl font-semibold">{formatINR(balance)}</div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium">Recipient (id / username / email)</label>
            <input
              value={form.recipient}
              onChange={(e) => setForm({ ...form, recipient: e.target.value })}
              className="mt-1 block w-full border rounded p-2"
              placeholder="Enter recipient id or username"
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
            <button
              type="submit"
              className="px-4 py-2 bg-green-600 text-white rounded disabled:opacity-60"
              disabled={submitting}
            >
              {submitting ? "Processing..." : "Send"}
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

      {/* Transactions list + pagination */}
      <div className="max-w-6xl mx-auto p-6 bg-white rounded-lg shadow mt-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-xl font-semibold">Recent P2P Transactions</h2>
          <div className="text-sm text-slate-600">
            Page {transactions?.current_page ?? txState.meta?.current_page ?? 1}{" "}
            {transactions?.last_page ? `of ${transactions.last_page}` : ""}
          </div>
        </div>

        {(!txState.data || txState.data.length === 0) ? (
          <div className="text-sm text-slate-500">No transactions to show.</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-slate-50">
                <tr className="text-left">
                  <th className="px-4 py-2">Date</th>
                  <th className="px-4 py-2">Transaction ID</th>
                  <th className="px-4 py-2">Direction</th>
                  <th className="px-4 py-2">Counterparty</th>
                  <th className="px-4 py-2 text-right">Amount</th>
                  <th className="px-4 py-2">Remark</th>
                  <th className="px-4 py-2">Status</th>
                </tr>
              </thead>
              <tbody>
                {txState.data.map((t) => {
                  const isSent = Number(t.from_user_id) === Number(currentUserId);
                  const direction = isSent ? "Sent" : "Received";
                  const counterparty = isSent ? (t.to_name || `ID:${t.to_user_id}`) : (t.from_name || `ID:${t.from_user_id}`);
                  return (
                    <tr key={t.id} className="border-t">
                      <td className="px-4 py-2">{fmtDate(t.created_at)}</td>
                      <td className="px-4 py-2">{t.id}</td>
                      <td className="px-4 py-2">{direction}</td>
                      <td className="px-4 py-2">{counterparty}</td>
                      <td className="px-4 py-2 text-right font-semibold">{formatINR(t.amount)}</td>
                      <td className="px-4 py-2">{t.remark || "—"}</td>
                      <td className="px-4 py-2">—</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination controls: prefer server-provided links if present */}
        <div className="mt-4 flex items-center justify-center space-x-2">
          {((transactions?.links) ?? txState.links ?? []).map((link, idx) => {
            // link: { url, label, active }
            const labelHtml = String(link.label).replace(/&laquo;/g, "«").replace(/&raquo;/g, "»");
            const isDisabled = !link.url;
            const isActive = !!link.active;
            return (
              <button
                key={idx}
                onClick={() => !isDisabled && goToPage(link.url)}
                disabled={isDisabled || txLoading}
                className={`px-3 py-1 rounded border text-sm ${isActive ? "bg-slate-900 text-white" : "bg-white text-slate-700"} ${isDisabled ? "opacity-50 cursor-not-allowed" : "hover:bg-slate-100"}`}
                dangerouslySetInnerHTML={{ __html: labelHtml }}
              />
            );
          })}
        </div>
      </div>

      {/* Notification */}
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
