<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/**
 * Build an array of extra providers (prod-safe).
 * - Only include Laravel\Pail\PailServiceProvider in local and if the class exists (installed).
 */
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
    // IMPORTANT: Must be an array (not a Closure) in Laravel 12
    ->withProviders($extraProviders)
    ->withSchedule(function (Schedule $schedule): void {
        // IST windows
        $schedule->command('binary:match 1')
            ->dailyAt('12:01')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping();

        $schedule->command('binary:match 2')
            ->dailyAt('23:59')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping();

        // For testing only:
        // $schedule->command('binary:match 1')->everyMinute()->timezone('Asia/Kolkata')->withoutOverlapping();
    })
    ->create();
