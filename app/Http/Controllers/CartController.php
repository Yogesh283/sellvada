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

        // Balance = SUM(credits) - SUM(debits)
    $walletBalance = (float) \DB::table('wallet')
    ->where('user_id', $uid)
    ->selectRaw("COALESCE(SUM(REPLACE(amount, ',', '') + 0), 0) AS bal")
    ->value('bal');



        return Inertia::render('Card', [
            'walletBalance' => $walletBalance,   // 👈 this is what you read in Card.jsx
        ]);
    }
}
