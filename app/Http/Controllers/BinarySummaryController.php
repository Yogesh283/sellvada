<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BinarySummaryController extends Controller
{
    /**
     * All-level team business grouped by rank (Silver/Gold/Diamond/Other),
     * per-leg (L/R) Orders + Amount, Matched (pair) amount and Carry Forward.
     * Tree rule: child.refer_by = parent.referral_id
     * Leg comes from ROOT CHILD (L/R), propagate to all descendants
     * Filters: ?from=YYYY-MM-DD&to=YYYY-MM-DD
     * View: Income/Binary
     */
    public function show(Request $request): Response
    {
        $userId = (int) Auth::id();
        abort_unless($userId, 401);

        $from = $request->query('from');
        $to   = $request->query('to');

        // Team root = my referral code
        $myReferral = (string) DB::table('users')->where('id', $userId)->value('referral_id');

        // skeleton row
        $emptyRow = [
            'left'=>0,'right'=>0,
            'orders_left'=>0,'orders_right'=>0,
            'matched'=>0,'pairs'=>0,
            'cf_left'=>0,'cf_right'=>0
        ];
        $matrix = [
            'Silver'  => $emptyRow,
            'Gold'    => $emptyRow,
            'Diamond' => $emptyRow,
            'other'   => $emptyRow,
        ];

        if (!$myReferral) {
            return Inertia::render('Income/Binary', [
                'asOf'        => now()->toDateTimeString(),
                'filters'     => ['from'=>$from,'to'=>$to],
                'totals'      => ['left'=>0,'right'=>0],
                'matrix'      => $matrix,
                'recent'      => [],
                'carryTotals' => ['left'=>0,'right'=>0,'total'=>0],
            ]);
        }

        // Date filters for SQL
        $dateSql = '';
        $bind    = [$myReferral];
        if ($from) { $dateSql .= " AND DATE(s.created_at) >= ?"; $bind[] = $from; }
        if ($to)   { $dateSql .= " AND DATE(s.created_at) <= ?"; $bind[] = $to; }

        // ===== Rank/Leg aggregation (Orders + Amount) =====
        $rowsSql = "
WITH RECURSIVE team AS (
  -- direct children: keep their own position as root_leg
  SELECT id, referral_id, refer_by, position,
         UPPER(COALESCE(position,'NA')) AS root_leg
  FROM users
  WHERE refer_by = ?

  UNION ALL
  -- propagate same root_leg to all descendants
  SELECT u.id, u.referral_id, u.refer_by, u.position, t.root_leg
  FROM users u
  JOIN team t ON u.refer_by = t.referral_id
)
SELECT
  x.type,
  x.root_leg AS leg,
  SUM(x.amount) AS amount,
  COUNT(*)      AS orders
FROM (
  SELECT
    LOWER(COALESCE(NULLIF(s.type,''),'other')) AS type,
    t.root_leg,
    s.amount
  FROM team t
  JOIN sell s ON s.buyer_id = t.id
  WHERE s.status = 'paid' {$dateSql}
) x
GROUP BY x.type, x.root_leg
";
        $rows = DB::select($rowsSql, $bind);

        foreach ($rows as $r) {
            $rank = $r->type ?? 'other';
            $leg  = $r->leg;
            if (!isset($matrix[$rank])) $matrix[$rank] = $emptyRow;

            if ($leg === 'L') {
                $matrix[$rank]['left']        += (float) $r->amount;
                $matrix[$rank]['orders_left'] += (int) $r->orders;
            } elseif ($leg === 'R') {
                $matrix[$rank]['right']        += (float) $r->amount;
                $matrix[$rank]['orders_right'] += (int) $r->orders;
            }
        }

        // ===== Matched (pair) amount + CF per rank =====
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

        $leftTotal  = array_sum(array_column($matrix, 'left'));
        $rightTotal = array_sum(array_column($matrix, 'right'));

        // ===== Recent 20 paid orders (context) =====
        $recentSql = "
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
SELECT
  s.buyer_id,
  s.product,
  LOWER(COALESCE(NULLIF(s.type,''),'other')) AS type,
  t.root_leg AS leg,
  s.amount,
  s.created_at
FROM team t
JOIN sell s ON s.buyer_id = t.id
WHERE s.status='paid' {$dateSql}
ORDER BY s.id DESC
LIMIT 20
";
        $recent = collect(DB::select($recentSql, $bind))->map(fn($r) => [
            'buyer_id'   => $r->buyer_id,
            'product'    => $r->product,
            'type'       => $r->type,
            'leg'        => $r->leg,
            'amount'     => (float) $r->amount,
            'created_at' => (string) $r->created_at,
        ]);

        return Inertia::render('Income/Binary', [
            'asOf'        => now()->toDateTimeString(),
            'filters'     => ['from'=>$from, 'to'=>$to],
            'totals'      => ['left'=>$leftTotal, 'right'=>$rightTotal],
            'matrix'      => $matrix,
            'recent'      => $recent,
            'carryTotals' => [
                'left'=>$cfLTotal,
                'right'=>$cfRTotal,
                'total'=>$cfLTotal+$cfRTotal
            ],
        ]);
    }
}
