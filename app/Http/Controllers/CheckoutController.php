<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    private const DEFAULT_LEG     = 'L';       // fallback for leg
    private const DEFAULT_SHIP    = 49.0;      // default shipping
    private const INCOME_TYPE_VAL = 'DIRECT';  // change to 0 if column is TINYINT

    public function store(Request $request)
    {
        // 1) Validate cart
        $data = $request->validate([
            'items'             => ['required','array','min:1'],
            'items.*.id'        => ['required'],
            'items.*.name'      => ['required','string','max:100'],
            'items.*.price'     => ['required','numeric','min:0'],
            'items.*.qty'       => ['required','integer','min:1'],
            'items.*.variant'   => ['nullable','string','max:100'],
            'items.*.type'      => ['nullable','string','max:32'],
            'coupon'            => ['nullable','string','max:32'],
            'shipping'          => ['nullable','numeric','min:0'],
        ]);

        $user = Auth::user();
        abort_unless($user, 401);

        // 2) Common fields
        $orderNo   = 'ORD-'.Str::upper(Str::random(8));      // same for all items in this checkout
        $leg       = $user->position ?: self::DEFAULT_LEG;   // 'L' / 'R'
        $sponsorId = null;                                   // screenshot me NULL; chahe to $user->sponsor_id use kar lo

        // 3) Totals (wallet deduction ke liye)
        $subTotal = 0.0;
        foreach ($data['items'] as $it) {
            $subTotal += (float)$it['price'] * (int)$it['qty'];
        }
        $coupon   = strtoupper(trim((string)($data['coupon'] ?? '')));
        $discount = $coupon === 'FLAT10' ? round($subTotal * 0.10, 2) : 0.0;

        $taxable  = max(0, $subTotal - $discount);
        $tax      = round($taxable * 0.05, 2);
        $shipping = array_key_exists('shipping', $data) ? (float)$data['shipping'] : self::DEFAULT_SHIP;
        $grand    = round($taxable + $tax + $shipping, 2);   // wallet se itna katna

        // 4) All-or-nothing: wallet lock+deduct -> insert sell rows
        try {
            DB::transaction(function () use ($user, $data, $orderNo, $leg, $sponsorId, $coupon, $tax, $shipping, $grand) {

                // --- lock wallet ---
                $wallet = DB::table('wallet')->where('user_id', $user->id)->lockForUpdate()->first();
                if (!$wallet || (float)$wallet->amount < $grand) {
                    throw new \RuntimeException('INSUFFICIENT_FUNDS');
                }
                DB::table('wallet')
                    ->where('user_id', $user->id)
                    ->update(['amount' => DB::raw('amount - '.number_format($grand, 2, '.', ''))]);

                // --- sell rows (exactly like screenshot) ---
                foreach ($data['items'] as $it) {
                    $qty       = (int)$it['qty'];
                    $price     = (float)$it['price'];
                    $lineTotal = round($price * $qty, 2); // <-- sell.amount = product total ONLY

                    // derive type (prefer explicit, else "(Silver)" from name)
                    $type = null;
                    if (!empty($it['type'])) {
                        $type = strtolower(trim($it['type']));
                    } elseif (preg_match('/\(([^)]+)\)\s*$/', (string)$it['name'], $m)) {
                        $type = strtolower(trim($m[1]));
                    }

                    $details = [
                        'qty'      => $qty,
                        'price'    => $price,
                        'variant'  => $it['variant'] ?? null,
                        'coupon'   => $coupon ?: null,
                        'tax'      => $tax,
                        'shipping' => $shipping,
                        'type'     => $type,
                    ];

                    DB::table('sell')->insert([
                        'buyer_id'          => $user->id,
                        'sponsor_id'        => $sponsorId,                 // NULL ok
                        'income_to_user_id' => null,                       // screenshot-style
                        'leg'               => $leg,                       // 'L' / 'R'
                        'product'           => $it['name'],                // e.g. "Superfruit Mix (Silver)"
                        'amount'            => $lineTotal,                 // 3000.00
                        'income'            => 0.00,
                        'income_type'       => self::INCOME_TYPE_VAL,      // 'DIRECT' (or 0 if numeric)
                        'level'             => null,
                        'order_no'          => $orderNo,                   // e.g. ORD-DAQKRYMB
                        'status'            => 'paid',
                        'details'           => json_encode($details, JSON_UNESCAPED_UNICODE),
                        'type'              => $type,                      // e.g. 'silver'
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_FUNDS') {
                return back()->withErrors(['wallet' => 'Insufficient wallet balance.']);
            }
            throw $e;
        }

        return back()->with('success', 'Order placed successfully!');
    }
}
