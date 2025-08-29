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
  use App\Http\Controllers\Admin\WalletDepositAdminController;









/* ---------- CRON TEST ROUTES ---------- */
Route::get('/_cron/binary/{closing}', function ($closing, \Illuminate\Http\Request $r) {
    abort_unless(hash_equals(env('CRON_TOKEN',''), (string) $r->query('token')), 403);
    $args = ['closing' => (int) $closing];
    if ($r->query('date')) { $args['--date'] = $r->query('date'); }

    $code = \Illuminate\Support\Facades\Artisan::call('binary:match', $args);

    return response()->json([
        'ok'   => $code === 0,
        'cmd'  => 'binary:match',
        'args' => $args,
        'out'  => \Illuminate\Support\Facades\Artisan::output(),
    ]);
})->middleware('throttle:6,1');

Route::get('/_cron/star', function (\Illuminate\Http\Request $r) {
    abort_unless(hash_equals(env('CRON_TOKEN',''), (string) $r->query('token')), 403);

    $args = [];
    if ($r->query('date')) { $args['--date'] = $r->query('date'); }
    if ($r->boolean('dry')) { $args['--dry'] = true; }

    $code = \Illuminate\Support\Facades\Artisan::call('star:compute', $args);

    return response()->json([
        'ok'   => $code === 0,
        'cmd'  => 'star:compute',
        'args' => $args,
        'out'  => \Illuminate\Support\Facades\Artisan::output(),
    ]);
})->middleware('throttle:6,1');
/* ---------- /CRON TEST ROUTES ---------- */






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

Route::middleware('auth')->group(function () {
    Route::post('/checkout', [SellController::class, 'checkout'])->name('sell.checkout');
});


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



Route::middleware(['web', 'auth', 'verified'])
    ->get('/income/binary', [BinarySummaryController::class, 'show'])
    ->name('income.binary');


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


Route::middleware(['auth','verified','can:admin'])
    ->prefix('admin/wallet/deposits')->name('admin.wallet.deposits.')
    ->group(function () {
        Route::get('/',             [WalletDepositAdminController::class,'index'])->name('index');

        // Buttons se POST:
        Route::post('{id}/approve', [WalletDepositAdminController::class,'approve'])->name('approve');
        Route::post('{id}/reject',  [WalletDepositAdminController::class,'reject'])->name('reject');

        // ✅ OPTIONAL: Quick **approve link** (GET) for admin – use carefully
        Route::get('{id}/approve-link', [WalletDepositAdminController::class,'approve'])
            ->name('approve.link');
    });

  ////////////////

  // routes/web.php — ONLY APPROVE LINK

\Illuminate\Support\Facades\Route::get('/_admin/deposits/{id}/approve', function (\Illuminate\Http\Request $r, int $id) {
    \abort_unless(\hash_equals(\env('ADMIN_APPROVE_TOKEN',''), (string)$r->query('token')), 403);

    return \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
        $row = \Illuminate\Support\Facades\DB::table('wallet_deposits')->lockForUpdate()->find($id);
        if (! $row) \abort(404, 'Deposit not found');

        if ($row->status === 'rejected') \abort(400, 'Already rejected');
        if ($row->status === 'approved') {
            return \response()->json(['ok' => true, 'message' => 'Already approved']);
        }

        // 1) mark approved
        \Illuminate\Support\Facades\DB::table('wallet_deposits')->where('id', $id)->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'updated_at'  => now(),
        ]);

        // 2) credit wallet (type='main')
        $inc = \number_format((float)$row->amount, 2, '.', '');
        $affected = \Illuminate\Support\Facades\DB::update(
            "UPDATE wallet SET amount = amount + ? WHERE user_id = ? AND type = 'main'",
            [$inc, $row->user_id]
        );
        if ($affected === 0) {
            \Illuminate\Support\Facades\DB::table('wallet')->insert([
                'user_id'    => $row->user_id,
                'amount'     => $inc,
                'type'       => 'main',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // (optional) ledgers if tables exist
        if (\Illuminate\Support\Facades\Schema::hasTable('_payout')) {
            \Illuminate\Support\Facades\DB::table('_payout')->insert([
                'user_id'      => $row->user_id,
                'to_user_id'   => $row->user_id,
                'from_user_id' => null,
                'amount'       => $inc,
                'status'       => 'paid',
                'method'       => 'wallet_deposit',
                'type'         => 'wallet_deposit',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('wallet_ledger')) {
            \Illuminate\Support\Facades\DB::table('wallet_ledger')->insert([
                'user_id'    => $row->user_id,
                'type'       => 'credit',
                'source'     => 'deposit',
                'amount'     => $inc,
                'ref_id'     => $row->id,
                'meta'       => \json_encode(['method' => $row->method, 'reference' => $row->reference]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return \response()->json([
            'ok'      => true,
            'message' => 'Deposit approved & wallet credited',
            'deposit' => (int)$id,
            'user_id' => (int)$row->user_id,
            'amount'  => $inc,
        ]);
    });
})->middleware('throttle:10,1');

require __DIR__.'/auth.php';
