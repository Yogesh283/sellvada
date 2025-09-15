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
     * Renders the UI and returns summary values.
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

        // Team root = my referral_id
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

        // Default summary
        $summary = [
            'left' => 0.0,
            'right' => 0.0,
            'matched' => 0.0,
            'paid_this_month' => 0.0,
            'due' => null,
        ];
        $achieved = null;

        if ($myReferral) {
            // Compute team L/R repurchase from repurchase table for the month
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
SELECT UPPER(COALESCE(u.position,'NA')) AS leg,
       COUNT(*) AS orders,
       COALESCE(SUM(r.amount),0) AS amount
FROM team u
JOIN repurchase r ON r.buyer_id = u.id
WHERE r.status='paid'
  AND r.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(u.position,'NA'));
";
            $rows = DB::select($sql, [$myReferral, $mStart, $mEnd]);

            $L = 0.0; $R = 0.0;
            foreach ($rows as $r) {
                if ($r->leg === 'L') $L += (float)$r->amount;
                if ($r->leg === 'R') $R += (float)$r->amount;
            }
            $matched = min($L, $R);

            // Highest slab achieved
            foreach ($slabs as $s) {
                if ($matched >= $s['volume']) {
                    $achieved = $s['rank'];
                }
            }

            // Payouts this month (monthly + weekly types)
            $paidThisMonth = (float) DB::table('_payout')
                ->where('to_user_id', $userId)
                ->whereIn('type', ['repurchase_salary','repurchase_salary_weekly'])
                ->whereBetween('created_at', [$mStart, $mEnd])
                ->sum('amount');

            // Current due installment in this month window (first unpaid if any)
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

            $summary = [
                'left' => $L,
                'right' => $R,
                'matched' => $matched,
                'paid_this_month' => $paidThisMonth,
                'due' => $dueFirst,
            ];
        }

        // Render page with props
        return Inertia::render('Income/VipRepurchaseSalary', [
            'month' => $mStart->format('Y-m'),
            'slabs' => $slabs,
            'summary' => $summary,
            'achieved_rank' => $achieved,
        ]);
    }

    /**
     * POST /income/vip-repurchase-salary/close-week
     * Body: { week?: 'YYYY-MM-DD' }  // optional — any date inside target Mon-Sun; if omitted closes previous week
     *
     * Behavior:
     * - Validates the user has at least one self repurchase (status=paid) in that week
     * - Computes team L/R repurchase sums for Mon→Sun
     * - Picks highest slab; creates or upgrades repurchase_salary_qualifications for that week
     * - Creates 3 weekly installments due next 3 Mondays (first due = Monday after the week)
     * - Upgrades unpaid installments if higher slab later found
     */
    public function closeWeek(Request $request)
    {
        $userId = (int) Auth::id();
        abort_unless($userId, 401);

        // Optional week param: any date inside the target Mon-Sun
        $weekStr = $request->input('week');
        if ($weekStr) {
            try {
                $any = Carbon::parse($weekStr);
            } catch (\Throwable $e) {
                return redirect()->back()->with('error', 'Invalid date format for week.');
            }
        } else {
            // default: previous calendar week
            $any = now()->subWeek()->startOfWeek();
        }

        $weekStart = $any->copy()->startOfWeek(); // Monday 00:00
        $weekEnd = $weekStart->copy()->endOfWeek(); // Sunday 23:59:59

        // Resolve referral root for this user
        $myReferral = DB::table('users')->where('id', $userId)->value('referral_id');
        if (!$myReferral) {
            return redirect()->back()->with('error', 'Your referral root is not set — cannot compute team for weekly closing.');
        }

        // Must have at least one self repurchase in the week
        $hasSelf = DB::table('repurchase')
            ->where('buyer_id', $userId)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->exists();

        if (!$hasSelf) {
            return redirect()->back()->with('error', 'No self repurchase found in the selected week — cannot close.');
        }

        // Load slabs (fallback if empty)
        $slabsRaw = DB::table('repurchase_salary_slabs')->orderBy('threshold_volume','asc')->get();
        if ($slabsRaw->isEmpty()) {
            $slabs = collect([
                (object)['vip_no'=>1, 'threshold_volume'=>30000, 'salary_amount'=>1000],
                (object)['vip_no'=>2, 'threshold_volume'=>100000, 'salary_amount'=>3000],
                (object)['vip_no'=>3, 'threshold_volume'=>200000, 'salary_amount'=>5000],
                (object)['vip_no'=>4, 'threshold_volume'=>500000, 'salary_amount'=>10000],
                (object)['vip_no'=>5, 'threshold_volume'=>1000000, 'salary_amount'=>25000],
                (object)['vip_no'=>6, 'threshold_volume'=>2000000, 'salary_amount'=>50000],
                (object)['vip_no'=>7, 'threshold_volume'=>5000000, 'salary_amount'=>100000],
            ]);
        } else {
            $slabs = $slabsRaw;
        }

        // Compute team L/R repurchase sums for the week
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
SELECT UPPER(COALESCE(u.position,'NA')) AS leg, COALESCE(SUM(r.amount),0) AS amount
FROM team u
JOIN repurchase r ON r.buyer_id = u.id
WHERE r.status='paid'
  AND r.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(u.position,'NA'));
";
        $rows = DB::select($sql, [$myReferral, $weekStart, $weekEnd]);

        $volL = 0.0; $volR = 0.0;
        foreach ($rows as $r) {
            if ($r->leg === 'L') $volL += (float)$r->amount;
            if ($r->leg === 'R') $volR += (float)$r->amount;
        }
        $matched = min($volL, $volR);

        // Pick highest matching slab
        $qualified = null;
        foreach ($slabs as $s) {
            // slab may be object (DB) or array (fallback)
            $threshold = is_array($s) ? (float)$s['volume'] : (float)($s->threshold_volume ?? 0);
            if ($matched >= $threshold) {
                $qualified = $s;
            }
        }

        if (!$qualified) {
            return redirect()->back()->with('error', 'No slab matched for the week (matched = ' . number_format($matched,2) . ').');
        }

        DB::beginTransaction();
        try {
            // period marker stores week-start date (YYYY-MM-DD) for weekly
            $periodMarker = $weekStart->toDateString();
            $firstPayMonday = $weekStart->copy()->addWeek()->startOfWeek(); // next Monday

            $existing = DB::table('repurchase_salary_qualifications')
                ->where('sponsor_id', $userId)
                ->where('period_month', $periodMarker)
                ->lockForUpdate()
                ->first();

            // Determine vip_no and salary_amount for selected slab
            if (is_array($qualified)) {
                $vip_no = $qualified['vip_no'] ?? null;
                $salary_amount = $qualified['salary'] ?? $qualified['salary_amount'] ?? 0;
            } else {
                $vip_no = $qualified->vip_no ?? null;
                $salary_amount = $qualified->salary_amount ?? 0;
            }

            if (!$existing) {
                $qid = DB::table('repurchase_salary_qualifications')->insertGetId([
                    'sponsor_id' => $userId,
                    'period_month' => $periodMarker,
                    'vip_no' => $vip_no,
                    'salary_amount' => $salary_amount,
                    'months_total' => 3,
                    'months_paid' => 0,
                    'first_payout_month' => $firstPayMonday->toDateString(),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // create 3 weekly installments due on consecutive Mondays
                for ($i=0; $i<3; $i++) {
                    $due = $firstPayMonday->copy()->addWeeks($i)->startOfWeek();
                    DB::table('repurchase_salary_installments')->insert([
                        'qualification_id' => $qid,
                        'sponsor_id' => $userId,
                        'due_month' => $due->toDateString(),
                        'amount' => number_format((float)$salary_amount, 2, '.', ''),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } else {
                // upgrade if higher VIP
                $existingVip = (int)($existing->vip_no ?? 0);
                $newVip = (int)($vip_no ?? 0);
                if ($newVip > $existingVip) {
                    DB::table('repurchase_salary_qualifications')->where('id', $existing->id)
                        ->update([
                            'vip_no' => $newVip,
                            'salary_amount' => $salary_amount,
                            'updated_at' => now(),
                        ]);

                    // update unpaid installments to new amount
                    DB::table('repurchase_salary_installments')
                        ->where('qualification_id', $existing->id)
                        ->whereNull('paid_at')
                        ->update([
                            'amount' => number_format((float)$salary_amount, 2, '.', ''),
                            'updated_at' => now(),
                        ]);
                }
            }

            DB::commit();
            return redirect()->back()->with('success', 'Week closed: ' . $weekStart->toDateString() . ' → ' . $weekEnd->toDateString() . '. Qualified VIP' . $vip_no . ' (matched ' . number_format($matched, 2) . ').');
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('closeWeek error: '.$e->getMessage());
            return redirect()->back()->with('error', 'Failed to close week: ' . $e->getMessage());
        }
    }
}
