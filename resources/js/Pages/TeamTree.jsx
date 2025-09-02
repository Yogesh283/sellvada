import React from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import AuthenticatedLayout from "../Layouts/AuthenticatedLayout";
import TeamTreeNode from "../Components/TeamTreeNode";

/* Small pill chip */
const Chip = ({ children, tone = "slate" }) => {
  const toneMap = {
    slate: "bg-slate-100 text-slate-700",
    yellow: "bg-yellow-100 text-yellow-800",
    purple: "bg-purple-100 text-purple-800",
    emerald: "bg-emerald-100 text-emerald-700",
  };
  return (
    <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs ${toneMap[tone]}`}>
      {children}
    </span>
  );
};

/* Segmented control for Placement / Referral */
const ViewToggle = ({ seedId, currentType }) => {
  // keep Ziggy route() if present, else build href manually
  const hrefFor = (type) => {
    try {
      return route("team.tree", { root: seedId, type });
    } catch {
      return `/team/tree/${seedId}?type=${type}`;
    }
  };

  return (
    <div className="inline-flex rounded-2xl border bg-white p-1 shadow-sm">
      <Link
        href={hrefFor("placement")}
        className={`px-3 py-1.5 rounded-xl text-sm transition ${
          currentType === "placement"
            ? "bg-gray-900 text-white"
            : "text-gray-600 hover:bg-gray-100"
        }`}
      >
        Placement
      </Link>
      <Link
        href={hrefFor("referral")}
        className={`px-3 py-1.5 rounded-xl text-sm transition ${
          currentType === "referral"
            ? "bg-gray-900 text-white"
            : "text-gray-600 hover:bg-gray-100"
        }`}
      >
        Referral
      </Link>
    </div>
  );
};

export default function TeamTree({ root = null, counts = {}, seed, type = "placement" }) {
  const { auth } = usePage().props ?? {};

  return (
    <AuthenticatedLayout
      user={auth?.user}
      header={
        <div className="flex items-center justify-between">
          <div>
            <h2 className="font-semibold text-xl text-gray-800 leading-tight">Team Tree</h2>
            {seed && (
              <div className="text-[11px] text-gray-600">
                Viewing <span className="font-medium">{seed.name}</span>
                <span className="mx-1">(# {seed.id})</span>• {seed.code}
              </div>
            )}
          </div>
          {seed && <ViewToggle seedId={seed.id} currentType={type} />}
        </div>
      }
    >
      <Head title="Team Tree" />

      <div className="py-6">
        <div className="mx-auto max-w-[1400px] px-4 sm:px-6 lg:px-8">
          {/* Stat row */}
          <div className="mb-4 flex flex-wrap items-center gap-2 text-sm text-gray-700">
            <div className="mr-2">
              Total Team: <span className="font-medium">{counts?.total_nodes ?? 0}</span>
              <span className="mx-2">·</span>
              Left: <span className="font-medium">{counts?.left_nodes ?? 0}</span>
              <span className="mx-2">·</span>
              Right: <span className="font-medium">{counts?.right_nodes ?? 0}</span>
            </div>
            <Chip tone="slate">Silver: {counts?.pkg?.silver ?? 0}</Chip>
            <Chip tone="yellow">Gold: {counts?.pkg?.gold ?? 0}</Chip>
            <Chip tone="purple">Diamond: {counts?.pkg?.diamond ?? 0}</Chip>
            <Chip tone="emerald">View: {type === "placement" ? "Placement" : "Referral"}</Chip>
          </div>

          {/* Canvas */}
          {root ? (
            <div className="overflow-x-auto overflow-y-hidden rounded-xl border bg-white p-6 shadow-sm">
              {/* extra width so connectors don’t clip */}
              <div className="min-w-[900px]">
                <TeamTreeNode node={root} />
              </div>
            </div>
          ) : (
            <div className="rounded-xl border bg-white p-8 text-center text-gray-500">
              No team found.
            </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
