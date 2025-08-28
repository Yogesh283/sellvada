import React, { useMemo, useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, usePage, router } from "@inertiajs/react";

/* ---------- helpers (white theme) ---------- */
const INR = (n) =>
  Number(n ?? 0).toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const Card = ({ children, className = "" }) => (
  <div className={`rounded-2xl border border-slate-200 bg-white shadow-sm ${className}`}>{children}</div>
);

const Btn = ({ className = "", ...p }) => (
  <button
    {...p}
    type={p.type ?? "button"}
    className={`inline-flex items-center justify-center rounded-md px-3 py-2 text-sm font-semibold transition
    bg-sky-600 text-white hover:bg-sky-700 disabled:opacity-50 disabled:cursor-not-allowed ${className}`}
  />
);

function ProductCard({ p, onAdd }) {
  return (
    <Card className="h-full">
      <div className="p-4 flex gap-4">
        <img
          src={p.img}
          alt={p.name}
          className="h-20 w-20 shrink-0 rounded object-cover border"
          onError={(e) => (e.currentTarget.src = "/images/placeholder.png")}
        />
        <div className="flex-1 min-w-0">
          <div className="font-semibold text-slate-900 truncate">{p.name}</div>
          <div className="text-slate-500 text-sm">{p.variant}</div>
          <div className="mt-2 text-lg font-bold text-slate-900">₹ {INR(p.price)}</div>
        </div>
        <div className="hidden sm:flex items-center">
          <Btn onClick={() => onAdd(p)}>Add</Btn>
        </div>
      </div>
      <div className="px-4 pb-4 sm:hidden">
        <Btn className="w-full" onClick={() => onAdd(p)}>
          Add to cart
        </Btn>
      </div>
    </Card>
  );
}

function QtyControl({ value, onChange, min = 1 }) {
  const dec = () => onChange(Math.max(min, Number(value) - 1));
  const inc = () => onChange(Number(value) + 1);
  return (
    <div className="inline-flex items-stretch overflow-hidden rounded-md border border-slate-200">
      <button type="button" onClick={dec} className="px-2 text-slate-700 hover:bg-slate-50">–</button>
      <input
        type="number"
        min={min}
        value={value}
        onChange={(e) => onChange(Math.max(min, Number(e.target.value) || min))}
        className="w-16 text-center outline-none border-x border-slate-200"
      />
      <button type="button" onClick={inc} className="px-2 text-slate-700 hover:bg-slate-50">+</button>
    </div>
  );
}

export default function Buy() {
  const { catalog = [], defaults = {}, errors: serverErrors = {}, flash = {} } = usePage().props;

  const [cart, setCart] = useState([]);
  const [coupon, setCoupon] = useState("");
  const [shipping, setShipping] = useState(defaults?.shipping ?? 49);
  const [processing, setProcessing] = useState(false);

  const addToCart = (p) => {
    setCart((old) => {
      const idx = old.findIndex((x) => x.id === p.id);
      if (idx >= 0) {
        const next = [...old];
        next[idx] = { ...next[idx], qty: next[idx].qty + 1 };
        return next;
      }
      return [...old, { id: p.id, name: p.name, price: p.price, qty: 1, img: p.img, variant: p.variant, type: p.type }];
    });
    console.log("Added:", p.name);
  };

  const updateQty = (id, qty) => {
    setCart((old) =>
      old
        .map((r) => (r.id === id ? { ...r, qty: Math.max(1, Number(qty) || 1) } : r))
        .filter((r) => r.qty > 0)
    );
  };
  const removeItem = (id) => setCart((old) => old.filter((r) => r.id !== id));
  const clearCart = () => setCart([]);

  const subTotal = useMemo(() => cart.reduce((s, r) => s + Number(r.price) * Number(r.qty), 0), [cart]);
  const discount = useMemo(() => {
    const c = (coupon || "").trim().toUpperCase();
    return c === "FLAT10" ? Math.round(subTotal * 0.1) : 0;
  }, [coupon, subTotal]);
  const taxable = Math.max(0, subTotal - discount);
  const tax = Math.round(taxable * 0.05);
  const grand = taxable + tax + (cart.length ? Number(shipping || 0) : 0);

  const checkout = () => {
    if (!cart.length) {
      alert("Cart is empty");
      return;
    }

    const payload = {
      items: cart.map(({ id, name, price, qty, img, variant, type }) => ({
        id, name, price, qty, img, variant, type,
      })),
      coupon: coupon ? coupon.trim() : null,
      shipping: Number(shipping || 0),
    };

    console.log("POST /checkout", payload);
    setProcessing(true);

    // Ziggy की dependency हटाई — direct path यूज़ किया
    router.post("/checkout", payload, {
      preserveScroll: true,
      onSuccess: () => {
        clearCart();
        console.log("Order placed OK");
      },
      onError: (errs) => {
        console.warn("Checkout validation errors:", errs);
      },
      onFinish: () => setProcessing(false),
    });
  };

  return (
    <AuthenticatedLayout header={
      <div className="flex items-center gap-2">
        <h2 className="text-xl font-semibold leading-tight text-slate-800">Buy</h2>
        {/* small cart chip so you can SEE items are added */}
        <span className="ml-2 rounded-full bg-slate-100 text-slate-700 text-xs px-2 py-0.5">
          Cart: {cart.reduce((a, r) => a + r.qty, 0)}
        </span>
      </div>
    }>
      <Head title="Buy" />

      <div className="mx-auto max-w-6xl px-3 sm:px-6 lg:px-8 py-6 space-y-6">
        {flash?.success && (
          <div className="rounded-md bg-emerald-50 text-emerald-700 px-3 py-2">{flash.success}</div>
        )}

        {/* Catalog */}
        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
          {catalog.map((p) => (
            <ProductCard key={p.id} p={p} onAdd={addToCart} />
          ))}
        </div>

        {/* Cart */}
        <Card>
          <div className="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
            <div className="font-semibold text-slate-800">Your Cart</div>
            <Btn onClick={checkout} disabled={!cart.length || processing} className="hidden md:inline-flex">
              {processing ? "Processing…" : "Proceed to Checkout"}
            </Btn>
          </div>

          <div className="p-4">
            {!cart.length ? (
              <div className="text-slate-600 text-sm">No items yet. Add products from above.</div>
            ) : (
              <>
                {/* Mobile list */}
                <div className="space-y-3 md:hidden">
                  {cart.map((r) => (
                    <div key={r.id} className="rounded-lg border border-slate-200 p-3">
                      <div className="flex items-center gap-3">
                        <img
                          src={r.img}
                          alt={r.name}
                          className="h-12 w-12 rounded object-cover border"
                          onError={(e) => (e.currentTarget.src = "/images/placeholder.png")}
                        />
                        <div className="flex-1 min-w-0">
                          <div className="font-medium text-slate-900 truncate">{r.name}</div>
                          <div className="text-xs text-slate-500">{r.variant}</div>
                          <div className="mt-1 text-xs uppercase text-slate-500">{r.type}</div>
                        </div>
                        <button onClick={() => removeItem(r.id)} className="text-rose-600 text-xs underline">
                          Remove
                        </button>
                      </div>
                      <div className="mt-3 flex items-center justify-between">
                        <div className="text-slate-600 text-sm">
                          Price: <span className="font-medium">₹ {INR(r.price)}</span>
                        </div>
                        <QtyControl value={r.qty} onChange={(q) => updateQty(r.id, q)} />
                      </div>
                      <div className="mt-2 flex items-center justify-between">
                        <span className="text-slate-600 text-sm">Total</span>
                        <span className="font-semibold">₹ {INR(r.price * r.qty)}</span>
                      </div>
                    </div>
                  ))}
                </div>

                {/* Desktop table */}
                <div className="hidden md:block overflow-x-auto">
                  <table className="min-w-full text-sm">
                    <thead>
                      <tr className="text-left text-slate-500 border-b">
                        <th className="py-2 pr-4">Product</th>
                        <th className="py-2 pr-4">Type</th>
                        <th className="py-2 pr-4">Price</th>
                        <th className="py-2 pr-4">Qty</th>
                        <th className="py-2 pr-4">Total</th>
                        <th className="py-2" />
                      </tr>
                    </thead>
                    <tbody>
                      {cart.map((r) => (
                        <tr key={r.id} className="border-b last:border-0">
                          <td className="py-2 pr-4">
                            <div className="flex items-center gap-2">
                              <img
                                src={r.img}
                                alt={r.name}
                                className="h-10 w-10 rounded object-cover border"
                                onError={(e) => (e.currentTarget.src = "/images/placeholder.png")}
                              />
                              <div>
                                <div className="font-medium text-slate-900">{r.name}</div>
                                <div className="text-xs text-slate-500">{r.variant}</div>
                              </div>
                            </div>
                          </td>
                          <td className="py-2 pr-4 uppercase">{r.type}</td>
                          <td className="py-2 pr-4">₹ {INR(r.price)}</td>
                          <td className="py-2 pr-4">
                            <QtyControl value={r.qty} onChange={(q) => updateQty(r.id, q)} />
                          </td>
                          <td className="py-2 pr-4 font-medium">₹ {INR(r.price * r.qty)}</td>
                          <td className="py-2">
                            <button onClick={() => removeItem(r.id)} className="text-rose-600 hover:underline" type="button">
                              Remove
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                {/* Summary */}
                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                  <div className="space-y-2">
                    <label className="block text-sm font-medium text-slate-700">Coupon</label>
                    <input
                      value={coupon}
                      onChange={(e) => setCoupon(e.target.value)}
                      placeholder="e.g. FLAT10"
                      className="w-full rounded border border-slate-200 px-3 py-2"
                    />
                  </div>
                  <div className="space-y-2">
                    <label className="block text-sm font-medium text-slate-700">Shipping</label>
                    <input
                      type="number"
                      min={0}
                      value={shipping}
                      onChange={(e) => setShipping(e.target.value)}
                      className="w-full rounded border border-slate-200 px-3 py-2"
                    />
                  </div>
                </div>

                <div className="mt-6 grid gap-2 md:grid-cols-2">
                  <div />
                  <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <div className="flex justify-between text-sm">
                      <span className="text-slate-600">Subtotal</span>
                      <span className="font-medium text-slate-900">₹ {INR(subTotal)}</span>
                    </div>
                    <div className="flex justify-between text-sm">
                      <span className="text-slate-600">Discount</span>
                      <span className="font-medium text-slate-900">₹ {INR(discount)}</span>
                    </div>
                    <div className="flex justify-between text-sm">
                      <span className="text-slate-600">Tax (5%)</span>
                      <span className="font-medium text-slate-900">₹ {INR(tax)}</span>
                    </div>
                    <div className="flex justify-between text-sm">
                      <span className="text-slate-600">Shipping</span>
                      <span className="font-medium text-slate-900">₹ {INR(cart.length ? shipping : 0)}</span>
                    </div>
                    <div className="mt-2 border-t pt-2 flex justify-between text-base">
                      <span className="font-semibold text-slate-800">Grand Total</span>
                      <span className="font-bold text-slate-900">₹ {INR(grand)}</span>
                    </div>

                    <div className="mt-3 flex gap-2 flex-col sm:flex-row sm:justify-end">
                      <button
                        type="button"
                        onClick={clearCart}
                        className="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                      >
                        Clear
                      </button>
                      <Btn onClick={checkout} disabled={!cart.length || processing} className="sm:min-w-[160px]">
                        {processing ? "Processing…" : "Proceed to Checkout"}
                      </Btn>
                    </div>
                  </div>
                </div>
              </>
            )}
          </div>
        </Card>

        {/* Mobile sticky proceed bar */}
        {cart.length > 0 && (
          <div className="fixed bottom-3 left-3 right-3 z-40 md:hidden">
            <div className="flex items-center justify-between rounded-xl bg-slate-900 text-white px-4 py-3 shadow-lg">
              <div>
                <div className="text-xs opacity-80">Payable</div>
                <div className="text-lg font-semibold">₹ {INR(grand)}</div>
              </div>
              <Btn onClick={checkout} disabled={processing} className="bg-emerald-600 hover:bg-emerald-700">
                {processing ? "Processing…" : "Proceed"}
              </Btn>
            </div>
          </div>
        )}
      </div>
    </AuthenticatedLayout>
  );
}
