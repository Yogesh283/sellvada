// resources/js/Pages/Card.jsx
import React, { useMemo, useState, useEffect } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, router } from "@inertiajs/react";

/**
 * Cart / Add-To-Cart Page
 * - Reads cart from localStorage key: "cart"
 * - Keeps localStorage in sync with state
 * - Checkout posts to /checkout (auth protected)
 */

const formatINR = (n) =>
  new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR" }).format(n);

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
  try {
    localStorage.setItem(lsKey, JSON.stringify(items));
  } catch {}
};

function QtyButton({ onClick, children, disabled, "aria-label": ariaLabel }) {
  return (
    <button
      onClick={onClick}
      aria-label={ariaLabel}
      disabled={disabled}
      className="h-9 w-9 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 active:scale-[0.98] disabled:opacity-40"
      type="button"
    >
      {children}
    </button>
  );
}

function CartRow({ item, onInc, onDec, onRemove }) {
  const lineTotal = item.price * item.qty;
  return (
    <div className="grid grid-cols-[72px_1fr_auto] sm:grid-cols-[90px_1fr_auto_auto] gap-3 sm:gap-4 items-center border-b border-slate-100 py-4">
      {/* image */}
      <div className="h-18 w-18 sm:h-20 sm:w-20 overflow-hidden rounded-md bg-slate-100">
        <img
          src={item.img}
          alt={item.name}
          className="h-full w-full object-cover object-center"
          loading="lazy"
        />
      </div>

      {/* title + price (mobile) */}
      <div className="min-w-0">
        <div className="text-sm sm:text-base font-semibold text-slate-900 line-clamp-2">
          {item.name}
        </div>
        {item.variant ? <div className="text-xs text-slate-500">{item.variant}</div> : null}
        <div className="mt-1 text-sm text-slate-700 sm:hidden">{formatINR(item.price)}</div>
      </div>

      {/* unit price (desktop) */}
      <div className="hidden sm:block text-sm font-medium text-slate-700">
        {formatINR(item.price)}
      </div>

      {/* qty + actions + line total */}
      <div className="col-span-3 sm:col-span-1 flex items-center justify-between sm:justify-end gap-3">
        <div className="flex items-center gap-2">
          <QtyButton onClick={onDec} aria-label="Decrease quantity" disabled={item.qty <= 1}>
            −
          </QtyButton>
          <input
            readOnly
            value={item.qty}
            className="h-9 w-14 rounded-lg border border-slate-200 bg-white text-center text-sm"
          />
          <QtyButton onClick={onInc} aria-label="Increase quantity">
            ＋
          </QtyButton>
        </div>

        <div className="min-w-[96px] text-right font-semibold text-slate-900">
          {formatINR(lineTotal)}
        </div>

        <button onClick={onRemove} className="text-xs text-rose-600 hover:text-rose-700" type="button">
          Remove
        </button>
      </div>
    </div>
  );
}

export default function Card({ items: serverItems = null, shipping = 49 }) {
  // Prefer serverItems (if provided), else localStorage
  const [items, setItems] = useState(serverItems || []);

  // Load from localStorage on first mount if serverItems not provided
  useEffect(() => {
    if (!serverItems) {
      const saved = loadCart();
      if (saved.length) setItems(saved);
    } else {
      // If server sends items, also sync LS so refresh keeps state
      saveCart(serverItems);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Keep localStorage in sync whenever items change
  useEffect(() => {
    saveCart(items);
  }, [items]);

  const [coupon, setCoupon] = useState("");
  const [appliedCoupon, setAppliedCoupon] = useState(null);
  const [processing, setProcessing] = useState(false);

  const subTotal = useMemo(
    () => items.reduce((s, it) => s + Number(it.price) * Number(it.qty), 0),
    [items]
  );

  const discount = useMemo(() => {
    if (!appliedCoupon) return 0;
    if (appliedCoupon === "FLAT10") return Math.round(subTotal * 0.1);
    return 0;
  }, [appliedCoupon, subTotal]);

  const taxable = Math.max(0, subTotal - discount);
  const tax = Math.round(taxable * 0.05);
  const grand = taxable + tax + (items.length ? Number(shipping) : 0);

  const inc = (id) =>
    setItems((arr) => arr.map((it) => (it.id === id ? { ...it, qty: it.qty + 1 } : it)));
  const dec = (id) =>
    setItems((arr) =>
      arr.map((it) => (it.id === id && it.qty > 1 ? { ...it, qty: it.qty - 1 } : it))
    );
  const removeItem = (id) => setItems((arr) => arr.filter((it) => it.id !== id));

  const applyCoupon = (e) => {
    e.preventDefault();
    setAppliedCoupon(coupon.trim().toUpperCase());
  };

  const clearCartEverywhere = () => {
    setItems([]);
    saveCart([]);
    setAppliedCoupon(null);
    setCoupon("");
  };

  const onCheckout = (e) => {
    e.preventDefault();
    if (!items.length || processing) return;

    router.post(
      "/checkout",
      {
        items,               // will contain exact same product + price added from Welcome.jsx
        coupon: appliedCoupon,
        shipping,
      },
      {
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
        onSuccess: () => {
          clearCartEverywhere();
        },
      }
    );
  };

  return (
    <AuthenticatedLayout>
      <Head title="Your Cart" />

      <div className="bg-slate-50">
        {/* HEADER */}
        <div className="bg-gradient-to-r from-cyan-600 via-sky-600 to-blue-600">
          <div className="mx-auto max-w-6xl px-4 sm:px-6 py-4 sm:py-6 flex items-center gap-3">
            <img src="/image/11111.png" alt="CellVeda" className="h-8 w-auto sm:h-10 shrink-0" />
            <div className="min-w-0">
              <h1 className="text-lg sm:text-3xl font-bold text-white">Your Shopping Cart</h1>
              <p className="text-white/90 text-xs sm:text-sm truncate">
                Review your items and proceed to checkout.
              </p>
            </div>
          </div>
        </div>

        {/* CONTENT */}
        <div className="mx-auto max-w-6xl px-4 sm:px-6 py-6 sm:py-8 grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
          {/* CART ITEMS */}
          <section className="rounded-2xl bg-white p-4 sm:p-6 shadow ring-1 ring-slate-100">
            {items.length === 0 ? (
              <div className="py-14 sm:py-16 text-center">
                <div className="text-lg sm:text-xl font-semibold text-slate-900">Your cart is empty</div>
                <p className="mt-1 text-slate-600">Add products to continue shopping.</p>
                <a
                  href="/"
                  className="mt-4 inline-flex w-full sm:w-auto justify-center rounded-lg bg-sky-600 px-4 py-2.5 text-white font-semibold hover:bg-sky-700"
                >
                  Browse Products
                </a>
              </div>
            ) : (
              <>
                {/* Table header (desktop only) */}
                <div className="hidden sm:grid grid-cols-[90px_1fr_auto_auto] gap-4 border-b border-slate-200 pb-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                  <div>Item</div>
                  <div>Product</div>
                  <div>Price</div>
                  <div className="text-right">Total</div>
                </div>

                <div className="divide-y divide-slate-100">
                  {items.map((it) => (
                    <CartRow
                      key={it.id}
                      item={it}
                      onInc={() => inc(it.id)}
                      onDec={() => dec(it.id)}
                      onRemove={() => removeItem(it.id)}
                    />
                  ))}
                </div>

                {/* Coupon */}
                <form
                  onSubmit={applyCoupon}
                  className="mt-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-3"
                >
                  <input
                    value={coupon}
                    onChange={(e) => setCoupon(e.target.value)}
                    placeholder="Have a coupon? e.g., FLAT10"
                    className="w-full sm:max-w-xs rounded-lg border border-slate-200 px-3 py-2.5 text-sm"
                    inputMode="text"
                    autoComplete="off"
                  />
                  <button
                    type="submit"
                    className="rounded-lg bg-sky-600 px-4 py-2.5 text-white text-sm font-semibold hover:bg-sky-700"
                  >
                    Apply
                  </button>
                  {appliedCoupon && (
                    <span className="text-xs font-medium text-emerald-700">Applied: {appliedCoupon}</span>
                  )}
                </form>
              </>
            )}
          </section>

          {/* SUMMARY */}
          <aside className="h-max rounded-2xl bg-white p-4 sm:p-6 shadow ring-1 ring-slate-100 lg:sticky lg:top-6">
            <h2 className="text-lg font-semibold text-slate-900">Order Summary</h2>
            <div className="mt-3 space-y-2 text-sm">
              <div className="flex items-center justify-between">
                <span className="text-slate-600">Subtotal</span>
                <span className="font-medium text-slate-900">{formatINR(subTotal)}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-slate-600">Discount</span>
                <span className="font-medium text-emerald-700">− {formatINR(discount)}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-slate-600">Tax (5%)</span>
                <span className="font-medium text-slate-900">{formatINR(tax)}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-slate-600">Shipping</span>
                <span className="font-medium text-slate-900">
                  {items.length ? formatINR(shipping) : formatINR(0)}
                </span>
              </div>
              <div className="my-2 border-t border-slate-200" />
              <div className="flex items-center justify-between text-base font-bold text-slate-900">
                <span>Total</span>
                <span>{formatINR(grand)}</span>
              </div>
            </div>

            <div className="mt-5 grid gap-3">
              <button
                onClick={onCheckout}
                disabled={processing || items.length === 0}
                className="inline-flex items-center justify-center rounded-lg bg-gradient-to-r from-cyan-600 via-sky-600 to-blue-600 px-5 py-2.5 text-white font-semibold hover:from-cyan-700 hover:to-blue-700 w-full disabled:opacity-60"
                type="button"
              >
                {processing ? "Processing..." : "Proceed to Checkout"}
              </button>
              <a
                href="/"
                className="inline-flex items-center justify-center rounded-lg border border-slate-200 px-5 py-2.5 text-slate-700 font-semibold hover:bg-slate-50 w-full"
              >
                Continue Shopping
              </a>
            </div>

            <div className="mt-6 text-[11px] text-slate-500">
              Payments supported: UPI, Cards, NetBanking. Secure checkout.
            </div>
          </aside>
        </div>

        {/* MOBILE BOTTOM BAR CTA */}
        {items.length > 0 && (
          <div className="lg:hidden sticky bottom-0 inset-x-0 border-t border-slate-200 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/60">
            <div className="mx-auto max-w-6xl px-4 sm:px-6 py-3 flex items-center justify-between gap-3">
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
    </AuthenticatedLayout>
  );
}
