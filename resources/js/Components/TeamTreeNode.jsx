import React, { useState } from "react";
import { Link, usePage } from "@inertiajs/react";

/** Solid theme per package (full color) + SVG stroke color */
const COLORS = {
  diamond: "#34d399", // emerald-400
  gold:    "#fbbf24", // amber-400
  silver:  "#94a3b8", // slate-400
  default: "#d1d5db",
};

const THEMES = {
  diamond: {
    frame: "border-emerald-400 bg-emerald-100 ring-emerald-200",
    badge: "bg-emerald-200 text-emerald-900",
    icon:  "bg-emerald-200 text-emerald-800",
    stroke: COLORS.diamond,
  },
  gold: {
    frame: "border-amber-400 bg-amber-100 ring-amber-200",
    badge: "bg-amber-200 text-amber-900",
    icon:  "bg-amber-200 text-amber-800",
    stroke: COLORS.gold,
  },
  silver: {
    frame: "border-slate-400 bg-slate-100 ring-slate-200",
    badge: "bg-slate-200 text-slate-800",
    icon:  "bg-slate-200 text-slate-700",
    stroke: COLORS.silver,
  },
  default: {
    frame: "border-gray-200 bg-white ring-gray-200",
    badge: "bg-gray-100 text-gray-600",
    icon:  "bg-gray-100 text-gray-600",
    stroke: COLORS.default,
  },
};

/* SVG connectors (rounded, thicker) */
const VConn = ({ color = COLORS.default, h = 16 }) => (
  <svg width="8" height={h} className="block">
    <line x1="4" y1="0" x2="4" y2={h} stroke={color} strokeWidth="3" strokeLinecap="round" />
  </svg>
);

const HConn = ({ color = COLORS.default, w = 80 }) => (
  <svg width={w} height="8" className="block">
    <line x1="0" y1="4" x2={w} y2="4" stroke={color} strokeWidth="3" strokeLinecap="round" />
  </svg>
);

/** Compact member box */
function Box({ title, id, pkg, open, onToggle, href }) {
  const t = THEMES[pkg] || THEMES.default;

  return (
    <div className="relative w-[112px]">
      <button
        type="button"
        onClick={onToggle}
        className={`mx-auto rounded-2xl border ${t.frame} shadow-sm w-[112px] h-[64px] flex items-center justify-center hover:shadow-md transition active:scale-[0.99] ring-1`}
        title={open ? "Collapse" : "Expand"}
      >
        {/* caret */}
        <div className="absolute left-1.5 top-1.5 text-[11px] text-gray-500 select-none">
          {open ? "▾" : "▸"}
        </div>

        {/* avatar */}
        <div className={`w-7 h-7 rounded-full flex items-center justify-center ${t.icon}`}>
          <span className="text-sm">👤</span>
        </div>

        {/* package badge */}
        <div
          className={`absolute top-1 right-1 text-[9px] px-2 py-[2px] rounded-full capitalize whitespace-nowrap max-w-[84px] truncate text-center ${t.badge}`}
          title={pkg || "no-pack"}
        >
          {pkg ?? "No-Pack"}
        </div>
      </button>

      {/* label under box */}
      <div className="w-full text-center leading-tight mt-1">
        <div className="text-[10px] text-gray-800 truncate px-1" title={title}>
          {title}
        </div>
        <div className="text-[9px] text-gray-400">#{id}</div>
      </div>

      {/* tiny link to open full subtree page */}
      {href && (
        <Link
          href={href}
          className="absolute -right-1 -bottom-1 text-[10px] rounded-md bg-gray-100 px-1.5 py-[2px] text-gray-600 hover:bg-gray-200"
          title="Open subtree page"
        >
          ↗
        </Link>
      )}
    </div>
  );
}

export default function TeamTreeNode({ node }) {
  const { props } = usePage();
  const type = props?.type || "placement";
  const [open, setOpen] = useState(false); // collapsed by default

  if (!node) return null;

  const mainLine = node.name || node.code || `#${node.id}`;
  const userId = node.id;

  // href safe with/without Ziggy
  const hrefFor = (id) => {
    try { return route("team.tree", { root: id, type }); }
    catch { return `/team/tree/${id}?type=${type}`; }
  };

  const showChildren = open && (node.children?.L || node.children?.R);
  const theme = THEMES[node.package] || THEMES.default;

  return (
    <div className="inline-flex flex-col items-center">
      {/* current node */}
      <Box
        title={mainLine}
        id={userId}
        pkg={node.package}
        open={open}
        onToggle={() => setOpen((v) => !v)}
        href={hrefFor(userId)}
      />

      {/* vertical connector down (colored) */}
      {showChildren && <VConn color={theme.stroke} h={14} />}

      {/* children row */}
      {showChildren && (
        <div className="flex items-start gap-7">
          {/* LEFT */}
          <div className="flex flex-col items-center">
            <HConn color={theme.stroke} w={72} />
            {node.children?.L ? (
              <TeamTreeNode node={node.children.L} />
            ) : (
              <div className="rounded-2xl border border-purple-200 bg-white shadow-sm w-[112px] h-[64px] flex items-center justify-center">
                <div className="text-center text-[11px] text-purple-600">
                  <div className="text-lg leading-none">👥➕</div>
                  Add
                </div>
              </div>
            )}
          </div>

          {/* RIGHT */}
          <div className="flex flex-col items-center">
            <HConn color={theme.stroke} w={72} />
            {node.children?.R ? (
              <TeamTreeNode node={node.children.R} />
            ) : (
              <div className="rounded-2xl border border-purple-200 bg-white shadow-sm w-[112px] h-[64px] flex items-center justify-center">
                <div className="text-center text-[11px] text-purple-600">
                  <div className="text-lg leading-none">👥➕</div>
                  Add
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
