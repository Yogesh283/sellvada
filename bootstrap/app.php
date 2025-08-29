<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Dev-only providers (safe for production)
$extraProviders = [];
$isLocal = (($_ENV['APP_ENV'] ?? null) === 'local') || (function_exists('env') && env('APP_ENV') === 'local');
if ($isLocal && class_exists(\Laravel\Pail\PailServiceProvider::class)) {
    $extraProviders[] = \Laravel\Pail\PailServiceProvider::class;
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // customize if needed
    })
    ->withProviders($extraProviders) // must be an array
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('binary:match 1')->dailyAt('12:01')->timezone('Asia/Kolkata')->withoutOverlapping();
        $schedule->command('binary:match 2')->dailyAt('23:59')->timezone('Asia/Kolkata')->withoutOverlapping();
        // $schedule->command('binary:match 1')->everyMinute()->timezone('Asia/Kolkata')->withoutOverlapping(); // test only
    })
    ->create();
