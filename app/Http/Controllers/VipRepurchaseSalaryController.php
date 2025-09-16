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

        // Month window
        $monthStr = $request->query('month') ?: now()->format('Y-m');
        try {
            $mStart = Carbon::parse($monthStr.'-01')->startOfMonth();
        } catch (\Throwable $e) {
            $mStart = now()->startOfMonth();
        }
        $mEnd = (clone $mStart)->endOfMonth();

        // team root = my referral_id (referral tree)
        $myReferral = DB::table('users')->where('id', $userId)->value('referral_id');
        $myReferral = is_null($myReferral) ? null : (string)$myReferral;

        // load slabs either from table or fallback
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

        // defaults
        $summary = ['left'=>0.0,'right'=>0.0,'matched'=>0.0,'paid_this_month'=>0.0,'due'=>null];
        $achieved = null;

        // Compute referral-team combined (sell + repurchase) for selected month
        $teamSells = ['left'=>0.0,'right'=>0.0,'cnt_left'=>0,'cnt_right'=>0];
        $teamRep = ['left'=>0.0,'right'=>0.0,'cnt_left'=>0,'cnt_right'=>0];

        if ($myReferral) {
            // sells
            $sellRows = DB::select($this->teamCte() . "
SELECT UPPER(COALESCE(u.position,'NA')) AS leg, COALESCE(SUM(s.amount),0) AS amt, COUNT(s.id) AS cnt
FROM team u
JOIN sell s ON s.buyer_id = u.id
WHERE s.status='paid' AND s.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(u.position,'NA'))
", [$myReferral, $mStart, $mEnd]);

            foreach ($sellRows as $r) {
                if ($r->leg === 'L') { $teamSells['left'] = (float)$r->amt; $teamSells['cnt_left'] = (int)$r->cnt; }
                if ($r->leg === 'R') { $teamSells['right'] = (float)$r->amt; $teamSells['cnt_right'] = (int)$r->cnt; }
            }

            // repurchases
            $repRows = DB::select($this->teamCte() . "
SELECT UPPER(COALESCE(u.position,'NA')) AS leg, COALESCE(SUM(r.amount),0) AS amt, COUNT(r.id) AS cnt
FROM team u
JOIN repurchase r ON r.buyer_id = u.id
WHERE r.status='paid' AND r.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(u.position,'NA'))
", [$myReferral, $mStart, $mEnd]);

            foreach ($repRows as $r) {
                if ($r->leg === 'L') { $teamRep['left'] = (float)$r->amt; $teamRep['cnt_left'] = (int)$r->cnt; }
                if ($r->leg === 'R') { $teamRep['right'] = (float)$r->amt; $teamRep['cnt_right'] = (int)$r->cnt; }
            }
        }

        $teamCombinedLeft = $teamSells['left'] + $teamRep['left'];
        $teamCombinedRight = $teamSells['right'] + $teamRep['right'];
        $teamCombinedMatched = min($teamCombinedLeft, $teamCombinedRight);

        // Placement-based combined totals (placement tree)
        $uid = (int)$userId;
        $rootRow = DB::table('users')->where('id',$uid)->select('left_user_id','right_user_id')->first();
        $leftRoot = (int)($rootRow->left_user_id ?? 0);
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

        // placement sells
        $placementSellLeft = $placementSellRight = 0.0;
        $placementRepLeft = $placementRepRight = 0.0;

        $placementSellRows = DB::select($placementCte . "
SELECT UPPER(COALESCE(t.root_leg,'NA')) AS leg, COALESCE(SUM(s.amount),0) AS amt
FROM team t
JOIN sell s ON s.buyer_id = t.id
WHERE s.status='paid' AND s.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(t.root_leg,'NA'))
", [$uid, $leftRoot, $rightRoot, $mStart, $mEnd]);

        foreach ($placementSellRows as $r) {
            if ($r->leg === 'L') $placementSellLeft = (float)$r->amt;
            if ($r->leg === 'R') $placementSellRight = (float)$r->amt;
        }

        $placementRepRows = DB::select($placementCte . "
SELECT UPPER(COALESCE(t.root_leg,'NA')) AS leg, COALESCE(SUM(r.amount),0) AS amt
FROM team t
JOIN repurchase r ON r.buyer_id = t.id
WHERE r.status='paid' AND r.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(t.root_leg,'NA'))
", [$uid, $leftRoot, $rightRoot, $mStart, $mEnd]);

        foreach ($placementRepRows as $r) {
            if ($r->leg === 'L') $placementRepLeft = (float)$r->amt;
            if ($r->leg === 'R') $placementRepRight = (float)$r->amt;
        }

        $placementCombinedLeft = $placementSellLeft + $placementRepLeft;
        $placementCombinedRight = $placementSellRight + $placementRepRight;
        $placementCombinedMatched = min($placementCombinedLeft, $placementCombinedRight);

        // choose matched used to show slabs — you said placement-only earlier; here we show placementCombined by default
        $matched = $placementCombinedMatched;

        // Highest slab achieved
        $achievedRank = null;
        foreach ($slabs as $s) {
            if ($matched >= $s['volume']) $achievedRank = $s['rank'];
        }

        // paid this month already
        $paidThisMonth = (float) DB::table('_payout')
            ->where('to_user_id', $userId)
            ->whereIn('type', ['repurchase_salary','repurchase_salary_weekly'])
            ->whereBetween('created_at', [$mStart, $mEnd])
            ->sum('amount');

        // current due installment (first unpaid within month)
        $due = DB::table('repurchase_salary_installments as i')
            ->join('repurchase_salary_qualifications as q', 'q.id', '=', 'i.qualification_id')
            ->where('q.sponsor_id', $userId)
            ->whereBetween(DB::raw('DATE(i.due_month)'), [$mStart->toDateString(), $mEnd->toDateString()])
            ->select('i.id','i.amount','i.due_month','i.paid_at','q.months_total','q.months_paid')
            ->orderBy('i.due_month','asc')
            ->first();

        $summary = [
            'left' => $placementCombinedLeft,
            'right' => $placementCombinedRight,
            'matched' => $matched,
            'paid_this_month' => $paidThisMonth,
            'due' => $due,
        ];

        return Inertia::render('Income/VipRepurchaseSalary', [
            'month' => $mStart->format('Y-m'),
            'slabs' => $slabs,
            'summary' => $summary,
            'achieved_rank' => $achievedRank,
            // extras for frontend breakdown display
            'team_combined' => [
                'left' => $teamCombinedLeft,
                'right' => $teamCombinedRight,
                'matched' => $teamCombinedMatched,
                'sell_left' => $teamSells['left'],
                'sell_right' => $teamSells['right'],
                'rep_left' => $teamRep['left'],
                'rep_right' => $teamRep['right'],
            ],
            'placement_combined' => [
                'left' => $placementCombinedLeft,
                'right' => $placementCombinedRight,
                'matched' => $placementCombinedMatched,
                'sell_left' => $placementSellLeft,
                'sell_right' => $placementSellRight,
                'rep_left' => $placementRepLeft,
                'rep_right' => $placementRepRight,
            ],
        ]);
    }

    protected function teamCte(): string
    {
        return "
WITH RECURSIVE team AS (
  SELECT id, referral_id, refer_by, position, 1 AS lvl
  FROM users
  WHERE refer_by = ?

  UNION ALL

  SELECT u.id, u.referral_id, u.refer_by, u.position, t.lvl + 1
  FROM users u
  JOIN team t ON u.refer_by = t.referral_id
)
";
    }
}
