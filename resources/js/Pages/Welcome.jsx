// resources/js/Pages/Welcome.jsx
import React from "react";
import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Swiper, SwiperSlide } from "swiper/react";
import { Autoplay } from "swiper/modules";
import "swiper/css";


const CATALOG = {
  superfruit: {
    id: 1,
    name: "Super Food (Silver)",
    img: "/image/2.png",
    variant: "30 Pic gummies(2 bottles)",
    type: "silver",

    bottles: 2,
    gummiesPerBottle: 30,
    unitPrice: 2000,
    baseTotal: 2 * 2000,      // 4000
    mrp: 3000,                // offer price
    price: 3000,              // used by cart
    discount: 4000 - 3000,    // 1000
    discountPercent: 25,
    totalGummies: 2 * 30,     // 60
    pricePerGummy: 3000 / 60, // 50
  },

  immunity: {
    id: 2,
    name: "Super Food (Gold)",
    img: "/image/10.png",
    variant: "30 Pic gummies(10 bottles)",
    type: "gold",

    bottles: 10,
    gummiesPerBottle: 30,
    unitPrice: 2000,
    baseTotal: 10 * 2000,     // 20000
    mrp: 15000,
    price: 15000,
    discount: 20000 - 15000,  // 5000
    discountPercent: 25,
    totalGummies: 10 * 30,    // 300
    pricePerGummy: 15000 / 300,
  },

  metabolism: {
    id: 3,
    name: "Super Food(Diamond)",
    img: "/image/20.png",
    variant: "30 Pic gummies(20 bottles)",
    type: "diamond",

    bottles: 20,
    gummiesPerBottle: 30,
    unitPrice: 2000,
    baseTotal: 20 * 2000,     // 40000
    mrp: 30000,
    price: 30000,
    discount: 40000 - 30000,  // 10000
    discountPercent: 25,
    totalGummies: 20 * 30,    // 600
    pricePerGummy: 30000 / 600,
  },
};




const slides = [
  {
    title: "GREEN APPLE",
    subtitle: "Detox & Freshness",
    points: [
      "Helps flush out toxins from the body.",
      "Keeps the liver clean and healthy.",
      "Improves digestion.",
      "Provides instant energy.",
      "Gives the skin a natural glow and freshness.",
      "Helps in weight management.",
      "Regulates blood sugar levels.",
      "Strengthens the heart and keeps it healthy.",
    ],
    color: "bg-green-100 text-green-700",
  },
  {
    title: "GRAPE SEED",
    subtitle: "Blood Flow & Immunity",
    points: [
      "Improves blood circulation.",
      "Helps in controlling blood pressure.",
      "Keeps the heart healthy and strong.",
      "Boosts immunity and helps fight diseases.",
      "Reduces oxidative stress and toxins.",
      "Maintains healthy cholesterol levels.",
      "Improves energy and stamina.",
      "Supports overall wellness.",
    ],
    color: "bg-purple-100 text-purple-700",
  },
  {
    title: "BLUEBERRY",
    subtitle: "Brain & Heart Health",
    points: [
      "Boosts memory power and brain function.",
      "Improves concentration and focus.",
      "Helps reduce mental stress and fatigue.",
      "Maintains heart health and regulates cholesterol.",
      "Rich in antioxidants that slow aging.",
      "Improves vision and eye health.",
      "Strengthens the immune system.",
      "Protects cells from damage and improves skin glow.",
    ],
    color: "bg-blue-100 text-blue-700",
  },
  {
    title: "NONI",
    subtitle: "Ancient Vitality Booster",
    points: [
      "Improves energy and reduces tiredness.",
      "Boosts immunity and overall wellness.",
      "Helps in joint pain and muscle strength.",
      "Promotes better sleep and relaxation.",
      "Reduces inflammation in the body.",
      "Helps regulate blood sugar levels.",
      "Supports digestion and gut health.",
      "Improves skin quality and natural glow.",
    ],
    color: "bg-yellow-100 text-yellow-700",
  },
  {
    title: "CRANBERRY",
    subtitle: "Skin & Urinary Health",
    points: [
      "Improves kidney and urinary health.",
      "Helps prevent urinary tract infections (UTIs).",
      "Improves digestion and stomach function.",
      "Strengthens the immune system.",
      "Maintains oral health and prevents gum issues.",
      "Improves skin health and glow.",
      "Boosts energy and stamina.",
      "Protects from harmful bacteria growth.",
    ],
    color: "bg-red-100 text-red-700",
  },
  {
    title: "ACAI BERRY",
    subtitle: "Anti-Aging & Metabolism",
    points: [
      "Slows down signs of aging naturally.",
      "Improves metabolism and fat burning.",
      "Boosts stamina and physical performance.",
      "Helps maintain healthy cholesterol levels.",
      "Strengthens immunity and energy levels.",
      "Rich in antioxidants to fight free radicals.",
      "Supports healthy skin and glow.",
      "Improves digestion and gut health.",
    ],
    color: "bg-indigo-100 text-indigo-700",
  },
];

function ImageSlider() {
  return (
    <Swiper
      modules={[Autoplay]}
      autoplay={{ delay: 3500, disableOnInteraction: false }}
      loop
      className="w-full h-[380px] sm:h-[420px] md:h-[460px] rounded-2xl overflow-hidden shadow-xl bg-white"
    >
      {slides.map((item, i) => (
        <SwiperSlide
          key={i}
          className={`flex flex-col items-center justify-center ${item.color} p-6 text-center`}
        >
          <h2 className="text-2xl sm:text-3xl font-bold">{item.title}</h2>
          <h3 className="mt-1 text-lg sm:text-xl font-semibold">{item.subtitle}</h3>
          <ul className="mt-3 text-sm sm:text-base max-w-lg text-left space-y-1">
            {item.points.map((p, idx) => (
              <li key={idx} className="flex items-start gap-2">
                <span>✅</span>
                <span>{p}</span>
              </li>
            ))}
          </ul>
        </SwiperSlide>
      ))}
    </Swiper>
  );
}


/* ----------------- UI helpers ----------------- */
const Section = ({ children, className = "" }) => (
  <section className={`mx-auto w-full max-w-[1230px] px-3 sm:px-4 ${className}`}>{children}</section>
);

const SectionTitle = ({ eyebrow, title, desc }) => (
  <div className="text-center mb-8 sm:mb-10">
    {eyebrow && (
      <div className="inline-block rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-[11px] sm:text-xs font-medium text-sky-700">
        {eyebrow}
      </div>
    )}
    <h2 className="mt-3 text-2xl sm:text-3xl font-bold tracking-tight text-slate-900">{title}</h2>
    {desc && <p className="mt-2 text-slate-600 max-w-2xl mx-auto text-sm sm:text-base">{desc}</p>}
  </div>
);

const Check = () => (
  <svg className="h-5 w-5 text-sky-600 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden>
    <path d="M9 16.17l-3.88-3.88-1.42 1.41L9 19 21.3 6.7l-1.41-1.41z" />
  </svg>
);

const LinkButton = ({ href = "#", children, variant = "primary" }) => {
  const base =
    "inline-flex items-center justify-center rounded-lg px-5 py-2.5 text-sm font-semibold transition shadow focus:outline-none focus:ring-2 focus:ring-offset-2";
  const styles = {
    primary:
      "bg-gradient-to-r from-cyan-600 via-sky-600 to-blue-600 text-white hover:from-cyan-700 hover:to-blue-700 focus:ring-sky-300",
    outline: "bg-white text-sky-700 ring-1 ring-sky-300 hover:bg-sky-50 focus:ring-sky-300",
    subtle: "bg-sky-50 text-sky-700 hover:bg-sky-100 ring-1 ring-sky-100",
  };
  return (
    <a href={href} className={`${base} ${styles[variant]}`}>
      {children}
    </a>
  );
};

/* ----------------- Add to Cart (localStorage) ----------------- */
const CART_KEY = "cart";
function parseTypeFromName(name) {
  const m = String(name).match(/\(([^)]+)\)\s*$/);
  return m ? m[1].trim().toLowerCase() : null;
}
function addToCart(product, goToCart = true) {
  try {
    const raw = localStorage.getItem(CART_KEY);
    const cart = raw ? JSON.parse(raw) : [];
    const idx = cart.findIndex((it) => it.id === product.id);
    const type = product.type || parseTypeFromName(product.name);

    if (idx > -1) {
      cart[idx].qty += 1;
    } else {
      cart.push({
        id: product.id,
        name: product.name,
        price: Number(product.price), // ✅ MRP as number
        qty: 1,
        img: product.img,
        variant: product.variant || null,
        type, // ✅ carry type to backend
      });
    }
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    if (goToCart) window.location.href = "/card";
  } catch (e) {
    console.error("Add to cart failed", e);
    alert("Could not add to cart. Please try again.");
  }
}

/* ----------------- Product Card ----------------- */
function ProductCard({ product, bullets = [] }) {
  const { img, name, price, variant, type, baseTotal, discount, discountPercent } = product;
  return (
    <div className="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:shadow-md transition">
      <div className="relative overflow-hidden rounded-xl aspect-square bg-sky-50">
        <img
          src={img}
          alt={name}
          loading="lazy"
          className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
        />
        <div className="absolute left-3 top-3 rounded-md bg-sky-600/90 px-2 py-1 text-xs font-medium text-white">
          Bestseller
        </div>
      </div>
      <div className="mt-4">
        <h3 className="text-lg font-semibold text-slate-900">{name}</h3>
        {variant && <p className="mt-1 text-sm text-slate-600">{variant}</p>}
        {type && (
          <p className="text-xs text-slate-500">
            Type: <span className="uppercase">{type}</span>
          </p>
        )}
        <ul className="mt-3 space-y-1 text-sm text-sky-700">
          {bullets.slice(0, 3).map((b, i) => (
            <li className="flex items-center gap-2" key={i}>
              <span className="text-base">✅</span> {b}
            </li>
          ))}
        </ul>
        <div className="mt-4 flex items-center justify-between">
          <div>
            <div className="text-xs text-slate-500 line-through">₹{baseTotal.toLocaleString()}</div>
            <div className="text-xl font-bold text-slate-900">₹{price.toLocaleString()}</div>
            <div className="text-xs text-green-700 mt-0.5">
              Save ₹{discount.toLocaleString()} ({discountPercent}%)
            </div>
          </div>
          <div className="flex gap-2">
            <button
              onClick={() => addToCart(product, true)}
              className="inline-flex items-center justify-center rounded-lg bg-gradient-to-r from-cyan-600 via-sky-600 to-blue-600 px-5 py-2.5 text-white font-semibold hover:from-cyan-700 hover:to-blue-700"
              type="button"
            >
              Buy Now
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ----------------- Page ----------------- */
export default function Welcome() {
  return (
    <AuthenticatedLayout>
      <Head title="Welcome" />

      <div className="bg-slate-50">
        {/* Optional: Slider on top */}
        <Section className="mt-6">
          <ImageSlider />
        </Section>

        {/* Product Information */}
        <Section className="mt-8 sm:mt-12" id="benefits">
          <SectionTitle
            eyebrow="Why choose Cellvada"
            title="Product Information"
            desc="A powerhouse combination of superfruits for daily detox, immunity and skin health."
          />

          <div className="rounded-3xl border border-slate-200 bg-white p-5 sm:p-8 shadow-lg">
            <div className="grid gap-6 md:grid-cols-[1fr_1.05fr] items-center">
              <div className="rounded-xl overflow-hidden bg-sky-50">
                <img
                  src="/image/AAAA.png"
                  alt="Cellvada product packaging"
                  className="object-cover"
                  loading="lazy"
                  onError={(e) => (e.currentTarget.style.display = "none")}
                />
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                {[
                  "Green Apple — Detox & Freshness",
                  "Blueberry — Cell Repair",
                  "Cranberry — Skin & Urinal Health",
                  "Grape Seed — Blood Flow & Immunity",
                  "Noni — Energy & Vita",
                  "Acai Berry — Anti-Aging & Metabolism",
                ].map((t, i) => (
                  <div className="flex items-start gap-3" key={i}>
                    <Check />
                    <div className="text-[15px] leading-snug text-slate-700">{t}</div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </Section>

        {/* Featured Product */}
        <Section className="mt-10 sm:mt-12">
          <div className="rounded-3xl bg-white shadow-lg ring-1 ring-slate-100 p-6 sm:p-8">
            <div className="grid md:grid-cols-2 gap-8 items-center">
              <div className="flex justify-center md:justify-start">
                <img
                  src={CATALOG.superfruit.img}
                  alt={CATALOG.superfruit.name}
                  className="w-[320px] sm:w-[360px] md:w-[400px] h-[320px] sm:h-[360px] md:h-[400px] object-cover rounded-2xl shadow-md bg-sky-600/10"
                  loading="lazy"
                />
              </div>

              <div>
                <h3 className="text-2xl sm:text-3xl font-bold text-slate-900 mb-3">
                  {CATALOG.superfruit.name}
                </h3>
                <p className="text-slate-600 mb-4">
                  Type: <b className="uppercase">{CATALOG.superfruit.type}</b>
                </p>
                <div className="flex items-center gap-6">
                  <div>
                    <div className="text-sm text-slate-500 line-through">
                      ₹{CATALOG.superfruit.baseTotal.toLocaleString()}
                    </div>
                    <div className="text-2xl font-semibold text-slate-900">
                      ₹{CATALOG.superfruit.price.toLocaleString()}
                    </div>
                    <div className="text-xs text-green-700 mt-1">
                      Save ₹{CATALOG.superfruit.discount.toLocaleString()} (
                      {CATALOG.superfruit.discountPercent}%)
                    </div>
                  </div>
                  <button
                    onClick={() => addToCart(CATALOG.superfruit, true)}
                    className="inline-flex items-center justify-center rounded-lg bg-gradient-to-r from-cyan-600 via-sky-600 to-blue-600 px-5 py-2.5 text-white font-semibold hover:from-cyan-700 hover:to-blue-700"
                    type="button"
                  >
                    Buy Now
                  </button>
                </div>
              </div>
            </div>
          </div>
        </Section>

        {/* Product Grid */}
        <Section className="mt-10 sm:mt-14" id="products">
          <SectionTitle eyebrow="Top Picks" title="Featured Products" desc="Hand-picked favorites our customers love." />
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6">
            <ProductCard
              product={CATALOG.superfruit}
              bullets={["Detox & Freshness", "Cell Repair", "Skin Health"]}
            />
            <ProductCard
              product={CATALOG.immunity}
              bullets={["Grape Seed", "Cranberry", "Noni Energy"]}
            />
            <ProductCard
              product={CATALOG.metabolism}
              bullets={["Acai Berry", "Green Apple", "Blueberry"]}
            />
          </div>
        </Section>

        {/* Footer */}
        <footer className="bg-slate-950 text-slate-300 py-10 mt-12">
          <Section>
            <div className="text-center text-sm">
              © {new Date().getFullYear()} Cellvada. All rights reserved.
            </div>
          </Section>
        </footer>
      </div>
    </AuthenticatedLayout>
  );
}
