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
     * Returns sell+repurchase team & placement totals (monthly) and slab/matched info.
     */
    public function index(Request $request)
    {
        $userId = (int) Auth::id();
        abort_unless($userId, 401);

        // parse month (default current month)
        $monthStr = $request->query('month') ?: now()->format('Y-m');
        try {
            $mStart = Carbon::parse($monthStr . '-01')->startOfMonth();
        } catch (\Throwable $e) {
            $mStart = now()->startOfMonth();
        }
        $mEnd = (clone $mStart)->endOfMonth();

        // referral root (for team/referral tree)
        $myReferral = DB::table('users')->where('id', $userId)->value('referral_id');
        $myReferral = is_null($myReferral) ? null : (string)$myReferral;

        // load slabs from DB if present, fallback otherwise
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
                return [
                  'rank' => ($r->rank ?? ('VIP ' . ($r->vip_no ?? '?'))),
                  'volume' => (float) $r->threshold_volume,
                  'salary' => (float) $r->salary_amount,
                  'vip_no' => $r->vip_no ?? null,
                ];
            })->toArray();
        }

        // default summaries
        $summary = [
            'left' => 0.0,
            'right' => 0.0,
            'matched' => 0.0,
            'paid_this_month' => 0.0,
            'due' => null,
        ];

        // Team (referral) CTE — used for sell + repurchase totals
        $teamCte = "
WITH RECURSIVE team AS (
  SELECT id, referral_id, refer_by, position
  FROM users
  WHERE refer_by = ?

  UNION ALL

  SELECT u.id, u.referral_id, u.refer_by, u.position
  FROM users u
  JOIN team t ON u.refer_by = t.referral_id
)
";

        // initialize containers
        $teamSells = ['left'=>0.0,'right'=>0.0,'cnt_left'=>0,'cnt_right'=>0];
        $teamRepurchases = ['left'=>0.0,'right'=>0.0,'cnt_left'=>0,'cnt_right'=>0];

        // if referral root exists compute monthly sums for both sell and repurchase
        if ($myReferral) {
            // team sells for selected month
            $tsSql = $teamCte . "
SELECT UPPER(COALESCE(u.position,'NA')) AS leg, COALESCE(SUM(s.amount),0) AS amt, COUNT(s.id) AS cnt
FROM team u
JOIN sell s ON s.buyer_id = u.id
WHERE s.status='paid' AND s.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(u.position,'NA'))
";
            $tsRows = DB::select($tsSql, [$myReferral, $mStart, $mEnd]);
            foreach ($tsRows as $r) {
                if ($r->leg === 'L') { $teamSells['left'] = (float)$r->amt; $teamSells['cnt_left'] = (int)$r->cnt; }
                if ($r->leg === 'R') { $teamSells['right'] = (float)$r->amt; $teamSells['cnt_right'] = (int)$r->cnt; }
            }

            // team repurchases for selected month
            $trSql = $teamCte . "
SELECT UPPER(COALESCE(u.position,'NA')) AS leg, COALESCE(SUM(r.amount),0) AS amt, COUNT(r.id) AS cnt
FROM team u
JOIN repurchase r ON r.buyer_id = u.id
WHERE r.status='paid' AND r.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(u.position,'NA'))
";
            $trRows = DB::select($trSql, [$myReferral, $mStart, $mEnd]);
            foreach ($trRows as $r) {
                if ($r->leg === 'L') { $teamRepurchases['left'] = (float)$r->amt; $teamRepurchases['cnt_left'] = (int)$r->cnt; }
                if ($r->leg === 'R') { $teamRepurchases['right'] = (float)$r->amt; $teamRepurchases['cnt_right'] = (int)$r->cnt; }
            }
        } else {
            // fallback: if no referral root, try sponsor-based aggregation on sell (monthly)
            $teamSells['left'] = (float) DB::table('sell')
                ->where('sponsor_id', $userId)
                ->where('status','paid')
                ->whereBetween('created_at', [$mStart, $mEnd])
                ->where('leg','L')
                ->sum('amount');
            $teamSells['right'] = (float) DB::table('sell')
                ->where('sponsor_id', $userId)
                ->where('status','paid')
                ->whereBetween('created_at', [$mStart, $mEnd])
                ->where('leg','R')
                ->sum('amount');
            // repurchase fallback left/right not straightforward — leave zero
        }

        // Combined team totals (sell + repurchase) for the month
        $teamCombined = [
            'left' => $teamSells['left'] + $teamRepurchases['left'],
            'right' => $teamSells['right'] + $teamRepurchases['right'],
        ];
        $teamCombined['matched'] = min($teamCombined['left'], $teamCombined['right']);

        // Placement CTE (left_user_id / right_user_id) - monthly totals
        $uid = $userId;
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

        // placement sells (monthly)
        $placementSell = ['left'=>0.0,'right'=>0.0,'cnt_left'=>0,'cnt_right'=>0];
        $psSql = $placementCte . "
SELECT UPPER(COALESCE(t.root_leg,'NA')) AS leg, COALESCE(SUM(s.amount),0) AS amt, COUNT(s.id) AS cnt
FROM team t
JOIN sell s ON s.buyer_id = t.id
WHERE s.status='paid' AND s.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(t.root_leg,'NA'))
";
        $psRows = DB::select($psSql, [$uid, $leftRoot, $rightRoot, $mStart, $mEnd]);
        foreach ($psRows as $r) {
            if ($r->leg === 'L') { $placementSell['left'] = (float)$r->amt; $placementSell['cnt_left'] = (int)$r->cnt; }
            if ($r->leg === 'R') { $placementSell['right'] = (float)$r->amt; $placementSell['cnt_right'] = (int)$r->cnt; }
        }

        // placement repurchase (monthly)
        $placementRep = ['left'=>0.0,'right'=>0.0,'cnt_left'=>0,'cnt_right'=>0];
        $prSql = $placementCte . "
SELECT UPPER(COALESCE(t.root_leg,'NA')) AS leg, COALESCE(SUM(r.amount),0) AS amt, COUNT(r.id) AS cnt
FROM team t
JOIN repurchase r ON r.buyer_id = t.id
WHERE r.status='paid' AND r.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(t.root_leg,'NA'))
";
        $prRows = DB::select($prSql, [$uid, $leftRoot, $rightRoot, $mStart, $mEnd]);
        foreach ($prRows as $r) {
            if ($r->leg === 'L') { $placementRep['left'] = (float)$r->amt; $placementRep['cnt_left'] = (int)$r->cnt; }
            if ($r->leg === 'R') { $placementRep['right'] = (float)$r->amt; $placementRep['cnt_right'] = (int)$r->cnt; }
        }

        $placementCombined = [
            'left' => $placementSell['left'] + $placementRep['left'],
            'right' => $placementSell['right'] + $placementRep['right'],
        ];
        $placementCombined['matched'] = min($placementCombined['left'], $placementCombined['right']);

        // Highest slab achieved (based on teamCombined.matched)
        $matched = $teamCombined['matched'];
        $achieved = null;
        foreach ($slabs as $s) {
            if ($matched >= (float)$s['volume']) {
                $achieved = $s['rank'];
            }
        }

        // payouts this month (already paid)
        $paidThisMonth = (float) DB::table('_payout')
            ->where('to_user_id', $userId)
            ->whereIn('type', ['repurchase_salary', 'repurchase_salary_weekly'])
            ->whereBetween('created_at', [$mStart, $mEnd])
            ->sum('amount');

        // current due installment in this month (first unpaid if any)
        $dueRows = DB::table('repurchase_salary_installments as i')
            ->join('repurchase_salary_qualifications as q', 'q.id', '=', 'i.qualification_id')
            ->where('q.sponsor_id', $userId)
            ->whereBetween(DB::raw('DATE(i.due_month)'), [$mStart->toDateString(), $mEnd->toDateString()])
            ->select('i.id','i.amount','i.due_month','i.paid_at','q.months_total','q.months_paid','q.vip_no','q.salary_amount')
            ->orderBy('i.due_month','asc')
            ->get();

        $dueFirst = null;
        foreach ($dueRows as $d) {
            if (is_null($d->paid_at)) { $dueFirst = $d; break; }
        }

        $summary = [
            'left' => $teamCombined['left'],
            'right' => $teamCombined['right'],
            'matched' => $matched,
            'paid_this_month' => $paidThisMonth,
            'due' => $dueFirst,
        ];

        return Inertia::render('Income/VipRepurchaseSalary', [
            'month' => $mStart->format('Y-m'),
            'slabs' => $slabs,
            'summary' => $summary,
            'achieved_rank' => $achieved,
            'team_sells' => $teamSells,
            'team_repurchases' => $teamRepurchases,
            'team_combined' => $teamCombined,
            'placement_sells' => $placementSell,
            'placement_repurchases' => $placementRep,
            'placement_combined' => $placementCombined,
        ]);
    }
}
