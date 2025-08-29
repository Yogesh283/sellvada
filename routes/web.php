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

require __DIR__.'/auth.php';
