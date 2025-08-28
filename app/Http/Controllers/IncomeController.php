<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class IncomeController extends Controller
{
    public function index(Request $request)
    {
        $uid = Auth::id();

        // ----- STAR INCOME (Star Awards) -----
        $starTotal = (float) DB::table('_payout')
            ->where('to_user_id', $uid)
            ->where('type', 'star_award')
            ->where('status', 'paid')
            ->sum('amount');

        $starAwards = DB::table('star_rank_awards')
            ->where('sponsor_id', $uid)
            ->orderByDesc('awarded_at')
            ->get(['rank_no', 'reward_amount', 'awarded_at']);

        // ----- REPURCHASE SALARY -----
        $repTotalPaid = (float) DB::table('_payout')
            ->where('to_user_id', $uid)
            ->where('type', 'repurchase_salary')
            ->where('status', 'paid')
            ->sum('amount');

        $repPending = DB::table('repurchase_salary_installments as i')
            ->join('repurchase_salary_qualifications as q','q.id','=','i.qualification_id')
            ->where('i.sponsor_id', $uid)
            ->whereNull('i.paid_at')
            ->where('q.status','active')
            ->orderBy('i.due_month')
            ->get([
                'i.id','i.due_month','i.amount','q.vip_no','q.period_month'
            ]);

        $nextDue = $repPending->first()
            ? ['month' => $repPending->first()->due_month, 'amount' => (float)$repPending->first()->amount]
            : null;

        $repHistory = DB::table('repurchase_salary_installments as i')
            ->join('repurchase_salary_qualifications as q','q.id','=','i.qualification_id')
            ->where('i.sponsor_id', $uid)
            ->orderByDesc('i.due_month')
            ->limit(12)
            ->get([
                'i.due_month','i.amount','i.paid_at','q.vip_no','q.period_month'
            ]);

        return Inertia::render('Income/Overview', [
            'star' => [
                'total_paid' => $starTotal,
                'awards'     => $starAwards,
            ],
            'repurchase_salary' => [
                'total_paid' => $repTotalPaid,
                'pending'    => $repPending,
                'next_due'   => $nextDue,
                'history'    => $repHistory,
            ],
        ]);
    }
}
