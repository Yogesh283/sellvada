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
     * ALL-LEVEL team business (Left/Right) grouped by rank (type) using CTE.
     * Tree rule: child.refer_by = parent.referral_id
     * Leg comes from users.position (L/R), not sell.leg
     * Filters: ?from=YYYY-MM-DD&to=YYYY-MM-DD
     * View: Income/Binary
     */
    public function show(Request $request): Response
    {
        $userId = (int) Auth::id();
        abort_unless($userId, 401);

        $from = $request->query('from'); // YYYY-MM-DD
        $to   = $request->query('to');   // YYYY-MM-DD

        // Team root = my referral code
        $myReferral = (string) DB::table('users')->where('id', $userId)->value('referral_id');

        // Empty safe response
        $emptyMatrix = [
            'silver'  => ['left'=>0,'right'=>0,'orders_left'=>0,'orders_right'=>0],
            'gold'    => ['left'=>0,'right'=>0,'orders_left'=>0,'orders_right'=>0],
            'diamond' => ['left'=>0,'right'=>0,'orders_left'=>0,'orders_right'=>0],
            'other'   => ['left'=>0,'right'=>0,'orders_left'=>0,'orders_right'=>0],
        ];

        if (!$myReferral) {
            return Inertia::render('Income/Binary', [
                'asOf'    => now()->toDateTimeString(),
                'filters' => ['from'=>$from, 'to'=>$to],
                'totals'  => ['left'=>0, 'right'=>0],
                'matrix'  => $emptyMatrix,
                'recent'  => [],
            ]);
        }

        // Date clause + bindings
        $dateSql  = '';
        $bindings = [$myReferral];
        if ($from) { $dateSql .= ' AND DATE(s.created_at) >= ?'; $bindings[] = $from; }
        if ($to)   { $dateSql .= ' AND DATE(s.created_at) <= ?'; $bindings[] = $to; }

        /** ---------- 1) Rank + Left/Right grouped (matrix) ---------- */
      $rowsSql = "
WITH RECURSIVE team AS (
    SELECT id, referral_id, refer_by, position, 1 AS lvl
    FROM users
    WHERE refer_by = ?

    UNION ALL
    SELECT u.id, u.referral_id, u.refer_by, u.position, t.lvl + 1
    FROM users u
    INNER JOIN team t ON u.refer_by = t.referral_id
)
SELECT 
    x.type,
    x.leg,
    SUM(x.amount)  AS amount,
    COUNT(*)       AS orders
FROM (
    SELECT
        LOWER(COALESCE(NULLIF(s.type,''),'other')) AS type,
        UPPER(COALESCE(u.position,'NA'))          AS leg,
        s.amount
    FROM team u
    INNER JOIN sell s ON s.buyer_id = u.id
    WHERE s.status = 'paid' {$dateSql}
) AS x
GROUP BY x.type, x.leg
";
$rows = DB::select($rowsSql, $bindings);


        // Build matrix expected by React
        $matrix = $emptyMatrix;
        foreach ($rows as $r) {
            $type = $r->type ?? 'other';
            if ($r->leg === 'L') {
                $matrix[$type]['left']        += (float) $r->amount;
                $matrix[$type]['orders_left'] += (int) $r->orders;
            } elseif ($r->leg === 'R') {
                $matrix[$type]['right']        += (float) $r->amount;
                $matrix[$type]['orders_right'] += (int) $r->orders;
            }
        }

        // Totals
        $leftTotal  = array_sum(array_map(fn($x) => $x['left'],  $matrix));
        $rightTotal = array_sum(array_map(fn($x) => $x['right'], $matrix));

        /** ---------- 2) Recent 20 orders (with leg from users.position) ---------- */
        $recentSql = "
            WITH RECURSIVE team AS (
                SELECT id, referral_id, refer_by, position, 1 AS lvl
                FROM users
                WHERE refer_by = ?

                UNION ALL
                SELECT u.id, u.referral_id, u.refer_by, u.position, t.lvl + 1
                FROM users u
                INNER JOIN team t ON u.refer_by = t.referral_id
            )
            SELECT
                s.buyer_id,
                s.product,
                LOWER(COALESCE(NULLIF(s.type,''),'other')) AS type,
                UPPER(COALESCE(u.position,'NA'))          AS leg,
                s.amount,
                s.created_at
            FROM team u
            INNER JOIN sell s ON s.buyer_id = u.id
            WHERE s.status = 'paid' {$dateSql}
            ORDER BY s.id DESC
            LIMIT 20
        ";

        $recent = collect(DB::select($recentSql, $bindings))->map(function ($r) {
            return [
                'buyer_id'   => $r->buyer_id,
                'product'    => $r->product,
                'type'       => $r->type,
                'leg'        => $r->leg,
                'amount'     => (float) $r->amount,
                'created_at' => (string) $r->created_at,
            ];
        });

        return Inertia::render('Income/Binary', [
            'asOf'    => now()->toDateTimeString(),
            'filters' => ['from'=>$from, 'to'=>$to],
            'totals'  => ['left'=>$leftTotal, 'right'=>$rightTotal],
            'matrix'  => $matrix,
            'recent'  => $recent,
        ]);
    }
}
