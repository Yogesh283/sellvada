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

require __DIR__.'/auth.php';
