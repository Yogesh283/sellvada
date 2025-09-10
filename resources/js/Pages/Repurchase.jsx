// resources/js/Pages/Repurchase.jsx
import React, { useMemo, useState } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/* ---------- Money helpers ---------- */
const formatINR = (n) =>
  new Intl.NumberFormat("en-IN", {
    style: "currency",
    currency: "INR",
    maximumFractionDigits: 0,
  }).format(Number(n || 0));

/* ---------- Popup Modal ---------- */
function Popup({ open, title, message, tone = "neutral", onClose }) {
  if (!open) return null;
  const toneRing =
    tone === "success"
      ? "ring-emerald-400"
      : tone === "error"
      ? "ring-rose-400"
      : "ring-cyan-400";

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className={`w-full max-w-md rounded-2xl bg-white shadow-xl ring ${toneRing} ring-1`}>
        <div className="flex items-center justify-between border-b border-slate-200 p-4">
          <h3 className="text-lg font-semibold text-slate-900">{title}</h3>
          <button onClick={onClose} className="rounded-md p-1 text-slate-500 hover:bg-slate-100">✕</button>
        </div>
        <div className="p-4 text-slate-700 whitespace-pre-line">{message}</div>
        <div className="flex justify-end border-t border-slate-200 p-3">
          <button onClick={onClose} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Close</button>
        </div>
      </div>
    </div>
  );
}

/* ---------- Cart Row ---------- */
function Row({ it, onInc, onDec, onRemove }) {
  const line = Number(it.price) * Number(it.qty);
  return (
    <div className="grid grid-cols-1 gap-3 py-3 border-b border-slate-100 sm:grid-cols-[88px_1fr_120px_92px_120px_64px] sm:items-center">
      <div className="h-20 w-20 overflow-hidden rounded-md bg-slate-100">
        <img src={it.img} alt={it.name} className="h-full w-full object-cover" />
      </div>
      <div className="min-w-0">
        <div className="text-sm font-semibold text-slate-900">{it.name}</div>
        {it.variant && (<div className="text-xs text-slate-500">{it.variant}</div>)}
      </div>
      <div className="text-sm font-medium text-slate-700">{formatINR(it.price)}</div>
      <div className="flex items-center gap-1">
        <button onClick={onDec} className="h-7 w-7 rounded-md border bg-white hover:bg-slate-50" type="button">−</button>
        <input readOnly value={it.qty} className="h-7 w-10 rounded-md border text-center text-sm" />
        <button onClick={onInc} className="h-7 w-7 rounded-md border bg-white hover:bg-slate-50" type="button">＋</button>
      </div>
      <div className="text-right font-semibold">{formatINR(line)}</div>
      <div><button onClick={onRemove} className="text-xs text-rose-600 hover:text-rose-700">Remove</button></div>
    </div>
  );
}

/* ---------- Page ---------- */
export default function Repurchase({ product, walletBalance = 0, defaultAddress = null }) {
  const { auth } = usePage().props ?? {};

  // Address card
  const AddressCard = () => {
    if (!defaultAddress) {
      return (
        <div className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
          ⚠ No shipping address found. <a href="/address" className="underline font-semibold hover:text-amber-900">Add Address</a>
        </div>
      );
    }
    return (
      <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm text-sm">
        <div className="font-semibold">{defaultAddress.name} ({defaultAddress.phone})</div>
        <div>
          {defaultAddress.line1}{defaultAddress.line2 ? `, ${defaultAddress.line2}` : ""}, {defaultAddress.city}, {defaultAddress.state} - {defaultAddress.pincode}
        </div>
        <div>{defaultAddress.country}</div>
        <div className="mt-3">
          <a href="/address" className="inline-flex items-center rounded-lg border border-sky-600 px-3 py-1.5 text-xs font-medium text-sky-700 hover:bg-sky-50">Change Address</a>
        </div>
      </div>
    );
  };

  // Cart state
  const [items, setItems] = useState([]);
  const add = (p) => setItems((arr) => {
    const f = arr.find((x) => x.id === p.id);
    if (f) return arr.map(x => x.id === p.id ? { ...x, qty: x.qty + 1 } : x);
    return [...arr, { id: p.id, name: p.name, img: p.img, variant: p.variant || null, price: Number(p.price), qty: 1, type: "repurchase" }];
  });
  const inc = (id) => setItems((arr) => arr.map(it => it.id === id ? { ...it, qty: it.qty + 1 } : it));
  const dec = (id) => setItems((arr) => arr.map(it => it.id === id && it.qty > 1 ? { ...it, qty: it.qty - 1 } : it));
  const removeItem = (id) => setItems((arr) => arr.filter(it => it.id !== id));

  // Coupon
  const [coupon, setCoupon] = useState("");
  const [appliedCoupon, setAppliedCoupon] = useState(null);
  const applyCoupon = (e) => {
    e?.preventDefault?.();
    const code = (coupon || "").trim().toUpperCase();
    if (!code) { setAppliedCoupon(null); return; }
    if (code === "FLAT10") setAppliedCoupon(code); else setAppliedCoupon(null);
  };

  // Totals
  const subTotal = useMemo(() => items.reduce((s, it) => s + Number(it.price) * Number(it.qty), 0), [items]);
  const discount = useMemo(() => (appliedCoupon === "FLAT10" ? Math.round(subTotal * 0.1) : 0), [appliedCoupon, subTotal]);
  const grand = Math.max(0, subTotal - discount);

  const [processing, setProcessing] = useState(false);

  // Popup
  const [popup, setPopup] = useState({ show: false, title: "", message: "", tone: "neutral" });
  const showPopup = (title, message, tone = "success") => setPopup({ show: true, title, message, tone });

  // Checkout
  const onCheckout = () => {
    if (!items.length) return showPopup("Cart Empty", "Please add items to cart.", "error");
    if (!defaultAddress) return showPopup("No Address", "Please add a shipping address before checkout.", "error");
    if (walletBalance < Number(grand)) return showPopup("Insufficient Balance", `Need ${formatINR(grand)} in wallet.`, "error");

    router.post(
      route("repurchase.repurchaseOrder"),
      { items, coupon: appliedCoupon, shipping: 0 }, // pass shipping (0 or actual)
      {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
        onSuccess: () => {
          const orderSummary = items.map(it => `${it.name} x ${it.qty} = ${formatINR(it.price * it.qty)}`).join("\n");
          setItems([]);
          setAppliedCoupon(null);
          setCoupon("");
          showPopup("Order Success", `Repurchase order placed!\n\n${orderSummary}\n\nTotal: ${formatINR(grand)}`, "success");
        },
        onError: (err) => {
          // Try to show server-side validation message if present
          const msg = err?.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(" | ") : "Something went wrong.";
          showPopup("Checkout Failed", msg, "error");
        },
      }
    );
  };

  return (
    <AuthenticatedLayout user={auth?.user}>
      <Head title="Repurchase" />

      <div className="mx-auto max-w-6xl px-3 sm:px-6 py-6 space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-xl sm:text-2xl font-semibold tracking-tight">Repurchase</h1>
          <div className="text-sm text-slate-600">Wallet: <span className="font-semibold text-slate-900">{formatINR(walletBalance)}</span></div>
        </div>

        <section>
          <h2 className="text-lg font-semibold mb-2">Shipping Address</h2>
          <AddressCard />
        </section>

        <section className="rounded-2xl bg-white p-4 sm:p-6 shadow ring-1 ring-slate-100">
          <div className="grid md:grid-cols-[360px_1fr] gap-6 items-start">
            <img src={product.img} alt={product.name} className="w-[320px] h-[320px] object-cover rounded-2xl bg-slate-50" />
            <div className="space-y-2">
              <h2 className="text-2xl font-bold text-slate-900">{product.name}</h2>
              <p className="text-sm text-slate-500">{product.variant}</p>

              <div className="flex items-center gap-3 mt-2">
                <div className="text-xl font-semibold text-slate-900">₹{product.price.toLocaleString()}</div>
                <div className="text-sm line-through text-slate-500">₹{product.mrp.toLocaleString()}</div>
                <div className="text-sm text-green-600 font-medium">Save ₹{product.discount.toLocaleString()} ({product.discountPercent}%)</div>
              </div>

              <div className="mt-4 flex w-full flex-col sm:flex-row gap-2">
                <button onClick={() => add(product)} className="w-full sm:w-auto rounded-lg border border-indigo-600 px-4 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-50" type="button">Add to Cart</button>
                <button onClick={onCheckout} disabled={processing} className="w-full sm:w-auto rounded-lg bg-gradient-to-r from-indigo-600 to-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:from-indigo-700 hover:to-blue-700 disabled:opacity-60" type="button">{processing ? "Processing..." : "Buy Now"}</button>
              </div>
            </div>
          </div>
        </section>

        {items.length > 0 && (
          <section className="rounded-2xl bg-white p-4 sm:p-6 shadow ring-1 ring-slate-100">
            <h3 className="text-lg font-semibold mb-3">Cart</h3>
            <div className="divide-y divide-slate-100">{items.map(it => <Row key={it.id} it={it} onInc={() => inc(it.id)} onDec={() => dec(it.id)} onRemove={() => removeItem(it.id)} />)}</div>

            <form onSubmit={applyCoupon} className="mt-4 flex items-center gap-2">
              <input value={coupon} onChange={(e) => setCoupon(e.target.value)} placeholder="Have a coupon? e.g., FLAT10" className="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" />
              <button type="submit" className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Apply</button>
              {appliedCoupon && <span className="text-xs font-medium text-emerald-700">Applied: {appliedCoupon}</span>}
            </form>

            <div className="mt-4 space-y-1 text-sm">
              <div className="flex justify-between"><span>Subtotal</span><span>{formatINR(subTotal)}</span></div>
              <div className="flex justify-between text-emerald-700"><span>Discount</span><span>- {formatINR(discount)}</span></div>
              <div className="border-t pt-2 flex justify-between font-bold"><span>Total</span><span>{formatINR(grand)}</span></div>
            </div>

            <div className="mt-4 flex gap-2">
              <button onClick={onCheckout} disabled={processing} className="flex-1 rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 disabled:opacity-60">{processing ? "Processing..." : "Proceed to Checkout"}</button>
            </div>
          </section>
        )}
      </div>

      <Popup open={popup.show} title={popup.title} message={popup.message} tone={popup.tone} onClose={() => setPopup({ ...popup, show: false })} />
    </AuthenticatedLayout>
  );
}
