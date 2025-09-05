<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $tz = 'Asia/Kolkata';

        // Closing 1 over at ~12:00 → run 12:01 IST
        $schedule->command('binary:match 1')
            ->timezone($tz)
            ->dailyAt('12:01')
            ->appendOutputTo(storage_path('logs/binary_match_1.log'));

        // Closing 2 over at ~18:00 → run 18:01 IST
        $schedule->command('binary:match 2')
            ->timezone($tz)
            ->dailyAt('18:01')
            ->appendOutputTo(storage_path('logs/binary_match_2.log'));
    }

    protected function schedule(Schedule $schedule): void
{
    $schedule->command('star:compute')
        ->timezone('Asia/Kolkata')
        ->dailyAt('23:59')
        ->appendOutputTo(storage_path('logs/star_compute.log'));
}

protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule): void
{
    $tz = 'Asia/Kolkata';

    // On the 1st of every month → qualify previous month
    $schedule->command('repurchase:qualify')
        ->timezone($tz)
        ->monthlyOn(1, '00:10')
        ->appendOutputTo(storage_path('logs/repurchase_qualify.log'));

    // Pay current month installment (run daily; idempotent)
    $schedule->command('repurchase:pay')
        ->timezone($tz)
        ->dailyAt('00:20')
        ->appendOutputTo(storage_path('logs/repurchase_pay.log'));

        protected $routeMiddleware = [
    // ...
    'admin' => \App\Http\Middleware\AdminOnly::class,
];

}


}
