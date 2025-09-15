import React, { useState } from "react";
import { Link, usePage } from "@inertiajs/react";

/**
 * TeamTreeNode.jsx
 * - Uses backend package string exactly for badge display (displayPkg)
 * - Uses normalized package string for THEMES lookup (normalizedPkg)
 */

const THEMES = {
  diamond: {
    frame: "bg-emerald-500 border-emerald-600 text-white",
    badge: "bg-emerald-600 text-white",
    icon: "bg-emerald-400 text-white",
    stroke: "#34d399",
  },
  gold: {
    frame: "bg-amber-400 border-amber-500 text-white",
    badge: "bg-amber-500 text-white",
    icon: "bg-amber-300 text-white",
    stroke: "#fbbf24",
  },
  silver: {
    frame: "bg-gray-400 border-gray-500 text-white",
    badge: "bg-gray-500 text-white",
    icon: "bg-gray-300 text-white",
    stroke: "#94a3b8",
  },
  starter: {
    frame: "bg-red-500 border-red-600 text-white",
    badge: "bg-red-600 text-white",
    icon: "bg-red-400 text-white",
    stroke: "#ef4444",
  },
  default: {
    frame: "bg-white border-gray-300 text-gray-700",
    badge: "bg-gray-100 text-gray-600",
    icon: "bg-gray-200 text-gray-600",
    stroke: "#d1d5db",
  },
};

const VConn = ({ color = "#d1d5db", h = 16 }) => (
  <svg width="8" height={h} className="block">
    <line
      x1="4"
      y1="0"
      x2="4"
      y2={h}
      stroke={color}
      strokeWidth="3"
      strokeLinecap="round"
    />
  </svg>
);

const HConn = ({ color = "#d1d5db", w = 60 }) => (
  <svg width={w} height="8" className="block">
    <line
      x1="0"
      y1="4"
      x2={w}
      y2="4"
      stroke={color}
      strokeWidth="3"
      strokeLinecap="round"
    />
  </svg>
);

/** small helper: if backend sends null/empty, show fallback */
const safeDisplay = (s) => (s === null || typeof s === "undefined" ? "" : String(s));

function Box({ title, id, pkgNormalized, pkgDisplay, open, onToggle, href, isRoot }) {
  const t = THEMES[pkgNormalized] || THEMES.default;
  return (
    <div className="relative w-[100px] sm:w-[112px]">
      <button
        type="button"
        onClick={!isRoot ? onToggle : undefined}
        className={`mx-auto rounded-xl sm:rounded-2xl border ${t.frame} shadow-md w-[100px] sm:w-[112px] h-[56px] sm:h-[64px] flex items-center justify-center transition active:scale-[0.99] ring-1`}
        title={open ? "Collapse" : "Expand"}
      >
        {!isRoot && (
          <div className="absolute left-1 top-1 text-[10px] text-white/80">
            {open ? "▾" : "▸"}
          </div>
        )}
        <div
          className={`w-6 h-6 sm:w-7 sm:h-7 rounded-full flex items-center justify-center ${t.icon}`}
        >
          <span className="text-xs sm:text-sm">👤</span>
        </div>

        <div
          className={`absolute top-1 right-1 text-[8px] px-1.5 rounded-full truncate max-w-[70px] ${t.badge}`}
          title={safeDisplay(pkgDisplay)}
        >
          {safeDisplay(pkgDisplay) || "No-Pack"}
        </div>
      </button>

      <div className="w-full text-center mt-1">
        <div className="text-[9px] truncate">{title}</div>
        <div className="text-[8px] text-gray-500">#{id}</div>
      </div>

      {href && (
        <Link
          href={href}
          className="absolute -right-1 -bottom-1 text-[9px] bg-white/70 px-1 rounded hover:bg-white"
        >
          ↗
        </Link>
      )}
    </div>
  );
}

export default function TeamTreeNode({ node, isRoot = false }) {
  const { props } = usePage();
  const type = props?.type || "placement";
  const [open, setOpen] = useState(isRoot);

  if (!node) return null;

  const title = node.name || node.code || `#${node.id}`;
  const hrefFor = (id) => {
    try {
      return route("team.tree", { root: id, type });
    } catch {
      return `/team/tree/${id}?type=${type}`;
    }
  };

  // --- PACKAGE: keep raw backend value for display, normalize only for theme lookup
  const rawPkg = node.package ?? node.pkg ?? null;
  const displayPkg = rawPkg; // show exactly what backend sent
  const normalizedPkg = typeof rawPkg === "string" ? rawPkg.toLowerCase().trim() : null;

  const showChildren = open && (node.children?.L || node.children?.R);
  const theme = THEMES[normalizedPkg] || THEMES.default;

  const wrapperCls = isRoot
    ? "flex flex-col items-center mx-auto"
    : "inline-flex flex-col items-center";

  return (
    <div className={wrapperCls}>
      <Box
        title={title}
        id={node.id}
        pkgNormalized={normalizedPkg}
        pkgDisplay={displayPkg}
        open={open}
        onToggle={() => setOpen((v) => !v)}
        href={hrefFor(node.id)}
        isRoot={isRoot}
      />

      {showChildren && <VConn color={theme.stroke} h={14} />}

      {showChildren && (
        <div className="flex items-start gap-4 sm:gap-7">
          {/* LEFT */}
          <div className="flex flex-col items-center">
            <HConn color={theme.stroke} />
            {node.children?.L ? (
              <TeamTreeNode node={node.children.L} />
            ) : (
              <div className="rounded-xl border border-purple-300 bg-purple-50 w-[100px] h-[56px] flex items-center justify-center">
                <div className="text-[10px] text-purple-600">👥➕</div>
              </div>
            )}
          </div>

          {/* RIGHT */}
          <div className="flex flex-col items-center">
            <HConn color={theme.stroke} />
            {node.children?.R ? (
              <TeamTreeNode node={node.children.R} />
            ) : (
              <div className="rounded-xl border border-purple-300 bg-purple-50 w-[100px] h-[56px] flex items-center justify-center">
                <div className="text-[10px] text-purple-600">👥➕</div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
