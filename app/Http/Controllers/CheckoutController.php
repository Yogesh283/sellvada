<?php

namespace App\Http\Controllers;

use App\Models\Sell;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        // 1) Validate payload
        $data = $request->validate([
            'items'             => ['required', 'array', 'min:1'],
            'items.*.id'        => ['required'],
            'items.*.name'      => ['required','string','max:120'],
            'items.*.price'     => ['required','numeric','min:0'],
            'items.*.qty'       => ['required','integer','min:1'],
            'items.*.variant'   => ['nullable','string','max:120'],
            'items.*.type'      => ['nullable','string','max:32'],
            'coupon'            => ['nullable','string','max:32'],
            'shipping'          => ['required','numeric','min:0'],
        ]);

        // 2) Totals (server-side)
        $subTotal = 0.0;
        foreach ($data['items'] as $it) {
            $subTotal += (float)$it['price'] * (int)$it['qty'];
        }

        $coupon   = strtoupper(trim((string)($data['coupon'] ?? '')));
        $discount = $coupon === 'FLAT10' ? round($subTotal * 0.10) : 0.0;

        $taxable  = max(0.0, $subTotal - $discount);
        $tax      = round($taxable * 0.05);
        $grand    = $taxable + $tax + (count($data['items']) ? (float)$data['shipping'] : 0.0);

        // 3) Resolve sponsor/upline and leg (for sell rows)
        $sponsorId = $this->resolveSponsorId($user);
        $leg       = $user->position ?: null; // 'L' or 'R'

        $orderNo = 'ORD-'.Str::upper(Str::random(8));

        // 4) Transaction: lock wallet, check balance, deduct, create sell rows
        DB::transaction(function () use ($user, $data, $grand, $sponsorId, $leg, $coupon, $tax, $orderNo) {

            // Lock the wallet row
            $walletRow = DB::table('wallet')
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            $current = $walletRow->amount ?? 0.0;
            if ($current < $grand) {
                throw ValidationException::withMessages([
                    'wallet' => 'Insufficient wallet balance.',
                ]);
            }

            // Deduct
            DB::table('wallet')
                ->where('user_id', $user->id)
                ->update([
                    'amount'     => DB::raw('amount - '.number_format($grand, 2, '.', '')),
                    'updated_at' => now(),
                ]);

            // Create sell rows
            foreach ($data['items'] as $it) {
                $type = null;
                if (!empty($it['type'])) {
                    $type = strtolower(trim($it['type']));
                } elseif (preg_match('/\(([^)]+)\)\s*$/', (string)$it['name'], $m)) {
                    $type = strtolower(trim($m[1])); // "Product (Silver)" -> "silver"
                }

                $lineAmount = round((float)$it['price'] * (int)$it['qty'], 2);

                Sell::create([
                    'buyer_id'          => $user->id,
                    'sponsor_id'        => $sponsorId,        // upline id
                    'income_to_user_id' => $sponsorId,        // same for direct income (if any)
                    'leg'               => $leg,
                    'product'           => $it['name'],
                    'amount'            => $lineAmount,
                    'income'            => 0.0,               // no commission at checkout time
                    'income_type'       => 'DIRECT',          // keep uppercase to avoid enum truncation
                    'level'             => null,
                    'order_no'          => $orderNo,
                    'status'            => 'paid',
                    'type'              => $type,             // silver/gold/diamond/repurchase
                    'details'           => [
                        'qty'      => (int)$it['qty'],
                        'price'    => (float)$it['price'],
                        'variant'  => $it['variant'] ?? null,
                        'coupon'   => $coupon ?: null,
                        'tax'      => $tax,
                        'shipping' => (float)$data['shipping'],
                        'type'     => $type,
                    ],
                ]);
            }
        });

        return back()->with('success', 'Order placed & wallet debited successfully!');
    }

    /** Find sponsor id from user.sponsor_id or refer_by (referral_id / code / email / numeric id) */
    private function resolveSponsorId(User $user): ?int
    {
        if ($user->sponsor_id) return (int)$user->sponsor_id;

        $ref = trim((string)($user->refer_by ?? ''));
        if ($ref === '') return null;

        $s = User::query()
            ->whereRaw('LOWER(referral_id) = ?', [strtolower($ref)])
            ->orWhereRaw('LOWER(referral_code) = ?', [strtolower($ref)])
            ->orWhere('email', $ref)
            ->when(ctype_digit($ref), fn($q) => $q->orWhere('id', (int)$ref))
            ->first();

        if ($s) {
            $user->sponsor_id = $s->id;
            $user->save();
            return (int)$s->id;
        }
        return null;
    }
}
