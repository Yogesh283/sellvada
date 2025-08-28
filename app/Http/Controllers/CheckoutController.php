<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    private const DEFAULT_LEG      = 'L';        // fallback
    private const DEFAULT_SHIPPING = 49.0;       // default shipping
    private const INCOME_TYPE_VAL  = 'DIRECT';   // change to 0 if column is TINYINT

    public function store(Request $request)
    {
        // 1) Validate cart
        $data = $request->validate([
            'items'             => ['required','array','min:1'],
            'items.*.id'        => ['required'],
            'items.*.name'      => ['required','string','max:120'],
            'items.*.price'     => ['required','numeric','min:0'],
            'items.*.qty'       => ['required','integer','min:1'],
            'items.*.variant'   => ['nullable','string','max:120'],
            'items.*.type'      => ['nullable','string','max:32'],
            'coupon'            => ['nullable','string','max:32'],
            'shipping'          => ['nullable','numeric','min:0'],
        ]);

        $user = Auth::user();
        abort_unless($user, 401);

        // 2) Totals (wallet deduction ke liye)
        $subTotal = 0.0;
        foreach ($data['items'] as $it) {
            $subTotal += (float)$it['price'] * (int)$it['qty'];
        }
        $coupon   = strtoupper(trim((string)($data['coupon'] ?? '')));
        $discount = $coupon === 'FLAT10' ? round($subTotal * 0.10, 2) : 0.0;

        $taxable  = max(0, $subTotal - $discount);
        $tax      = round($taxable * 0.05, 2);
        $shipping = array_key_exists('shipping', $data) ? (float)$data['shipping'] : self::DEFAULT_SHIPPING;
        $grand    = round($taxable + $tax + $shipping, 2);

        // 3) Common fields
        $orderNo = 'ORD-'.Str::upper(Str::random(8));

        // 4) All-or-nothing
        DB::transaction(function () use ($user, $data, $orderNo, $coupon, $tax, $shipping, $grand) {
            // --- wallet lock + deduct ---
            $wallet = DB::table('wallet')->where('user_id', $user->id)->lockForUpdate()->first();
            if (!$wallet || (float)$wallet->amount < $grand) {
                throw new \RuntimeException('INSUFFICIENT_FUNDS');
            }
            DB::table('wallet')
                ->where('user_id', $user->id)
                ->update(['amount' => DB::raw('amount - '.number_format($grand, 2, '.', ''))]);

            // --- resolve upline (sponsor) & leg ---
            $uplineId = $this->resolveUplineId($user->id);
            $leg      = $this->resolveLegRelativeToUpline($user->id, $uplineId) ?? ($user->position ?: self::DEFAULT_LEG);

            // --- insert sell rows (exact table shape) ---
            foreach ($data['items'] as $it) {
                $qty        = (int)$it['qty'];
                $price      = (float)$it['price'];
                $lineAmount = round($price * $qty, 2); // sell.amount = product total only

                // type: explicit -> from "(Silver)" suffix -> null
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
                    'sponsor_id'        => $uplineId,                 // ✅ UPLINE
                    'income_to_user_id' => $uplineId,                 // ✅ DIRECT income to upline
                    'leg'               => $leg,                      // ✅ 'L' / 'R' relative to upline
                    'product'           => $it['name'],               // e.g. "Superfruit Mix (Silver)"
                    'amount'            => $lineAmount,               // e.g. 3000.00
                    'income'            => 0.00,
                    'income_type'       => self::INCOME_TYPE_VAL,     // 'DIRECT' or 0
                    'level'             => null,
                    'order_no'          => $orderNo,
                    'status'            => 'paid',
                    'details'           => json_encode($details, JSON_UNESCAPED_UNICODE),
                    'type'              => $type,                     // e.g. 'silver'
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }
        });

        return back()->with('success', 'Order placed successfully!');
    }

    // --------- Helpers ---------

    /** Upline/sponsor ko resolve kare: sponsor_id -> parent_id -> refer_by -> null */
    private function resolveUplineId(int $userId): ?int
    {
        $u = DB::table('users')
            ->select('id','sponsor_id','parent_id','refer_by')
            ->where('id', $userId)
            ->first();

        if (!$u) return null;
        if (!empty($u->sponsor_id)) return (int)$u->sponsor_id;
        if (!empty($u->parent_id))  return (int)$u->parent_id;

        $ref = trim((string)($u->refer_by ?? ''));
        if ($ref !== '') {
            $s = DB::table('users')
                ->whereRaw('LOWER(referral_id) = ?', [strtolower($ref)])
                ->orWhereRaw('LOWER(referral_code) = ?', [strtolower($ref)])
                ->orWhere('email', $ref)
                ->when(ctype_digit($ref), fn($q) => $q->orWhere('id', (int)$ref))
                ->value('id');
            return $s ? (int)$s : null;
        }
        return null;
    }

    /** Upline ke respect me buyer ka leg: 'L' agar upline.left_user_id == buyer_id, 'R' agar right, warna null */
    private function resolveLegRelativeToUpline(int $buyerId, ?int $uplineId): ?string
    {
        if (!$uplineId) return null;

        $upline = DB::table('users')
            ->select('left_user_id','right_user_id')
            ->where('id', $uplineId)
            ->first();

        if (!$upline) return null;

        if ((int)($upline->left_user_id ?? 0) === $buyerId)  return 'L';
        if ((int)($upline->right_user_id ?? 0) === $buyerId) return 'R';

        return null;
    }
}
