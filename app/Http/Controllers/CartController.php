<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CartController extends Controller
{
   // app/Http/Controllers/CartController.php
public function show(Request $request)
{
    $uid = $request->user()->id;

    $walletBalance = (float) DB::table('wallet')
        ->where('user_id', $uid)
        ->selectRaw("COALESCE(SUM(REPLACE(amount, ',', '') + 0), 0) AS bal")
        ->value('bal');

    $defaultAddress = DB::table('address')
        ->where('user_id', $uid)
        ->orderByDesc('is_default')
        ->orderByDesc('id')
        ->select('id','name','phone','line1','line2','city','state','pincode','country','is_default')
        ->first();

    $addressCount = DB::table('address')->where('user_id', $uid)->count();

    // ⛔️ extras OFF
    $charges = [
        'shipping'    => 0,
        'packaging'   => 0,
        'convenience' => 0,
        'gst_percent' => 0,
    ];

    return Inertia::render('Card', [
        'walletBalance'  => $walletBalance,
        'defaultAddress' => $defaultAddress,
        'addressCount'   => $addressCount,
        'charges'        => $charges, // frontend total इसी से 0 रहेगा
    ]);
}

}
