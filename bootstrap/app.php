<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Your web middleware
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Customize exception handling here if needed
    })
    // IMPORTANT: Only register dev providers (like Pail) in local.
    // This avoids "Laravel\Pail\PailServiceProvider not found" in production
    ->withProviders(function (): array {
        if (app()->isLocal()) {
            // Using ::class here is safe; it just returns a string in PHP.
            return [
                \Laravel\Pail\PailServiceProvider::class,
            ];
        }
        return [];
    })
    // Your schedule (server time is UTC; we set IST explicitly)
    ->withSchedule(function (Schedule $schedule): void {
        // Closing 1: run daily 12:01 IST
        $schedule->command('binary:match 1')
            ->dailyAt('12:01')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping();

        // Closing 2: run daily 23:59 IST
        $schedule->command('binary:match 2')
            ->dailyAt('23:59')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping();

        // For quick testing, you can temporarily enable:
        // $schedule->command('binary:match 1')->everyMinute()->timezone('Asia/Kolkata')->withoutOverlapping();
    })
    ->create();
