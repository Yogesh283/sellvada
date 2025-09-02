import React from "react";
import { Link, usePage } from "@inertiajs/react";

/** Single member/add box */
function Box({ title, id, pkg }) {
  const badge =
    pkg === "diamond" ? "bg-purple-200 text-purple-800" :
    pkg === "gold"    ? "bg-yellow-200 text-yellow-800" :
    pkg === "silver"  ? "bg-slate-200 text-slate-700"  :
                        "bg-gray-100 text-gray-500";

  return (
    <div className="relative w-[130px]">
      <div className="mx-auto rounded-xl border bg-white shadow-sm w-[130px] h-[78px] flex items-center justify-center hover:shadow transition">
        <div className="w-9 h-9 rounded-full flex items-center justify-center bg-emerald-100 text-emerald-700">
          <span className="text-lg">👤</span>
        </div>
        <div
          className={`absolute top-1 right-1 text-[10px] px-2 py-[2px] rounded-full capitalize whitespace-nowrap max-w-[90px] truncate text-center ${badge}`}
          title={pkg || "no-pack"}
        >
          {pkg ?? "No-Pack"}
        </div>
      </div>
      <div className="w-full text-center leading-tight mt-1">
        <div className="text-[11px] text-gray-800 truncate px-1" title={title}>
          {title}
        </div>
        <div className="text-[10px] text-gray-400">#{id}</div>
      </div>
    </div>
  );
}

export default function TeamTreeNode({ node }) {
  const { props } = usePage();
  const type = props?.type || "placement";

  if (!node) return null;

  const mainLine = node.name || node.code || `#${node.id}`;
  const userId = node.id;

  // Ziggy-safe href
  const hrefFor = (id) => {
    try {
      return route("team.tree", { root: id, type });
    } catch {
      return `/team/tree/${id}?type=${type}`;
    }
  };

  return (
    <div className="inline-flex flex-col items-center">
      {/* Current node - clickable to open its subtree */}
      <Link href={hrefFor(userId)} className="inline-block cursor-pointer">
        <Box title={mainLine} id={userId} pkg={node.package} />
      </Link>

      {(node.children?.L || node.children?.R) && (
        <div className="w-44 h-4 border-l border-gray-300" />
      )}

      {/* children row */}
      <div className="flex items-start gap-12">
        {/* LEFT */}
        <div className="flex flex-col items-center">
          <div className="w-24 h-4 border-t border-gray-300" />
          {node.children?.L ? (
            <TeamTreeNode node={node.children.L} />
          ) : (
            <div className="rounded-xl border bg-white shadow-sm w-[130px] h-[78px] flex items-center justify-center">
              <div className="text-center text-xs text-purple-600">
                <div className="text-xl">👥➕</div>
                Add
              </div>
            </div>
          )}
        </div>

        {/* RIGHT */}
        <div className="flex flex-col items-center">
          <div className="w-24 h-4 border-t border-gray-300" />
          {node.children?.R ? (
            <TeamTreeNode node={node.children.R} />
          ) : (
            <div className="rounded-xl border bg-white shadow-sm w-[130px] h-[78px] flex items-center justify-center">
              <div className="text-center text-xs text-purple-600">
                <div className="text-xl">👥➕</div>
                Add
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
