<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RepurchaseController extends Controller
{
    // ✅ Repurchase page
    public function index()
    {
        $userId = (int) Auth::id();
        abort_unless($userId, 401);

        // Product config (single product)
      $product = [
        'id'             => 1,
        'name'           => 'Super Food',
        'img'            => '/image/1.png',
        'type'           => 'Silver',
        'variant'        => '1 Bottle (30 Gummies)',
        'bottles'        => 1,
        'gummiesPerBottle' => 30,
        'unitPrice'      => 2000,   // per bottle MRP
        'mrp'            => 2000,   // maximum retail price
        'price'          => 1000,   // discounted price (actual selling)
        'baseTotal'      => 2000,   // total MRP
        'discount'       => 1000,    // savings
        'discountPercent'=> 50,
        'totalGummies'   => 30,
    ];

        // Wallet balance
        $walletBalance = (float) DB::table('wallet')
            ->where('user_id', $userId)
            ->value('amount') ?? 0;

        // Default address
        $defaultAddress = DB::table('address')
            ->where('user_id', $userId)
            ->where('is_default', 1)
            ->first();

        return inertia('Repurchase', [
            'product'        => $product,
            'walletBalance'  => $walletBalance,
            'defaultAddress' => $defaultAddress,
        ]);
    }

    // ✅ Checkout order
   // ✅ Checkout order
public function checkout(Request $request)
{
    $userId = (int) Auth::id();
    abort_unless($userId, 401);

    // Items from frontend
    $items = (array) $request->input('items', []);
    if (empty($items)) {
        return back()->withErrors(['cart' => 'Cart is empty.']);
    }

    // Server-side catalog
    $catalog = [
        1 => ['name' => 'Super Food', 'price' => 1000, 'type' => 'repurchase', 'variant' => '1 bottles' ],
    ];

    $lines = [];
    $subTotal = 0;
    foreach ($items as $it) {
        $pid  = (int) ($it['id'] ?? 0);
        $qty  = max(1, min(99, (int) ($it['qty'] ?? 1)));
        $type = strtolower((string) ($it['type'] ?? ''));

        if ($type !== 'repurchase' || !isset($catalog[$pid])) {
            return back()->withErrors(['cart' => 'Invalid cart items.']);
        }

        $unit = (float) $catalog[$pid]['price'];
        $line = $unit * $qty;

        $lines[] = [
            'product_id' => $pid,
            'product'    => $catalog[$pid]['name'],
            'qty'        => $qty,
            'unit'       => $unit,
            'line'       => $line,
        ];

        $subTotal += $line;
    }

    // Coupon
    $coupon   = strtoupper((string) $request->input('coupon', ''));
    $discount = ($coupon === 'FLAT10') ? round($subTotal * 0.10) : 0;
    $grand    = max(0, $subTotal - $discount);

    // Address check
    $hasAddress = DB::table('address')->where('user_id', $userId)->exists();
    if (!$hasAddress) {
        return back()->withErrors(['address' => 'Please add a shipping address.']);
    }

    // Wallet check
    $wallet = (float) DB::table('wallet')->where('user_id', $userId)->value('amount') ?? 0.0;
    if ($wallet < $grand) {
        return back()->withErrors(['wallet' => 'Insufficient wallet balance.']);
    }

    // ✅ Sponsor ID निकालो (users table से)
    $sponsorId = DB::table('users')->where('id', $userId)->value('sponsor_id');

    // ✅ Transaction
    DB::beginTransaction();
    try {
        // Insert repurchase rows
        foreach ($lines as $L) {
            DB::table('repurchase')->insert([
                'buyer_id'   => $userId,
                'sponsor_id' => $sponsorId,   // ✅ अब sponsor_id भी जाएगा
                'product'    => $L['product'],
                'qty'        => $L['qty'],
                'amount'     => number_format($L['line'], 2, '.', ''),
                'status'     => 'paid',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Lock wallet row
        $walletRow = DB::table('wallet')
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if (!$walletRow || $walletRow->amount < $grand) {
            throw new \RuntimeException('Insufficient wallet balance during checkout.');
        }

        // Deduct wallet
        DB::table('wallet')
            ->where('user_id', $userId)
            ->update([
                'amount'     => DB::raw("amount - {$grand}"),
                'updated_at' => now(),
            ]);

        // Insert into payout ledger
        DB::table('_payout')->insert([
            'user_id'      => $userId,
            'to_user_id'   => $sponsorId,
            'from_user_id' => $userId,
            'amount'       => $grand,
            'status'       => 'paid',
            'method'       => 'wallet',
            'type'         => 'repurchase_payment',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        DB::commit();
    } catch (\Throwable $e) {
        DB::rollBack();
        \Log::error("Repurchase Checkout Error: " . $e->getMessage());
        return back()->withErrors(['order' => 'Checkout failed: '.$e->getMessage()]);
    }

    return back()->with('success', 'Repurchase order placed!');
}
}
