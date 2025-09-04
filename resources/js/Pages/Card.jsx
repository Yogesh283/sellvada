import React, { useMemo, useState, useEffect } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, router, usePage } from "@inertiajs/react";

const formatINR = (n) =>
  new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR" }).format(Number(n || 0));

const lsKey = "cart";
const loadCart = () => {
  try {
    const raw = localStorage.getItem(lsKey);
    return raw ? JSON.parse(raw) : [];
  } catch {
    return [];
  }
};
const saveCart = (items) => {
  try { localStorage.setItem(lsKey, JSON.stringify(items)); } catch { }
};

function QtyButton({ onClick, children, disabled, "aria-label": ariaLabel }) {
  return (
    <button
      onClick={onClick}
      aria-label={ariaLabel}
      disabled={disabled}
      className="h-10 w-10 sm:h-9 sm:w-9 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 active:scale-[0.98] disabled:opacity-40"
      type="button"
    >
      {children}
    </button>
  );
}

function CartRow({ item, onInc, onDec, onRemove }) {
  const lineTotal = Number(item.price) * Number(item.qty);
  return (
    <div className="grid grid-cols-[64px_1fr_auto] sm:grid-cols-[90px_1fr_auto_auto] gap-3 sm:gap-4 items-center border-b border-slate-100 py-3 sm:py-4">
      <div className="h-16 w-16 sm:h-20 sm:w-20 overflow-hidden rounded-md bg-slate-100">
        <img src={item.img} alt={item.name} className="h-full w-full object-cover object-center" loading="lazy" decoding="async" />
      </div>
      <div className="min-w-0">
        <div className="text-[13px] sm:text-base font-semibold text-slate-900 line-clamp-2">{item.name}</div>
        {item.variant ? <div className="text-[11px] sm:text-xs text-slate-500 line-clamp-1">{item.variant}</div> : null}
        <div className="mt-1 text-[13px] text-slate-700 sm:hidden">{formatINR(item.price)}</div>
      </div>
      <div className="hidden sm:block text-sm font-medium text-slate-700">{formatINR(item.price)}</div>
      <div className="col-span-3 sm:col-span-1 flex items-center justify-between sm:justify-end gap-2 sm:gap-3">
        <div className="flex items-center gap-1.5 sm:gap-2">
          <QtyButton onClick={onDec} aria-label="Decrease quantity" disabled={item.qty <= 1}>−</QtyButton>
          <input readOnly value={item.qty} className="h-9 w-12 sm:w-14 rounded-lg border border-slate-200 bg-white text-center text-sm" />
          <QtyButton onClick={onInc} aria-label="Increase quantity">＋</QtyButton>
        </div>
        <div className="min-w-[80px] sm:min-w-[96px] text-right font-semibold text-slate-900">{formatINR(lineTotal)}</div>
        <button onClick={onRemove} className="text-[12px] sm:text-xs text-rose-600 hover:text-rose-700" type="button">Remove</button>
      </div>
    </div>
  );
}

export default function Card({ items: serverItems = null }) { // 🔁 shipping prop हटा दिया
  const { walletBalance = 0, defaultAddress = null } = usePage().props;

  const [items, setItems] = useState(serverItems || []);
  const [toast, setToast] = useState(null);
  const [toastType, setToastType] = useState("success");
  const showSuccess = (msg) => { setToastType("success"); setToast(msg || "Order placed successfully!"); setTimeout(() => setToast(null), 2500); };
  const showError = (msg) => { setToastType("error"); setToast(msg || "Something went wrong."); setTimeout(() => setToast(null), 2500); };

  useEffect(() => {
    if (!serverItems) {
      const saved = loadCart();
      if (saved.length) setItems(saved);
    } else {
      saveCart(serverItems);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => { saveCart(items); }, [items]);

  const [coupon, setCoupon] = useState("");
  const [appliedCoupon, setAppliedCoupon] = useState(null);
  const [processing, setProcessing] = useState(false);

  // 👉 Totals: सिर्फ MRP - Coupon, कोई tax/shipping/extra नहीं
  const subTotal = useMemo(
    () => items.reduce((s, it) => s + Number(it.price) * Number(it.qty), 0),
    [items]
  );
  const discount = useMemo(
    () => (!appliedCoupon ? 0 : appliedCoupon === "FLAT10" ? Math.round(subTotal * 0.1) : 0),
    [appliedCoupon, subTotal]
  );
  const grand = Math.max(0, subTotal - discount); // ✅ final payable

  const inc = (id) => setItems((arr) => arr.map((it) => (it.id === id ? { ...it, qty: it.qty + 1 } : it)));
  const dec = (id) => setItems((arr) => arr.map((it) => (it.id === id && it.qty > 1 ? { ...it, qty: it.qty - 1 } : it)));
  const removeItem = (id) => setItems((arr) => arr.filter((it) => it.id !== id));

  const applyCoupon = (e) => { e.preventDefault(); setAppliedCoupon(coupon.trim().toUpperCase() || null); };
  const clearCartEverywhere = () => { setItems([]); saveCart([]); setAppliedCoupon(null); setCoupon(""); };

  const onCheckout = (e) => {
    e.preventDefault();
    if (!items.length || processing) return;

    if (!defaultAddress) {
      showError("Please add a shipping address before checkout.");
      return;
    }
    if (walletBalance < Number(grand)) {
      showError(`Insufficient wallet balance to pay ${formatINR(grand)}.`);
      return;
    }

    router.post('/checkout', {
      items,
      coupon: appliedCoupon,
      shipping: 0   // 👈 या जो भी shipping amount है
    }, {
      onStart: () => setProcessing(true),
      onFinish: () => setProcessing(false),
      onSuccess: () => {
        showSuccess('Order placed successfully!');
        clearCartEverywhere();
      },
      onError: (errors) => {
        if (errors?.wallet) {
          showError(errors.wallet);
        } else if (typeof errors === "object" && Object.keys(errors).length) {
          const firstError = Object.values(errors)[0];
          showError(firstError);
        } else {
          showError('Checkout failed. Please try again.');
        }
      },
    });
  };

  return (
    <AuthenticatedLayout>
      <Head title="Your Cart" />

      <div className="bg-slate-50 overflow-x-hidden">
        {/* HEADER */}
        <div className="bg-gradient-to-r from-cyan-600 via-sky-600 to-blue-600">
          <div className="mx-auto max-w-6xl px-3 sm:px-6 py-3 sm:py-6 flex items-center gap-3">
            <img src="/image/11111.png" alt="Brand" className="h-7 w-auto sm:h-10 shrink-0" />
            <div className="min-w-0">
              <h1 className="text-base sm:text-3xl font-bold text-white">Your Shopping Cart</h1>
              <p className="text-white/90 text-[12px] sm:text-sm truncate">Review your items and proceed to checkout.</p>
            </div>
          </div>
        </div>

        {/* CONTENT */}
        <div className="mx-auto max-w-6xl px-3 sm:px-6 py-4 sm:py-8 grid gap-4 sm:gap-6 lg:grid-cols-[1.2fr_.8fr]">
          {/* CART + ADDRESS */}
          <section className="rounded-2xl bg-white p-4 sm:p-6 shadow ring-1 ring-slate-100">
            {/* Shipping address box */}
            <div className="mb-4">
              <div className="flex items-center justify-between">
                <h3 className="text-base font-semibold">Shipping Address</h3>
                <a href="/address" className="text-indigo-600 text-sm hover:underline">Manage Addresses</a>
              </div>

              {!defaultAddress ? (
                <div className="mt-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                  No address found. Please add an address before checkout.{" "}
                  <a href="/address" className="underline">Add Address</a>
                </div>
              ) : (
                <div className="mt-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                  <div className="font-semibold">
                    {defaultAddress.name} ({defaultAddress.phone})
                  </div>
                  <div>
                    {defaultAddress.line1}
                    {defaultAddress.line2 ? `, ${defaultAddress.line2}` : ""},{" "}
                    {defaultAddress.city}, {defaultAddress.state} - {defaultAddress.pincode}
                  </div>
                  <div>{defaultAddress.country}</div>
                </div>
              )}
            </div>

            {items.length === 0 ? (
              <div className="py-10 sm:py-16 text-center">
                <div className="text-lg sm:text-xl font-semibold text-slate-900">Your cart is empty</div>
                <p className="mt-1 text-slate-600">Add products to continue shopping.</p>
                <a href="/" className="mt-4 inline-flex w-full sm:w-auto justify-center rounded-lg bg-sky-600 px-4 py-2.5 text-white font-semibold hover:bg-sky-700">Browse Products</a>
              </div>
            ) : (
              <>
                <div className="hidden sm:grid grid-cols-[90px_1fr_auto_auto] gap-4 border-b border-slate-200 pb-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                  <div>Item</div><div>Product</div><div>Price</div><div className="text-right">Total</div>
                </div>

                <div className="divide-y divide-slate-100">
                  {items.map((it) => (
                    <CartRow
                      key={it.id}
                      item={it}
                      onInc={() => setItems(arr => arr.map(x => x.id === it.id ? { ...x, qty: x.qty + 1 } : x))}
                      onDec={() => setItems(arr => arr.map(x => (x.id === it.id && x.qty > 1) ? { ...x, qty: x.qty - 1 } : x))}
                      onRemove={() => setItems(arr => arr.filter(x => x.id !== it.id))}
                    />
                  ))}
                </div>

                {/* Coupon */}
                <form onSubmit={applyCoupon} className="mt-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3">
                  <input
                    value={coupon}
                    onChange={(e) => setCoupon(e.target.value)}
                    placeholder="Have a coupon? e.g., FLAT10"
                    className="w-full sm:max-w-xs rounded-lg border border-slate-200 px-3 py-2.5 text-sm"
                    inputMode="text"
                    autoComplete="off"
                  />
                  <button type="submit" className="w-full sm:w-auto rounded-lg bg-sky-600 px-4 py-2.5 text-white text-sm font-semibold hover:bg-sky-700">Apply</button>
                  {appliedCoupon && <span className="text-xs font-medium text-emerald-700">Applied: {appliedCoupon}</span>}
                </form>
              </>
            )}
          </section>

          {/* SUMMARY */}
          <aside className="h-max rounded-2xl bg-white p-4 sm:p-6 shadow ring-1 ring-slate-100 lg:sticky lg:top-6">
            <h2 className="text-base sm:text-lg font-semibold text-slate-900">Order Summary</h2>

            <div className="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-[13px] sm:text-sm">
              <div className="flex items-center justify-between">
                <span className="text-slate-600">Wallet Balance</span>
                <span className="font-semibold text-slate-900">{formatINR(walletBalance)}</span>
              </div>
              {walletBalance < Number(grand) && (
                <div className="mt-1 text-[12px] text-rose-600">
                  Not enough balance to pay {formatINR(grand)}.
                </div>
              )}
            </div>

            <div className="mt-3 space-y-1.5 sm:space-y-2 text-[13px] sm:text-sm">
              <div className="flex items-center justify-between">
                <span className="text-slate-600">Subtotal</span>
                <span className="font-medium text-slate-900">{formatINR(subTotal)}</span>
              </div>

              {appliedCoupon && discount > 0 && (
                <div className="flex items-center justify-between text-emerald-700">
                  <span>Coupon Discount</span>
                  <span>-{formatINR(discount)}</span>
                </div>
              )}

              <div className="my-2 border-t border-slate-200" />

              <div className="flex items-center justify-between text-base font-bold text-slate-900">
                <span>Total</span>
                <span>{formatINR(grand)}</span>
              </div>
            </div>

            <div className="mt-4 sm:mt-5 grid gap-2 sm:gap-3">
              <button
                onClick={onCheckout}
                disabled={processing || items.length === 0}
                className="w-full inline-flex items-center justify-center rounded-lg bg-gradient-to-r from-cyan-600 via-sky-600 to-blue-600 px-5 py-2.5 text-white font-semibold hover:from-cyan-700 hover:to-blue-700 disabled:opacity-60"
                type="button"
              >
                {processing ? "Processing..." : "Proceed to Checkout"}
              </button>
              <a href="/" className="w-full inline-flex items-center justify-center rounded-lg border border-slate-200 px-5 py-2.5 text-slate-700 font-semibold hover:bg-slate-50">Continue Shopping</a>
            </div>

            {!defaultAddress && (
              <div className="mt-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-[12px] text-amber-800">
                Please select a shipping address before checkout.{" "}
                <a href="/address" className="underline">Add Address</a>
              </div>
            )}

            <div className="mt-6 text-[11px] text-slate-500">Payments supported: UPI, Cards, NetBanking. Secure checkout.</div>
          </aside>
        </div>

        {/* Mobile bottom bar */}
        {items.length > 0 && (
          <div className="lg:hidden sticky bottom-0 inset-x-0 border-t border-slate-200 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/60">
            <div className="mx-auto max-w-6xl px-3 py-2 sm:px-6 sm:py-3 flex items-center justify-between gap-3" style={{ paddingBottom: "calc(env(safe-area-inset-bottom, 0px) + 8px)" }}>
              <div className="text-sm">
                <div className="text-slate-600">Total</div>
                <div className="font-semibold text-slate-900">{formatINR(grand)}</div>
              </div>
              <button
                onClick={onCheckout}
                disabled={processing || items.length === 0}
                className="inline-flex flex-1 justify-center rounded-lg bg-sky-600 px-4 py-2.5 text-white font-semibold hover:bg-sky-700 disabled:opacity-60"
                type="button"
              >
                {processing ? "Processing..." : "Checkout"}
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Toast */}
      {toast && (
        <div className="fixed left-1/2 z-[9999] -translate-x-1/2" style={{ bottom: "calc(env(safe-area-inset-bottom, 0px) + 16px)" }}>
          <div className={`rounded-lg px-3 sm:px-4 py-2.5 sm:py-3 text-white shadow-lg ring-1 ${toastType === "error" ? "bg-rose-600 ring-rose-500/40" : "bg-emerald-600 ring-emerald-500/40"}`}>
            <div className="flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                {toastType === "error"
                  ? <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v4m0 4h.01M4.93 4.93l14.14 14.14M12 2a10 10 0 100 20 10 10 0 000-20z" />
                  : <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                }
              </svg>
              <span className="text-[13px] sm:text-sm font-medium">{toast}</span>
            </div>
          </div>
        </div>
      )}
    </AuthenticatedLayout>
  );
}
