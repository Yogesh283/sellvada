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



require __DIR__.'/auth.php';
