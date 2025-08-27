<?php

namespace App\Http\Controllers; // 👈 VERY IMPORTANT

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CheckoutController extends Controller
{
    public function store(Request $request)
    {
        $uid = $request->user()->id;

        // 1) Validate payload
        $data = Validator::make($request->all(), [
            'items'            => ['required','array','min:1'],
            'items.*.id'       => ['required'],
            'items.*.price'    => ['required','numeric','min:0'],
            'items.*.qty'      => ['required','integer','min:1'],
            'coupon'           => ['nullable','string'],
            'shipping'         => ['required','numeric','min:0'],
        ])->validate();

        // 2) Recompute totals
        $subTotal = 0.0;
        foreach ($data['items'] as $it) {
            $subTotal += ((float)$it['price']) * ((int)$it['qty']);
        }

        $discount = (!empty($data['coupon']) && strtoupper($data['coupon']) === 'FLAT10')
            ? round($subTotal * 0.10) : 0.0;

        $taxable = max(0.0, $subTotal - $discount);
        $tax     = round($taxable * 0.05);
        $grand   = $taxable + $tax + (count($data['items']) ? (float)$data['shipping'] : 0.0);

        // 3) Ensure wallet row exists (single balance approach)
        $current = DB::table('wallet')->where('user_id', $uid)->value('amount');
        if ($current === null) {
            if ($grand > 0) {
                return back()->withErrors(['wallet' => 'Insufficient wallet balance.']);
            }
        }

        // 4) Atomic deduction (race-safe)
        $affected = DB::update(
            "UPDATE wallet SET amount = amount - ? WHERE user_id = ? AND amount >= ?",
            [number_format($grand, 2, '.', ''), $uid, number_format($grand, 2, '.', '')]
        );

        if ($affected !== 1) {
            return back()->withErrors(['wallet' => 'Insufficient wallet balance.']);
        }

        // (Optional) Create order rows inside a transaction with the same update

        return back()->with('status', 'Order placed & wallet debited successfully!');
    }
}
