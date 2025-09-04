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
     * Leg comes from users.position (L/R)
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
        $emptyRow = ['left'=>0,'right'=>0,'orders_left'=>0,'orders_right'=>0,'matched'=>0,'pairs'=>0,'cf_left'=>0,'cf_right'=>0];
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
  SELECT id, referral_id, refer_by, position, 1 AS lvl
  FROM users
  WHERE refer_by = ?

  UNION ALL
  SELECT u.id, u.referral_id, u.refer_by, u.position, t.lvl + 1
  FROM users u
  JOIN team t ON u.refer_by = t.referral_id
)
SELECT
  x.type,
  x.leg,
  SUM(x.amount) AS amount,
  COUNT(*)      AS orders
FROM (
  SELECT
    LOWER(COALESCE(NULLIF(s.type,''),'other')) AS type,
    UPPER(COALESCE(u.position,'NA'))          AS leg,
    s.amount
  FROM team u
  JOIN sell s ON s.buyer_id = u.id
  WHERE s.status = 'paid' {$dateSql}
) x
GROUP BY x.type, x.leg
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
        // unit BV per rank (orders assumed homogeneous inside a rank)
        $unit = ['silver'=>3000.0, 'gold'=>15000.0, 'diamond'=>30000.0];

        $cfLTotal = 0.0; $cfRTotal = 0.0;

        foreach ($matrix as $rank => &$row) {
            $leftAmt  = (float)($row['left']  ?? 0);
            $rightAmt = (float)($row['right'] ?? 0);

            // Matched amount = amount utilised in pairs
            $matchedAmt = min($leftAmt, $rightAmt);
            $row['matched'] = $matchedAmt;

            // Pairs count (best-effort): by unit BV if known, else by min order count
            if (isset($unit[$rank]) && $unit[$rank] > 0) {
                $row['pairs'] = (int) floor($matchedAmt / $unit[$rank]);
            } else {
                $row['pairs'] = min((int)($row['orders_left'] ?? 0), (int)($row['orders_right'] ?? 0));
            }

            // Carry forward (unmatched extra)
            $row['cf_left']  = max(0.0, $leftAmt  - $matchedAmt);
            $row['cf_right'] = max(0.0, $rightAmt - $matchedAmt);

            $cfLTotal += $row['cf_left'];
            $cfRTotal += $row['cf_right'];
        }
        unset($row);

        // Top cards stay: total left/right business
        $leftTotal  = array_sum(array_column($matrix, 'left'));
        $rightTotal = array_sum(array_column($matrix, 'right'));

        // ===== Recent 20 paid orders (context) =====
        $recentSql = "
WITH RECURSIVE team AS (
  SELECT id, referral_id, refer_by, position, 1 AS lvl
  FROM users
  WHERE refer_by = ?

  UNION ALL
  SELECT u.id, u.referral_id, u.refer_by, u.position, t.lvl + 1
  FROM users u
  JOIN team t ON u.refer_by = t.referral_id
)
SELECT
  s.buyer_id,
  s.product,
  LOWER(COALESCE(NULLIF(s.type,''),'other')) AS type,
  UPPER(COALESCE(u.position,'NA'))          AS leg,
  s.amount,
  s.created_at
FROM team u
JOIN sell s ON s.buyer_id = u.id
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
            'totals'      => ['left'=>$leftTotal, 'right'=>$rightTotal],   // top cards
            'matrix'      => $matrix,                                      // table rows
            'recent'      => $recent,
            'carryTotals' => ['left'=>$cfLTotal,'right'=>$cfRTotal,'total'=>$cfLTotal+$cfRTotal],
        ]);
    }
}
