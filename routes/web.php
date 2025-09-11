<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\SellController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Carbon;   // 👈 add this
use App\Http\Controllers\TeamController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\BuyController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use App\Http\Controllers\BinarySummaryController;
  use App\Http\Controllers\StarIncomeController;
  use App\Http\Controllers\VipRepurchaseSalaryController;
  use App\Http\Controllers\DepositController;
  use App\Http\Controllers\AddressController;
  use App\Http\Controllers\WithdrawalController;
  use App\Http\Controllers\TeamTreeController;
  use App\Http\Controllers\RepurchaseController;

use Filament\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\P2PTransferController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/orders', [SellController::class, 'index'])->name('orders.my');
});


Route::middleware(['web','auth'])->group(function () {
    Route::get('/p2p/transfer', [P2PTransferController::class, 'show'])->name('p2p.transfer.page');
    Route::post('/p2p/transfer', [P2PTransferController::class, 'transfer'])->name('p2p.transfer');
    Route::get('/p2p/balance', [P2PTransferController::class, 'balance'])->name('p2p.balance'); // fallback
});


Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/card', fn() => Inertia::render('Card'))->name('card');
});

// Route::middleware('auth')->group(function () {
//     Route::post('/checkout', [SellController::class, 'checkout'])->name('sell.checkout');
// });

Route::post('/checkout', [\App\Http\Controllers\CheckoutController::class, 'store'])->middleware('auth');



Route::middleware(['auth','verified'])->get('/team', [TeamController::class, 'index'])->name('team.index');

Route::middleware(['auth','verified'])->get('/cart', [CartController::class, 'show'])
     ->name('cart.show');

Route::middleware(['auth','verified'])->post('/checkout', [CheckoutController::class, 'store'])
     ->name('checkout.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
});

Route::middleware(['auth','verified'])->group(function () {
    Route::get('/payouts', [PayoutController::class, 'index'])->name('payouts.index');
});



Route::middleware(['auth','verified'])->group(function () {
    Route::get('/income', [IncomeController::class, 'index'])->name('income.index');
});

Route::middleware(['auth','verified'])->group(function () {
    Route::get('/buy', [ShopController::class, 'create'])->name('buy');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
});


Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');



// Route::middleware(['web', 'auth', 'verified'])
//     ->get('/income/binary', [BinarySummaryController::class, 'show'])
//     ->name('income.binary');


Route::middleware(['web','auth','verified'])
    ->get('/income/star', [StarIncomeController::class, 'show'])
    ->name('income.star');

    Route::middleware(['web','auth','verified'])
    ->get('/income/vip-repurchase-salary', [VipRepurchaseSalaryController::class, 'index'])
    ->name('income.vip-repurchase');

Route::middleware(['auth','verified'])->group(function () {
    Route::get('/wallet/deposit',  [DepositController::class, 'index'])->name('wallet.deposit');
    Route::post('/wallet/deposit', [DepositController::class, 'store']);
    // Route::post('/admin/deposits/{id}/approve', [DepositController::class, 'approve'])->middleware('can:approve-deposit');
});



Route::middleware(['auth','verified'])->group(function () {
    Route::get('/address', [AddressController::class, 'index'])->name('address.index');
    Route::post('/address', [AddressController::class, 'store'])->name('address.store');
    Route::put('/address/{address}', [AddressController::class, 'update'])->name('address.update');
    Route::delete('/address/{address}', [AddressController::class, 'destroy'])->name('address.delete');
    Route::post('/address/{address}/default', [AddressController::class, 'makeDefault'])->name('address.default');
});


Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/card', [CartController::class, 'show'])->name('cart.show');
    // Your existing checkout route should already exist:
    // Route::post('/checkout', [SellController::class, 'checkout'])->name('checkout');
});



Route::middleware(['auth','verified'])->group(function () {
    Route::get('/wallet/withdraw',  [WithdrawalController::class, 'index'])->name('wallet.withdraw');
    Route::post('/wallet/withdraw', [WithdrawalController::class, 'store'])->name('wallet.withdraw.store');
});


Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/income/binary', [BinarySummaryController::class, 'show'])
        ->name('income.binary');
});




Route::middleware(['auth','verified'])->group(function () {
    Route::get('/team/tree/{root?}', [TeamTreeController::class, 'show'])
        ->whereNumber('root')
        ->name('team.tree');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/repurchase', [RepurchaseController::class, 'index'])
        ->name('repurchase.index');

    Route::post('/repurchase/checkout', [RepurchaseController::class, 'repurchaseOrder'])
        ->name('repurchase.repurchaseOrder');
});





use App\Models\User; // 👈 Laravel 8+


Route::get('/payout-list', function () {
    // Amounts
    $AMT = ['silver' => 3000, 'gold' => 18000, 'diamond' => 4800];

    // Allowed sets based on self plan
    $ALLOWED = [
        'silver'  => ['silver'],
        'gold'    => ['silver','gold'],
        'diamond' => ['silver','gold','diamond'],
    ];

    // 1) All users with left/right ids
    $users = User::select('id','left_user_id','right_user_id')->get();

    // 2) Collect all ids for which we need highest plan (self + left + right)
    $relatedIds = $users->flatMap(fn($u) => [$u->id, $u->left_user_id, $u->right_user_id])
                        ->filter()->unique()->values();

    // 3) Highest plan per buyer (lifetime) from sell.buyer_id (paid only)
    $orderExpr = "CASE LOWER(type)
                    WHEN 'silver' THEN 1
                    WHEN 'gold' THEN 2
                    WHEN 'diamond' THEN 3
                    ELSE 0 END";

    $levels = DB::table('sell')
        ->select('buyer_id', DB::raw("MAX($orderExpr) AS lvl"))
        ->where('status','paid')
        ->whereIn(DB::raw('LOWER(type)'), ['silver','gold','diamond'])
        ->whereIn('buyer_id', $relatedIds)
        ->groupBy('buyer_id')
        ->pluck('lvl','buyer_id');  // [buyer_id => 1|2|3]

    // helpers
    $planOf = function ($id) use ($levels) {
        $lvl = (int)($levels[$id] ?? 0);
        return $lvl >= 3 ? 'diamond' : ($lvl == 2 ? 'gold' : ($lvl == 1 ? 'silver' : null));
    };
    $minAmount = function ($a, $b, $c) use ($AMT) {
        return min($AMT[$a] ?? PHP_FLOAT_MAX, $AMT[$b] ?? PHP_FLOAT_MAX, $AMT[$c] ?? PHP_FLOAT_MAX);
    };

    // 4) foreach users → rule apply → payout row (only 1 pair paid, smallest package)
    $out = [];

    foreach ($users as $u) {
        $selfPlan  = $planOf($u->id);
        $leftId    = $u->left_user_id;
        $rightId   = $u->right_user_id;
        $leftPlan  = $leftId  ? $planOf($leftId)  : null;
        $rightPlan = $rightId ? $planOf($rightId) : null;

        // qualify only if all three plans are known and left/right fit allowed set
        if (!$selfPlan || !$leftPlan || !$rightPlan) {
            continue;
        }

        $allowedSet = $ALLOWED[$selfPlan];

        if (in_array($leftPlan, $allowedSet, true) && in_array($rightPlan, $allowedSet, true)) {
            // ✅ pay only 1 pair, amount = smallest package among self/left/right
            $payout = $minAmount($selfPlan, $leftPlan, $rightPlan);

            $out[] = [
                'sponsor_id' => $u->id,
                'self_plan'  => $selfPlan,
                'left_id'    => $leftId,
                'left_plan'  => $leftPlan,
                'right_id'   => $rightId,
                'right_plan' => $rightPlan,
                'pairs_matched' => 1,          // up to 10 count ignore; pay only 1
                'payout'     => $payout,       // smallest package amount
                'note'       => 'paid for 1 pair at smallest package',
            ];
        }
    }

    return response()->json($out);
});




require __DIR__.'/auth.php';
