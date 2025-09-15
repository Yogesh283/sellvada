<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class VipRepurchaseSalaryController extends Controller
{
    /**
     * GET /income/vip-repurchase-salary?month=YYYY-MM
     * Returns summary values plus team/placement totals for both 'sell' and 'repurchase'
     * and combined totals (sell + repurchase).
     */
    public function index(Request $request)
    {
        $userId = (int) Auth::id();
        abort_unless($userId, 401);

        // Month window (default = current month)
        $monthStr = $request->query('month') ?: now()->format('Y-m');
        try {
            $mStart = Carbon::parse($monthStr . '-01')->startOfMonth();
        } catch (\Throwable $e) {
            $mStart = now()->startOfMonth();
        }
        $mEnd = (clone $mStart)->endOfMonth();

        // Team root = my referral_id (referral-based team)
        $myReferral = DB::table('users')->where('id', $userId)->value('referral_id');
        $myReferral = is_null($myReferral) ? null : (string)$myReferral;

        // Slabs (fallback static)
        $slabsRaw = DB::table('repurchase_salary_slabs')->orderBy('threshold_volume','asc')->get();
        if ($slabsRaw->isEmpty()) {
            $slabs = [
                ['rank' => 'VIP 1', 'volume' => 30000, 'salary' => 1000],
                ['rank' => 'VIP 2', 'volume' => 100000, 'salary' => 3000],
                ['rank' => 'VIP 3', 'volume' => 200000, 'salary' => 5000],
                ['rank' => 'VIP 4', 'volume' => 500000, 'salary' => 10000],
                ['rank' => 'VIP 5', 'volume' => 1000000, 'salary' => 25000],
                ['rank' => 'VIP 6', 'volume' => 2000000, 'salary' => 50000],
                ['rank' => 'VIP 7', 'volume' => 5000000, 'salary' => 100000],
            ];
        } else {
            $slabs = $slabsRaw->map(function ($r) {
                return ['rank' => ($r->rank ?? ('VIP ' . ($r->vip_no ?? '?'))), 'volume' => (float)$r->threshold_volume, 'salary' => (float)$r->salary_amount];
            })->toArray();
        }

        // Defaults
        $summary = [
            'left' => 0.0,
            'right' => 0.0,
            'matched' => 0.0,
            'paid_this_month' => 0.0,
            'due' => null,
        ];
        $achieved = null;

        // ---------- referral/team-based repurchase summary (monthly) ----------
        $repRows = [];
        if ($myReferral) {
            $repSql = "
WITH RECURSIVE team AS (
  SELECT id, referral_id, refer_by, position, 1 AS lvl
  FROM users
  WHERE refer_by = ?

  UNION ALL
  SELECT u.id, u.referral_id, u.refer_by, u.position, t.lvl + 1
  FROM users u
  JOIN team t ON u.refer_by = t.referral_id
)
SELECT UPPER(COALESCE(u.position,'NA')) AS leg,
       COUNT(*) AS orders,
       COALESCE(SUM(r.amount),0) AS amount
FROM team u
JOIN repurchase r ON r.buyer_id = u.id
WHERE r.status='paid'
  AND r.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(u.position,'NA'));
";
            $repRows = DB::select($repSql, [$myReferral, $mStart, $mEnd]);
        }

        $repL = 0.0; $repR = 0.0;
        foreach ($repRows as $r) {
            if ($r->leg === 'L') $repL = (float)$r->amount;
            if ($r->leg === 'R') $repR = (float)$r->amount;
        }

        // ---------- referral/team-based sell summary (monthly) ----------
        $sellRows = [];
        if ($myReferral) {
            $sellSql = "
WITH RECURSIVE team AS (
  SELECT id, referral_id, refer_by, position
  FROM users
  WHERE refer_by = ?

  UNION ALL
  SELECT u.id, u.referral_id, u.refer_by, u.position
  FROM users u
  JOIN team t ON u.refer_by = t.referral_id
)
SELECT UPPER(COALESCE(u.position,'NA')) AS leg,
       COUNT(*) AS orders,
       COALESCE(SUM(s.amount),0) AS amount
FROM team u
JOIN sell s ON s.buyer_id = u.id
WHERE s.status='paid'
  AND s.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(u.position,'NA'));
";
            $sellRows = DB::select($sellSql, [$myReferral, $mStart, $mEnd]);
        }

        $sellL = 0.0; $sellR = 0.0;
        foreach ($sellRows as $r) {
            if ($r->leg === 'L') $sellL = (float)$r->amount;
            if ($r->leg === 'R') $sellR = (float)$r->amount;
        }

        // Combined team totals (sell + repurchase)
        $teamCombinedLeft  = $sellL + $repL;
        $teamCombinedRight = $sellR + $repR;
        $teamCombinedMatched = min($teamCombinedLeft, $teamCombinedRight);

        // Highest slab achieved based on teamCombinedMatched
        foreach ($slabs as $s) {
            if ($teamCombinedMatched >= $s['volume']) {
                $achieved = $s['rank'];
            }
        }

        // Payouts this month (include weekly/monthly repurchase payout types)
        $paidThisMonth = (float) DB::table('_payout')
            ->where('to_user_id', $userId)
            ->whereIn('type', ['repurchase_salary','repurchase_salary_weekly'])
            ->whereBetween('created_at', [$mStart, $mEnd])
            ->sum('amount');

        // due installment (first unpaid) in this month
        $due = DB::table('repurchase_salary_installments as i')
            ->join('repurchase_salary_qualifications as q', 'q.id', '=', 'i.qualification_id')
            ->where('q.sponsor_id', $userId)
            ->whereBetween(DB::raw('DATE(i.due_month)'), [$mStart->toDateString(), $mEnd->toDateString()])
            ->select('i.id','i.amount','i.due_month','i.paid_at','q.months_total','q.months_paid','q.vip_no','q.salary_amount')
            ->orderBy('i.due_month','asc')
            ->get();

        $dueFirst = null;
        foreach ($due as $d) {
            if (is_null($d->paid_at) && $dueFirst === null) {
                $dueFirst = $d;
            }
        }

        // Put combined values into summary so front-end uses combined matched
        $summary = [
            'left' => $teamCombinedLeft,
            'right' => $teamCombinedRight,
            'matched' => $teamCombinedMatched,
            'paid_this_month' => $paidThisMonth,
            'due' => $dueFirst,
        ];

        //
        // ---------- placement-based totals (placement tree using left_user_id/right_user_id)
        //
        $uid = (int)$userId;
        $rootRow = DB::table('users')->where('id', $uid)->select('left_user_id','right_user_id')->first();
        $leftRoot  = (int)($rootRow->left_user_id ?? 0);
        $rightRoot = (int)($rootRow->right_user_id ?? 0);

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
        $bindsDate = [$uid, $leftRoot, $rightRoot, $mStart, $mEnd]; // will be used for both sell/repurchase placement queries

        // placement sells
        $placementSell = ['left'=>0.0,'right'=>0.0,'cnt_left'=>0,'cnt_right'=>0];
        $placementSellSql = $placementCte . "
SELECT UPPER(COALESCE(t.root_leg,'NA')) AS leg, COALESCE(SUM(s.amount),0) AS amt, COUNT(s.id) AS cnt
FROM team t
JOIN sell s ON s.buyer_id = t.id
WHERE s.status='paid'
  AND s.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(t.root_leg,'NA'))
";
        $psRows = DB::select($placementSellSql, $bindsDate);
        foreach ($psRows as $r) {
            if ($r->leg === 'L') { $placementSell['left'] = (float)$r->amt; $placementSell['cnt_left'] = (int)$r->cnt; }
            if ($r->leg === 'R') { $placementSell['right'] = (float)$r->amt; $placementSell['cnt_right'] = (int)$r->cnt; }
        }

        // placement repurchases
        $placementRepurchase = ['left'=>0.0,'right'=>0.0,'cnt_left'=>0,'cnt_right'=>0];
        $placementRepSql = $placementCte . "
SELECT UPPER(COALESCE(t.root_leg,'NA')) AS leg, COALESCE(SUM(r.amount),0) AS amt, COUNT(r.id) AS cnt
FROM team t
JOIN repurchase r ON r.buyer_id = t.id
WHERE r.status='paid'
  AND r.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(t.root_leg,'NA'))
";
        $prRows = DB::select($placementRepSql, $bindsDate);
        foreach ($prRows as $r) {
            if ($r->leg === 'L') { $placementRepurchase['left'] = (float)$r->amt; $placementRepurchase['cnt_left'] = (int)$r->cnt; }
            if ($r->leg === 'R') { $placementRepurchase['right'] = (float)$r->amt; $placementRepurchase['cnt_right'] = (int)$r->cnt; }
        }

        // placement combined
        $placementCombinedLeft  = $placementSell['left'] + $placementRepurchase['left'];
        $placementCombinedRight = $placementSell['right'] + $placementRepurchase['right'];
        $placementCombinedMatched = min($placementCombinedLeft, $placementCombinedRight);

        // Final props
        $props = [
            'month' => $mStart->format('Y-m'),
            'slabs' => $slabs,
            'summary' => $summary,
            'achieved_rank' => $achieved,

            // breakdowns
            'team_sells' => ['left'=>$sellL,'right'=>$sellR,'rows'=>$sellRows],
            'team_repurchases' => ['left'=>$repL,'right'=>$repR,'rows'=>$repRows],
            'team_combined' => ['left'=>$teamCombinedLeft,'right'=>$teamCombinedRight,'matched'=>$teamCombinedMatched],

            'placement_sells' => $placementSell,
            'placement_repurchases' => $placementRepurchase,
            'placement_combined' => ['left'=>$placementCombinedLeft,'right'=>$placementCombinedRight,'matched'=>$placementCombinedMatched],
        ];

        return Inertia::render('Income/VipRepurchaseSalary', $props);
    }

    /**
     * POST /income/vip-repurchase-salary/close-week
     * (unchanged — use your existing implementation from before)
     */
    public function closeWeek(Request $request)
    {
        // Use the closeWeek implementation you already have (unchanged).
        // If you want, I can paste the same closeWeek method here again — tell me.
        abort(404, 'Use existing closeWeek implementation.');
    }
}
