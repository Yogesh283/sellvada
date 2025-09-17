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

// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // हर Monday सुबह 1:00 AM → qualification run
    $schedule->command('repurchase:qualify --require-self=1')
             ->mondays()
             ->at('01:00')
             ->withoutOverlapping();

    // हर Monday सुबह 2:00 AM → payout run (closing पिछले Sunday तक)
    $schedule->command('repurchase:pay --date=' . now()->format('Y-m-d'))
             ->mondays()
             ->at('02:00')
             ->withoutOverlapping();
}



}
