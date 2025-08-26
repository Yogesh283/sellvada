<?php

use Illuminate\Console\Scheduling\Schedule;

return \Illuminate\Foundation\Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (\Illuminate\Foundation\Configuration\Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
        // add more middleware if needed...
    })
    ->withExceptions(function (\Illuminate\Foundation\Configuration\Exceptions $exceptions): void {
        // exception reporting config (optional)
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Schedule only once (avoid duplicates)
        $schedule->command('binary:match 1')
            ->dailyAt('12:01')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping();

        $schedule->command('binary:match 2')
            ->dailyAt('23:59')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping();

        // TEMP testing example (remove after test):
        // $schedule->command('binary:match 1')->everyMinute()->withoutOverlapping();
    })
    ->create();
