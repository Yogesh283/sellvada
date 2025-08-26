<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\SellController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Carbon;   // 👈 add this



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




if (app()->environment('local')) {

    Route::get('/test-plan/{plan}/{sponsorId?}', function (string $plan, int $sponsorId = 100) {

        // ---------- Plan config ----------
        $PLAN = strtolower($plan);
        $configs = [
            'silver'  => [
                'types'       => ['silver'],
                'closing_cap' => 3000,
                'daily_cap'   => 6000,
            ],
            'gold'    => [
                'types'       => ['gold','silver'],
                'closing_cap' => 18000,
                'daily_cap'   => 36000,
            ],
            'diamond' => [
                'types'       => ['diamond','gold','silver'],
                'closing_cap' => 48000,
                'daily_cap'   => 96000,
            ],
        ];
        abort_if(!isset($configs[$PLAN]), 422, 'Invalid plan. Use silver | gold | diamond.');

        $cfg = $configs[$PLAN];

        // ---------- Date + Closings ----------
        $day = request('date')
            ? Carbon::parse(request('date'))->startOfDay()
            : Carbon::today();

        // Two closings: [00:00–11:59:59] and [12:00–23:59:59]
        $windows = [
            [$day->copy()->startOfDay(),    $day->copy()->setTime(11,59,59)],
            [$day->copy()->setTime(12,0,0), $day->copy()->endOfDay()],
        ];

        $outClosings = [];
        $dailyRaw = 0;

        foreach ($windows as $i => [$start, $end]) {
            // Eligible rows for this closing (plan-wise type filter)
            $rows = DB::table('sell')
                ->select('leg','amount','type','created_at')
                ->where('sponsor_id', $sponsorId)
                ->whereIn('type', $cfg['types'])  // MySQL CI collation ⇒ case-insensitive
                ->whereBetween('created_at', [$start, $end])
                ->get();

            // Sum volume per side (amount is string -> cast to float)
            $volL = $rows->where('leg','L')->sum(fn($r) => (float)$r->amount);
            $volR = $rows->where('leg','R')->sum(fn($r) => (float)$r->amount);

            $matched   = min($volL, $volR);                   // matched business volume
            $payoutRaw = $matched;                            // ₹1 per ₹1 volume (per your posters)
            $payout    = min($payoutRaw, $cfg['closing_cap']); // per-closing capping

            $dailyRaw += $payout;

            $outClosings[] = [
                'closing'      => $i+1,
                'window_start' => $start->toDateTimeString(),
                'window_end'   => $end->toDateTimeString(),
                'volume_left'  => $volL,
                'volume_right' => $volR,
                'matched'      => $matched,
                'closing_cap'  => $cfg['closing_cap'],
                'payout'       => $payout,
                'rows_count'   => $rows->count(),
            ];
        }

        return response()->json([
            'ok'                  => true,
            'plan'                => strtoupper($PLAN),
            'sponsor_id'          => $sponsorId,
            'date'                => $day->toDateString(),
            'config'              => [
                'eligible_types' => $cfg['types'],
                'closing_cap'    => $cfg['closing_cap'],
                'daily_cap'      => $cfg['daily_cap'],
            ],

            'closings'            => $outClosings,
            'daily_total_raw'     => $dailyRaw,
            'daily_total_payable' => min($dailyRaw, $cfg['daily_cap']),
            'sponcer_id' =>$sponsorId,
        ]);
    });
}


require __DIR__.'/auth.php';
