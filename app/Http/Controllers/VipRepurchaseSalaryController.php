<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class VipRepurchaseSalaryController extends Controller
{
    public function index(Request $request)
    {
        $userId = (int) Auth::id();
        abort_unless($userId, 401);

        // month param
        $monthStr = $request->query('month') ?: now()->format('Y-m');
        try {
            $mStart = Carbon::parse($monthStr . '-01')->startOfMonth();
        } catch (\Throwable $e) {
            $mStart = now()->startOfMonth();
        }
        $mEnd = (clone $mStart)->endOfMonth();

        // previous month window (carry forward calculation)
        $prevStart = (clone $mStart)->subMonthNoOverflow()->startOfMonth();
        $prevEnd   = (clone $prevStart)->endOfMonth();

        // slabs from DB or fallback
        $slabsRaw = DB::table('repurchase_salary_slabs')->orderBy('threshold_volume','asc')->get();
        if ($slabsRaw->isEmpty()) {
            $slabs = [
                ['rank' => 'VIP 1', 'volume' => 30000, 'salary' => 1000, 'vip_no'=>1],
                ['rank' => 'VIP 2', 'volume' => 100000, 'salary' => 3000, 'vip_no'=>2],
                ['rank' => 'VIP 3', 'volume' => 200000, 'salary' => 5000, 'vip_no'=>3],
                ['rank' => 'VIP 4', 'volume' => 500000, 'salary' => 10000, 'vip_no'=>4],
                ['rank' => 'VIP 5', 'volume' => 1000000, 'salary' => 25000, 'vip_no'=>5],
                ['rank' => 'VIP 6', 'volume' => 2000000, 'salary' => 50000, 'vip_no'=>6],
                ['rank' => 'VIP 7', 'volume' => 5000000, 'salary' => 100000, 'vip_no'=>7],
            ];
        } else {
            $slabs = $slabsRaw->map(function ($r) {
                return [
                    'rank' => ($r->rank ?? ('VIP ' . ($r->vip_no ?? '?'))),
                    'volume' => (float)($r->threshold_volume ?? 0),
                    'salary' => (float)($r->salary_amount ?? 0),
                    'vip_no' => $r->vip_no ?? null,
                ];
            })->toArray();
        }

        // get placement roots & referral id
        $rootRow = DB::table('users')->where('id', $userId)->select('left_user_id','right_user_id','referral_id')->first();
        $leftRoot  = (int)($rootRow->left_user_id ?? 0);
        $rightRoot = (int)($rootRow->right_user_id ?? 0);
        $myReferral = $rootRow->referral_id ?? null;

        // if no placement root, return zeros (still show slab table)
        if (!$leftRoot && !$rightRoot) {
            return Inertia::render('Income/VipRepurchaseSalary', [
                'month' => $mStart->format('Y-m'),
                'slabs' => $slabs,
                'placement_sells' => ['left'=>0,'right'=>0,'cnt_left'=>0,'cnt_right'=>0],
                'placement_repurchases' => ['left'=>0,'right'=>0,'cnt_left'=>0,'cnt_right'=>0],
                'placement_combined' => ['left'=>0,'right'=>0],
                'placement_matched' => 0,
                'placement_pending' => 0,
                'carry_forward' => ['left'=>0,'right'=>0],
                'paid_this_month' => 0,
            ]);
        }

        // placement recursive CTE
        $placementCte = "
WITH RECURSIVE team AS (
  SELECT id, left_user_id, right_user_id, 'NA' AS root_leg
  FROM users WHERE id = ?

  UNION ALL

  SELECT u.id, u.left_user_id, u.right_user_id,
         CASE WHEN u.id = ? THEN 'L'
              WHEN u.id = ? THEN 'R'
              ELSE t.root_leg END AS root_leg
  FROM users u
  JOIN team t ON u.id IN (t.left_user_id, t.right_user_id)
)
";

        $binds = [$userId, $leftRoot, $rightRoot];

        // sells in month (placement)
        $placementSellsSql = $placementCte . "
SELECT UPPER(COALESCE(t.root_leg,'NA')) AS leg, COALESCE(SUM(s.amount),0) AS amt, COUNT(s.id) AS cnt
FROM team t
JOIN sell s ON s.buyer_id = t.id
WHERE s.status = 'paid'
  AND s.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(t.root_leg,'NA'))
";
        $sellRows = DB::select($placementSellsSql, array_merge($binds, [$mStart, $mEnd]));
        $placementSells = ['left'=>0,'right'=>0,'cnt_left'=>0,'cnt_right'=>0];
        foreach ($sellRows as $r) {
            if ($r->leg === 'L') { $placementSells['left'] = (float)$r->amt; $placementSells['cnt_left'] = (int)$r->cnt; }
            if ($r->leg === 'R') { $placementSells['right'] = (float)$r->amt; $placementSells['cnt_right'] = (int)$r->cnt; }
        }

        // repurchases in month (placement)
        $placementRepSql = $placementCte . "
SELECT UPPER(COALESCE(t.root_leg,'NA')) AS leg, COALESCE(SUM(r.amount),0) AS amt, COUNT(r.id) AS cnt
FROM team t
JOIN repurchase r ON r.buyer_id = t.id
WHERE r.status = 'paid'
  AND r.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(t.root_leg,'NA'))
";
        $repRows = DB::select($placementRepSql, array_merge($binds, [$mStart, $mEnd]));
        $placementRepurchases = ['left'=>0,'right'=>0,'cnt_left'=>0,'cnt_right'=>0];
        foreach ($repRows as $r) {
            if ($r->leg === 'L') { $placementRepurchases['left'] = (float)$r->amt; $placementRepurchases['cnt_left'] = (int)$r->cnt; }
            if ($r->leg === 'R') { $placementRepurchases['right'] = (float)$r->amt; $placementRepurchases['cnt_right'] = (int)$r->cnt; }
        }

        // combine
        $placementCombined = [
            'left'  => $placementSells['left'] + $placementRepurchases['left'],
            'right' => $placementSells['right'] + $placementRepurchases['right'],
        ];
        $placementMatched = min($placementCombined['left'], $placementCombined['right']);
        $placementPending = abs($placementCombined['left'] - $placementCombined['right']);

        // previous month combined for carry-forward
        $prevSellRows = DB::select($placementSellsSql, array_merge($binds, [$prevStart, $prevEnd]));
        $prevRepRows  = DB::select($placementRepSql, array_merge($binds, [$prevStart, $prevEnd]));
        $prevSells = ['left'=>0,'right'=>0]; $prevReps=['left'=>0,'right'=>0];
        foreach ($prevSellRows as $r) {
            if ($r->leg === 'L') $prevSells['left'] = (float)$r->amt;
            if ($r->leg === 'R') $prevSells['right'] = (float)$r->amt;
        }
        foreach ($prevRepRows as $r) {
            if ($r->leg === 'L') $prevReps['left'] = (float)$r->amt;
            if ($r->leg === 'R') $prevReps['right'] = (float)$r->amt;
        }
        $prevCombined = [
            'left' => $prevSells['left'] + $prevReps['left'],
            'right' => $prevSells['right'] + $prevReps['right'],
        ];
        $carryForward = [
            'left' => max(0, $prevCombined['left'] - $prevCombined['right']),
            'right'=> max(0, $prevCombined['right'] - $prevCombined['left']),
        ];

        // find highest slab achieved by placementMatched
        $achievedRank = null;
        foreach ($slabs as $s) {
            if ($placementMatched >= (float)$s['volume']) {
                $achievedRank = $s['rank'];
            }
        }

        // paid this month (optional)
        $paidThisMonth = (float) DB::table('_payout')
            ->where('to_user_id', $userId)
            ->whereIn('type', ['repurchase_salary','repurchase_salary_weekly','repurchase_salary_monthly'])
            ->whereBetween('created_at', [$mStart, $mEnd])
            ->sum('amount');

        // return Inertia props
        return Inertia::render('Income/VipRepurchaseSalary', [
            'month' => $mStart->format('Y-m'),
            'slabs' => $slabs,
            'placement_sells' => $placementSells,
            'placement_repurchases' => $placementRepurchases,
            'placement_combined' => $placementCombined,
            'placement_matched' => $placementMatched,
            'placement_pending' => $placementPending,
            'carry_forward' => $carryForward,
            'achieved_rank' => $achievedRank,
            'paid_this_month' => $paidThisMonth,
        ]);
    }
}
