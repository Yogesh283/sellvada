<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CartController extends Controller
{
    public function show(Request $request)
    {
        $uid = $request->user()->id;

        // Wallet balance (robust even if amounts are stored as strings with commas)
        $walletBalance = (float) DB::table('wallet')
            ->where('user_id', $uid)
            ->selectRaw("COALESCE(SUM(REPLACE(amount, ',', '') + 0), 0) AS bal")
            ->value('bal');

        // Default (or latest) shipping address for this user
        $defaultAddress = DB::table('address')
            ->where('user_id', $uid)
            ->orderByDesc('is_default')   // prefer default
            ->orderByDesc('id')           // else latest
            ->select('id','name','phone','line1','line2','city','state','pincode','country','is_default')
            ->first();

        $addressCount = DB::table('address')->where('user_id', $uid)->count();

        return Inertia::render('Card', [
            'walletBalance'  => $walletBalance,
            'defaultAddress' => $defaultAddress,   // 👈 React will show this
            'addressCount'   => $addressCount,
        ]);
    }
}
