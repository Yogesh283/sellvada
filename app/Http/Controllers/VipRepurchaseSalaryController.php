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
        $userId = Auth::id();

        // Month filter (YYYY-MM)
        $monthStr = $request->query('month') ?: now()->format('Y-m');
        $mStart   = Carbon::parse($monthStr.'-01')->startOfMonth();
        $mEnd     = (clone $mStart)->endOfMonth();

        // --- Monthly repurchase volumes (Left / Right) ---
        $rows = DB::table('sell')
            ->select('leg', 'amount')
            ->where('status', 'paid')
            ->where('type', 'repurchase')
            ->where('sponsor_id', $userId)
            ->whereBetween('created_at', [$mStart, $mEnd])
            ->get();

        $L = 0.0; $R = 0.0;
        foreach ($rows as $r) {
            $leg = strtoupper((string)($r->leg ?? ''));
            $amt = (float)$r->amount;
            if ($leg === 'L') $L += $amt;
            elseif ($leg === 'R') $R += $amt;
        }
        $matched = min($L, $R);

        // --- Slabs (VIP 1..7) ---
        $slabs = [
            ['rank' => 'VIP 1', 'volume' =>  30000,   'salary' =>   1000],
            ['rank' => 'VIP 2', 'volume' => 100000,   'salary' =>   3000],
            ['rank' => 'VIP 3', 'volume' => 200000,   'salary' =>   5000],
            ['rank' => 'VIP 4', 'volume' => 500000,   'salary' =>  10000],
            ['rank' => 'VIP 5', 'volume' => 1000000,  'salary' =>  25000],
            ['rank' => 'VIP 6', 'volume' => 2000000,  'salary' =>  50000],
            ['rank' => 'VIP 7', 'volume' => 5000000,  'salary' => 100000],
        ];

        $achieved = null;
        foreach ($slabs as $s) {
            if ($matched >= $s['volume']) {
                $achieved = $s['rank'];
            }
        }

        // --- Payouts this month ---
        $paidThisMonth = (float) DB::table('_payout')
            ->where('to_user_id', $userId)
            ->where('type', 'repurchase_salary')
            ->whereBetween('created_at', [$mStart, $mEnd])
            ->sum('amount');

        // --- Due installment for this month (NO q.rank_no here) ---
        $due = DB::table('repurchase_salary_installments as i')
            ->join('repurchase_salary_qualifications as q', 'q.id', '=', 'i.qualification_id')
            ->where('q.sponsor_id', $userId)
            ->whereDate('i.due_month', $mStart->toDateString())
            ->select(
                'i.id',
                'i.amount',
                'i.due_month',
                'i.paid_at',
                'q.months_total',
                'q.months_paid'
            )
            ->first();

        return Inertia::render('Income/VipRepurchaseSalary', [
            'month' => $mStart->format('Y-m'),
            'slabs' => $slabs,
            'summary' => [
                'left'            => $L,
                'right'           => $R,
                'matched'         => $matched,
                'achieved_rank'   => $achieved,
                'paid_this_month' => $paidThisMonth,
                'due'             => $due,
            ],
        ]);
    }
}
