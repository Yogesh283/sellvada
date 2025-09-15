<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamTreeController extends Controller
{
    // app/Http/Controllers/TeamTreeController.php
    public function show(Request $req)
    {
        $viewerId = $req->user()->id;
        $rootId   = (int) ($req->route('root') ?? $viewerId);
        $type     = strtolower($req->query('type', 'placement')); // default: placement

        $seed = DB::table('users')
            ->where('id', $rootId)
            ->select('id','name','email','referral_id','left_user_id','right_user_id','parent_id')
            ->first();
        if (!$seed) abort(404);

        /* ---------- AUTH GUARD (optional): root must be in viewer's downline ---------- */
        if ($rootId !== $viewerId) {
            // Placement-based check so users can't jump out of their binary
            $inPlacement = DB::select("
                WITH RECURSIVE place AS (
                    SELECT id, left_user_id, right_user_id
                    FROM users WHERE id = ?
                    UNION ALL
                    SELECT u.id, u.left_user_id, u.right_user_id
                    FROM users u
                    JOIN place p ON u.id IN (p.left_user_id, p.right_user_id)
                )
                SELECT id FROM place
            ", [$viewerId]);
            if (!collect($inPlacement)->pluck('id')->contains($rootId)) abort(403);
        }

        /* ---------- 1) FETCH DESCENDANTS (placement vs referral) ---------- */

        if ($type === 'referral') {
            // === REFERRAL tree (sponsor chain) ===
            $myCode = (string)$seed->referral_id;
            $rows = DB::select("
                WITH RECURSIVE team AS (
                    SELECT id, name, email, referral_id, refer_by, position, 1 AS lvl
                    FROM users WHERE refer_by = ?
                    UNION ALL
                    SELECT u.id, u.name, u.email, u.referral_id, u.refer_by, u.position, t.lvl+1
                    FROM users u
                    JOIN team t ON u.refer_by = t.referral_id
                )
                SELECT id, name, email, referral_id, refer_by, position, lvl
                FROM team
            ", [$myCode]);

            // parent resolve by refer_by (code), side by position
            $infoById = [
                $seed->id => [
                    'id'=>(int)$seed->id,'name'=>$seed->name,'code'=>$seed->referral_id,
                    'email'=>$seed->email,'package'=>null,
                ],
            ];
            foreach ($rows as $r) {
                $infoById[(int)$r->id] = [
                    'id'=>(int)$r->id,'name'=>$r->name,'code'=>$r->referral_id,
                    'email'=>$r->email,'package'=>null,
                ];
            }
            $codeToId = [];
            foreach ($infoById as $id=>$n) $codeToId[$n['code']] = $id;

            $childMap = []; // parentId => ['L'=>id,'R'=>id]
            foreach ($rows as $r) {
                $pid = $codeToId[$r->refer_by] ?? null; if (!$pid) continue;
                $pos = strtoupper((string)$r->position)==='L'?'L':'R';
                $childMap[$pid] = $childMap[$pid] ?? ['L'=>null,'R'=>null];
                if ($childMap[$pid][$pos]===null) $childMap[$pid][$pos]=(int)$r->id;
            }

        } else {
            // === PLACEMENT tree (binary pointers) — DEFAULT ===
            $rows = DB::select("
                WITH RECURSIVE place AS (
                    SELECT id, name, email, referral_id, left_user_id, right_user_id, 0 AS lvl
                    FROM users WHERE id = ?
                    UNION ALL
                    SELECT u.id, u.name, u.email, u.referral_id, u.left_user_id, u.right_user_id, p.lvl+1
                    FROM users u
                    JOIN place p ON u.id IN (p.left_user_id, p.right_user_id)
                )
                SELECT id, name, email, referral_id, left_user_id, right_user_id, lvl
                FROM place
            ", [$seed->id]);

            // flat info & child map from left/right pointers
            $infoById = [];
            $childMap = [];
            foreach ($rows as $r) {
                $id = (int)$r->id;
                $infoById[$id] = [
                    'id'=>$id,'name'=>$r->name,'code'=>$r->referral_id,
                    'email'=>$r->email,'package'=>null,
                ];
                $childMap[$id] = [
                    'L' => (!is_null($r->left_user_id)  && (int)$r->left_user_id  !== 0) ? (int)$r->left_user_id  : null,
                    'R' => (!is_null($r->right_user_id) && (int)$r->right_user_id !== 0) ? (int)$r->right_user_id : null,
                ];
            }
        }

        /* ---------- 2) (optional) packages from `sell` ---------- */
        $allIds = array_keys($infoById);
        if (empty($allIds)) {
            // nothing to do
            $root = null;
            $counts = [
                'total_nodes'=>0,'left_nodes'=>0,'right_nodes'=>0,
                'pkg'=>['starter'=>0,'silver'=>0,'gold'=>0,'diamond'=>0],
            ];
            return Inertia::render('TeamTree', [
                'root'=>$root,
                'counts'=>$counts,
                'seed'=>['id'=>$seed->id,'name'=>$seed->name,'code'=>$seed->referral_id],
                'type'=>$type,
            ]);
        }

        // ordering: 0 starter, 1 silver, 2 gold, 3 diamond
        $order  = "CASE LOWER(type) WHEN 'starter' THEN 0 WHEN 'silver' THEN 1 WHEN 'gold' THEN 2 WHEN 'diamond' THEN 3 ELSE 0 END";

        // get max lvl per buyer (only paid sells, and only canonical types)
        $lvlById = DB::table('sell')
            ->select('buyer_id', DB::raw("MAX($order) AS lvl"))
            ->where('status','paid')
            ->whereIn(DB::raw('LOWER(type)'), ['starter','silver','gold','diamond'])
            ->whereIn('buyer_id', $allIds)
            ->groupBy('buyer_id')
            ->pluck('lvl','buyer_id');

        // map numeric lvl to package name (0 -> starter, etc.)
        $toPkg = static fn (int $lvl) => $lvl>=3 ? 'diamond' : ($lvl===2 ? 'gold' : ($lvl===1 ? 'silver' : 'starter'));

        // IMPORTANT: assign package ONLY if there is a paid sell entry for that buyer.
        foreach ($infoById as $id => &$n) {
            if (isset($lvlById[$id])) {
                // lvlById present -> map to package
                $n['package'] = $toPkg((int)$lvlById[$id]);
            } else {
                // no paid sells found for this buyer -> keep package null
                $n['package'] = null;
            }
        }
        unset($n);

        /* ---------- 3) Build nested tree from maps ---------- */
        $makeNode = function (int $id) use (&$makeNode, $infoById, $childMap) {
            if (!isset($infoById[$id])) return null;
            $base = $infoById[$id];
            $Lid  = $childMap[$id]['L'] ?? null;
            $Rid  = $childMap[$id]['R'] ?? null;
            return [
                'id'=>$base['id'],'name'=>$base['name'],'code'=>$base['code'],
                'email'=>$base['email'],'package'=>$base['package'],
                'children'=>[
                    'L' => $Lid ? $makeNode($Lid) : null,
                    'R' => $Rid ? $makeNode($Rid) : null,
                ],
            ];
        };
        $root = $makeNode($seed->id);

        /* ---------- 4) Counts ---------- */
        $counts = [
            'total_nodes'=>0,'left_nodes'=>0,'right_nodes'=>0,
            'pkg'=>['starter'=>0,'silver'=>0,'gold'=>0,'diamond'=>0],
        ];
        $walk = function ($n, $side=null) use (&$walk,&$counts) {
            if (!$n) return;
            if ($side) {
                $counts['total_nodes']++;
                $side==='L' ? $counts['left_nodes']++ : $counts['right_nodes']++;
                if (!empty($n['package']) && isset($counts['pkg'][$n['package']])) $counts['pkg'][$n['package']]++;
            }
            if (!empty($n['children']['L'])) $walk($n['children']['L'], $side ?: 'L');
            if (!empty($n['children']['R'])) $walk($n['children']['R'], $side ?: 'R');
        };
        $walk($root);

        return Inertia::render('TeamTree', [
            'root'=>$root,
            'counts'=>$counts,
            'seed'=>['id'=>$seed->id,'name'=>$seed->name,'code'=>$seed->referral_id],
            'type'=>$type,
        ]);
    }
}
