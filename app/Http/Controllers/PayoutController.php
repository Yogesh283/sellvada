<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PayoutController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = Auth::id();

        // Optional filters from query string
        $status = $request->query('status');      // paid | pending | cancelled
        $method = $request->query('method');      // closing_1 | closing_2 | anything you use
        $type   = $request->query('type');        // binary_matching etc.

        // Totals for cards
        $sumPaid = (float) DB::table('_payout')
            ->where('user_id', $userId)
            ->where('status', 'paid')
            ->sum('amount');

        $sumPending = (float) DB::table('_payout')
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->sum('amount');

        $todayPaid = (float) DB::table('_payout')
            ->where('user_id', $userId)
            ->where('status', 'paid')
            ->whereDate('created_at', now()->toDateString())
            ->sum('amount');

        $monthPaid = (float) DB::table('_payout')
            ->where('user_id', $userId)
            ->where('status', 'paid')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        // List (paginated)
        $payouts = DB::table('_payout')
            ->select('id','created_at','amount','status','method','type')
            ->where('user_id', $userId)
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($method, fn($q) => $q->where('method', $method))
            ->when($type,   fn($q) => $q->where('type', $type))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Payouts', [
            'payouts' => $payouts,
            'filters' => [
                'status' => $status,
                'method' => $method,
                'type'   => $type,
            ],
            'stats' => [
                'sumPaid'    => $sumPaid,
                'sumPending' => $sumPending,
                'todayPaid'  => $todayPaid,
                'monthPaid'  => $monthPaid,
            ],
        ]);
    }
}
