<?php

namespace App\Http\Controllers;

use App\Models\Sell;
use App\Models\User;
use Illuminate\Http\Request;
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
        $tax      = round($taxable * 0.0, 2);
        $extra    = round($taxable * 0.0, 2);
        $shipping = count($data['items']) ? (float)$data['shipping'] : 0.0;
        $grand    = round($taxable + $tax + $extra + $shipping, 2);

        // 3) Resolve sponsor/upline and leg (for sell rows)
        $sponsorId = $this->resolveSponsorId($user);
        $leg       = $user->position ?: null; // 'L' or 'R'

        $orderNo = 'ORD-'.Str::upper(Str::random(8));

        try {
            DB::beginTransaction();

            // Ensure wallet row exists
            $walletRow = DB::table('wallet')
                ->where('user_id', $user->id)
                ->where('type', 'main')
                ->first();

            if (!$walletRow) {
                // create wallet row with zero balance to avoid null access
                DB::table('wallet')->insert([
                    'user_id'    => $user->id,
                    'type'       => 'main',
                    'amount'     => 0.00,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $current = 0.0;
            } else {
                $current = (float)$walletRow->amount;
            }

            // quick check
            if ($current < $grand) {
                DB::rollBack();
                throw ValidationException::withMessages(['wallet' => 'Insufficient wallet balance.']);
            }

            // Atomic decrement to deduct amount (avoid race conditions)
            $updated = DB::table('wallet')
                ->where('user_id', $user->id)
                ->where('type', 'main')
                ->where('amount', '>=', $grand)
                ->decrement('amount', $grand);

            if (!$updated) {
                // someone else may have spent concurrently; return proper validation message
                DB::rollBack();
                $balance = (float)(DB::table('wallet')->where('user_id', $user->id)->where('type','main')->value('amount') ?? 0);
                throw ValidationException::withMessages(['wallet' => 'Insufficient wallet balance. Available ₹'.number_format($balance,2)]);
            }

            // Create sell rows (one row per item)
            foreach ($data['items'] as $it) {
                $type = null;
                if (!empty($it['type'])) {
                    $type = strtolower(trim($it['type']));
                } elseif (preg_match('/\(([^)]+)\)\s*$/', (string)$it['name'], $m)) {
                    $type = strtolower(trim($m[1]));
                }

                $lineAmount = round((float)$it['price'] * (int)$it['qty'], 2);

                // IMPORTANT: ensure Sell::$fillable includes these fields and
                // Sell has `protected $casts = ['details' => 'array'];`
                Sell::create([
                    'buyer_id'          => $user->id,
                    'sponsor_id'        => $sponsorId,
                    'income_to_user_id' => $sponsorId,
                    'leg'               => $leg,
                    'product'           => $it['name'],
                    'amount'            => $lineAmount,
                    'income'            => 0.0,
                    'income_type'       => 'DIRECT',
                    'level'             => null,
                    'order_no'          => $orderNo,
                    'status'            => 'paid',
                    'type'              => $type,
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

            DB::commit();

            if ($request->header('X-Inertia')) {
                return redirect()->back()->with('success', 'Order placed & wallet debited successfully!');
            }

            return response()->json([
                'success'  => true,
                'order_no' => $orderNo,
                'message'  => 'Order placed & wallet debited successfully!',
                'grand'    => $grand,
            ], 200);
        } catch (ValidationException $ve) {
            // let laravel handle 422 response for Inertia/Ajax
            throw $ve;
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Checkout error: '.$e->getMessage(), [
                'user_id'   => $user->id ?? null,
                'order_no'  => $orderNo ?? null,
                'exception' => $e,
            ]);

            $msg = config('app.debug') ? $e->getMessage() : 'Server error while processing checkout. Please try again later.';

            if ($request->header('X-Inertia')) {
                return redirect()->back()->withErrors(['server' => $msg]);
            }

            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 500);
        }
    }

    /**
     * Resolve sponsor ID for a user from various fallbacks and persist if found.
     */
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
