<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BinarySummaryController extends Controller
{
    /**
     * Return summary array for a given rootId and type ('placement'|'referral').
     */
    public static function getSummaryForRoot(int $rootId, string $type = 'placement', ?string $from = null, ?string $to = null): array
    {
        // date filter for sells
        $dateSql = '';
        $dateBinds = [];
        if ($from) { $dateSql .= " AND DATE(s.created_at) >= ?"; $dateBinds[] = $from; }
        if ($to)   { $dateSql .= " AND DATE(s.created_at) <= ?"; $dateBinds[] = $to; }

        // Prepare defaults
        $recent = [];
        $totalMembers = 0;
        $leftCount = 0;
        $rightCount = 0;
        $ids = [];
        $pkgCounts = ['silver'=>0,'gold'=>0,'diamond'=>0,'other'=>0];

        // Build team CTE + bindings (placement/referral)
        if (strtolower($type) === 'referral') {
            $rootReferral = (string) DB::table('users')->where('id', $rootId)->value('referral_id');
            if (!$rootReferral) {
                return [
                    'totals' => ['left' => 0.0, 'right' => 0.0],
                    'matrix' => ['silver'=>[], 'gold'=>[], 'diamond'=>[], 'other'=>[]],
                    'recent' => [],
                    'carryTotals' => ['left'=>0.0,'right'=>0.0,'total'=>0.0],
                    'counts' => ['total'=>0,'left'=>0,'right'=>0],
                    'pkg' => $pkgCounts,
                ];
            }

            $teamCte = "
WITH RECURSIVE team AS (
  SELECT id, referral_id, refer_by, position,
         UPPER(COALESCE(position,'NA')) AS root_leg
  FROM users
  WHERE refer_by = ?

  UNION ALL

  SELECT u.id, u.referral_id, u.refer_by, u.position, t.root_leg
  FROM users u
  JOIN team t ON u.refer_by = t.referral_id
)
";
            $bindTeamOnly = [$rootReferral];
            $bindRows = array_merge([$rootReferral], $dateBinds);

        } else {
            // placement
            $rootRow = DB::table('users')->where('id', $rootId)->select('left_user_id','right_user_id')->first();
            $leftRoot  = (int)($rootRow->left_user_id ?? 0);
            $rightRoot = (int)($rootRow->right_user_id ?? 0);

            $teamCte = "
WITH RECURSIVE team AS (
  SELECT id, referral_id, left_user_id, right_user_id, 'NA' AS root_leg
  FROM users WHERE id = ?

  UNION ALL

  SELECT u.id, u.referral_id, u.left_user_id, u.right_user_id,
         CASE WHEN u.id = ? THEN 'L' WHEN u.id = ? THEN 'R' ELSE t.root_leg END AS root_leg
  FROM users u
  JOIN team t ON u.id IN (t.left_user_id, t.right_user_id)
)
";
            $bindTeamOnly = [$rootId, $leftRoot, $rightRoot];
            $bindRows = array_merge([$rootId, $leftRoot, $rightRoot], $dateBinds);
        }

        // --- matrix aggregation: type x root_leg (safe subquery to satisfy ONLY_FULL_GROUP_BY) ---
        $rowsSql = $teamCte . "
SELECT x.type, x.root_leg AS root_leg, SUM(x.amount) AS amount, SUM(x.orders) AS orders
FROM (
  SELECT
    LOWER(COALESCE(NULLIF(s.type,''),'other')) AS type,
    UPPER(COALESCE(t.root_leg,'NA')) AS root_leg,
    s.amount,
    1 AS orders
  FROM team t
  JOIN sell s ON s.buyer_id = t.id
  WHERE s.status = 'paid' {$dateSql}
) x
GROUP BY x.type, x.root_leg
";
        $rows = DB::select($rowsSql, $bindRows);

        $emptyRow = ['left'=>0.0,'right'=>0.0,'orders_left'=>0,'orders_right'=>0,'matched'=>0.0,'pairs'=>0,'cf_left'=>0.0,'cf_right'=>0.0];
        $matrix = ['silver'=>$emptyRow,'gold'=>$emptyRow,'diamond'=>$emptyRow,'other'=>$emptyRow];

        foreach ($rows as $r) {
            $rk = strtolower((string)($r->type ?? 'other'));
            if ($rk === '') $rk = 'other';
            if (!isset($matrix[$rk])) $matrix[$rk] = $emptyRow;

            // SQL alias is `root_leg`
            $leg = strtoupper((string)($r->root_leg ?? 'NA'));
            if ($leg === 'L') {
                $matrix[$rk]['left'] += (float)($r->amount ?? 0.0);
                $matrix[$rk]['orders_left'] += (int)($r->orders ?? 0);
            } elseif ($leg === 'R') {
                $matrix[$rk]['right'] += (float)($r->amount ?? 0.0);
                $matrix[$rk]['orders_right'] += (int)($r->orders ?? 0);
            } else {
                // treat NA as other-left by default
                $matrix['other']['left'] += (float)($r->amount ?? 0.0);
                $matrix['other']['orders_left'] += (int)($r->orders ?? 0);
            }
        }

        // Compute authoritative totals FROM matrix (single source-of-truth)
        $leftTotal = 0.0;
        $rightTotal = 0.0;
        foreach ($matrix as $m) {
            $leftTotal  += (float)($m['left'] ?? 0.0);
            $rightTotal += (float)($m['right'] ?? 0.0);
        }

        // --- recent 20 sells for context ---
        $recentSql = $teamCte . "
SELECT s.buyer_id, s.product, LOWER(COALESCE(NULLIF(s.type,''),'other')) AS type,
       UPPER(COALESCE(t.root_leg,'NA')) AS leg, s.amount, s.created_at
FROM team t JOIN sell s ON s.buyer_id = t.id
WHERE s.status = 'paid' {$dateSql}
ORDER BY s.id DESC
LIMIT 20
";
        $recentRows = DB::select($recentSql, $bindRows);
        $recent = collect($recentRows)->map(fn($r)=>[
            'buyer_id'=> $r->buyer_id ?? null,
            'product' => $r->product ?? null,
            'type'    => $r->type ?? 'other',
            'leg'     => $r->leg ?? 'NA',
            'amount'  => (float)($r->amount ?? 0.0),
            'created_at' => (string)($r->created_at ?? '')
        ])->all();

        // --- counts (members & left/right) and ids for pkg counts ---
        if (strtolower($type) === 'referral') {
            $c = DB::selectOne($teamCte . "SELECT COUNT(*) AS total, SUM(CASE WHEN UPPER(COALESCE(root_leg,'NA'))='L' THEN 1 ELSE 0 END) AS left_count, SUM(CASE WHEN UPPER(COALESCE(root_leg,'NA'))='R' THEN 1 ELSE 0 END) AS right_count FROM team", $bindTeamOnly);
            $totalMembers = (int)($c->total ?? 0);
            $leftCount = (int)($c->left_count ?? 0);
            $rightCount = (int)($c->right_count ?? 0);
            $ids = collect(DB::select($teamCte . "SELECT id FROM team", $bindTeamOnly))->pluck('id')->map(fn($v)=>(int)$v)->toArray();
        } else {
            // placement: collect sub-tree ids (root included)
            $allIdsRows = DB::select($teamCte . "SELECT id FROM team", $bindTeamOnly);
            $ids = collect($allIdsRows)->pluck('id')->map(fn($v)=>(int)$v)->toArray();
            $totalMembers = count($ids);

            // left/right subtree counts
            $rootRow2 = DB::table('users')->where('id', $rootId)->select('left_user_id','right_user_id')->first();
            if ($rootRow2) {
                if (!is_null($rootRow2->left_user_id) && (int)$rootRow2->left_user_id !== 0) {
                    $lr = DB::selectOne("
WITH RECURSIVE sub AS (
  SELECT id, left_user_id, right_user_id FROM users WHERE id = ?
  UNION ALL
  SELECT u.id, u.left_user_id, u.right_user_id FROM users u JOIN sub s ON u.id IN (s.left_user_id, s.right_user_id)
)
SELECT COUNT(*) AS cnt FROM sub
", [(int)$rootRow2->left_user_id]);
                    $leftCount = (int)($lr->cnt ?? 0);
                }
                if (!is_null($rootRow2->right_user_id) && (int)$rootRow2->right_user_id !== 0) {
                    $rr = DB::selectOne("
WITH RECURSIVE sub AS (
  SELECT id, left_user_id, right_user_id FROM users WHERE id = ?
  UNION ALL
  SELECT u.id, u.left_user_id, u.right_user_id FROM users u JOIN sub s ON u.id IN (s.left_user_id, s.right_user_id)
)
SELECT COUNT(*) AS cnt FROM sub
", [(int)$rootRow2->right_user_id]);
                    $rightCount = (int)($rr->cnt ?? 0);
                }
            }
        }

        // --- package counts among ids (highest paid type per user) ---
        $pkgCounts = ['silver'=>0,'gold'=>0,'diamond'=>0,'other'=>0];
        if (!empty($ids)) {
            $order = "CASE LOWER(type) WHEN 'silver' THEN 1 WHEN 'gold' THEN 2 WHEN 'diamond' THEN 3 ELSE 0 END";
            $lvlById = DB::table('sell')
                ->select('buyer_id', DB::raw("MAX($order) AS lvl"))
                ->where('status','paid')
                ->whereIn(DB::raw('LOWER(type)'), ['silver','gold','diamond'])
                ->whereIn('buyer_id', $ids)
                ->groupBy('buyer_id')
                ->pluck('lvl','buyer_id');

            $toPkg = static fn (int $lvl) => $lvl>=3 ? 'diamond' : ($lvl===2 ? 'gold' : ($lvl===1 ? 'silver' : 'other'));
            foreach ($ids as $bid) {
                $lvl = (int)($lvlById[$bid] ?? 0);
                $pk = $toPkg($lvl);
                $pkgCounts[$pk] = ($pkgCounts[$pk] ?? 0) + 1;
            }
        }

        // --- matched/pairs/carry for matrix rows ---
        $unit = ['silver'=>3000.0, 'gold'=>15000.0, 'diamond'=>30000.0];
        $cfLTotal = 0.0; $cfRTotal = 0.0;
        foreach ($matrix as $rank => &$row) {
            $leftAmt  = (float)($row['left']  ?? 0);
            $rightAmt = (float)($row['right'] ?? 0);
            $matchedAmt = min($leftAmt, $rightAmt);
            $row['matched'] = $matchedAmt;
            if (isset($unit[$rank]) && $unit[$rank] > 0) {
                $row['pairs'] = (int) floor($matchedAmt / $unit[$rank]);
            } else {
                $row['pairs'] = min((int)($row['orders_left'] ?? 0), (int)($row['orders_right'] ?? 0));
            }
            $row['cf_left']  = max(0.0, $leftAmt  - $matchedAmt);
            $row['cf_right'] = max(0.0, $rightAmt - $matchedAmt);
            $cfLTotal += $row['cf_left'];
            $cfRTotal += $row['cf_right'];
        }
        unset($row);

        $carryTotals = ['left'=>$cfLTotal,'right'=>$cfRTotal,'total'=>$cfLTotal+$cfRTotal];

        return [
            'totals' => ['left'=>$leftTotal,'right'=>$rightTotal],
            'matrix' => $matrix,
            'recent' => $recent,
            'carryTotals' => $carryTotals,
            'counts' => ['total'=>$totalMembers,'left'=>$leftCount,'right'=>$rightCount],
            'pkg' => $pkgCounts,
        ];
    }

    /**
     * Show binary summary page (Inertia).
     */
    public function show(Request $request): Response
    {
        $userId = (int) Auth::id();
        abort_unless($userId, 401);

        $rootId = (int) ($request->query('root') ?? $userId);
        $type   = strtolower($request->query('type', 'placement')); // placement | referral

        $from = $request->query('from');
        $to   = $request->query('to');

        // get summary (reuse logic)
        $summary = self::getSummaryForRoot($rootId, $type, $from, $to);

        // render inertia with the full data
        return Inertia::render('Income/Binary', [
            'asOf'        => now()->toDateTimeString(),
            'filters'     => ['from'=>$from, 'to'=>$to],
            'totals'      => $summary['totals'],
            'matrix'      => $summary['matrix'],
            'recent'      => $summary['recent'],
            'carryTotals' => $summary['carryTotals'],
            'counts'      => $summary['counts'],
            'pkg'         => $summary['pkg'],
        ]);
    }
}
