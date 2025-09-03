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
     * Month-wise TEAM repurchase volume (Left/Right) from `repurchase` table.
     * Tree rule: child.refer_by = parent.referral_id
     * Leg comes from users.position (L/R)
     * Page: Income/VipRepurchaseSalary
     *
     * GET /income/vip-repurchase-salary?month=YYYY-MM
     */
    public function index(Request $request)
    {
        $userId = (int) Auth::id();
        abort_unless($userId, 401);

        // Month window
        $monthStr = $request->query('month') ?: now()->format('Y-m');
        $mStart   = Carbon::parse($monthStr.'-01')->startOfMonth();
        $mEnd     = (clone $mStart)->endOfMonth();

        // Team root = my referral_id
        $myReferral = (string) DB::table('users')->where('id', $userId)->value('referral_id');

        // Slabs (static – same as your page)
        $slabs = [
            ['rank' => 'VIP 1', 'volume' =>   30000,  'salary' =>   1000],
            ['rank' => 'VIP 2', 'volume' =>  100000,  'salary' =>   3000],
            ['rank' => 'VIP 3', 'volume' =>  200000,  'salary' =>   5000],
            ['rank' => 'VIP 4', 'volume' =>  500000,  'salary' =>  10000],
            ['rank' => 'VIP 5', 'volume' => 1000000,  'salary' =>  25000],
            ['rank' => 'VIP 6', 'volume' => 2000000,  'salary' =>  50000],
            ['rank' => 'VIP 7', 'volume' => 5000000,  'salary' => 100000],
        ];

        // Default empty response
        $summary = [
            'left'            => 0.0,
            'right'           => 0.0,
            'matched'         => 0.0,
            'paid_this_month' => 0.0,
            'due'             => null,
        ];
        $achieved = null;

        if ($myReferral) {
            // TEAM repurchase L/R from `repurchase` table (status='paid')
            $sql = "
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
  UPPER(COALESCE(u.position,'NA')) AS leg,
  COUNT(*)                          AS orders,
  SUM(r.amount)                     AS amount
FROM team u
JOIN repurchase r ON r.buyer_id = u.id
WHERE r.status='paid'
  AND r.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(u.position,'NA'));
";
            $rows = DB::select($sql, [$myReferral, $mStart, $mEnd]);

            $L = 0.0; $R = 0.0;
            foreach ($rows as $r) {
                if ($r->leg === 'L') $L += (float) $r->amount;
                if ($r->leg === 'R') $R += (float) $r->amount;
            }
            $matched = min($L, $R);

            // Highest slab achieved
            foreach ($slabs as $s) {
                if ($matched >= $s['volume']) {
                    $achieved = $s['rank'];
                }
            }

            // Payouts this month (already paid)
            $paidThisMonth = (float) DB::table('_payout')
                ->where('to_user_id', $userId)
                ->where('type', 'repurchase_salary')
                ->whereBetween('created_at', [$mStart, $mEnd])
                ->sum('amount');

            // Current due installment (if any)
            $due = DB::table('repurchase_salary_installments as i')
                ->join('repurchase_salary_qualifications as q', 'q.id', '=', 'i.qualification_id')
                ->where('q.sponsor_id', $userId)
                ->whereDate('i.due_month', $mStart->toDateString())
                ->select('i.id','i.amount','i.due_month','i.paid_at','q.months_total','q.months_paid')
                ->first();

            $summary = [
                'left'            => $L,
                'right'           => $R,
                'matched'         => $matched,
                'paid_this_month' => $paidThisMonth,
                'due'             => $due,
            ];
        }

        return Inertia::render('Income/VipRepurchaseSalary', [
            'month'   => $mStart->format('Y-m'),
            'slabs'   => $slabs,
            'summary' => $summary,
            'achieved_rank' => $achieved,
        ]);
    }
}
