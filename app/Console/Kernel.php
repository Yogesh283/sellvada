<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // agar command signature: binary:match {which}
        $schedule->command('binary:match', ['1'])
            ->dailyAt('12:01')
            ->timezone('Asia/Kolkata')       // live server UTC ho sakta, isliye yahi set kar do
            ->withoutOverlapping()           // long run me overlap avoid
            ->appendOutputTo(storage_path('logs/schedule.log'));

        $schedule->command('binary:match', ['2'])
            ->dailyAt('23:59')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/schedule.log'));

        // Agar maintenance mode me bhi chalana ho:
        // ->evenInMaintenanceMode();
        // Single server only (multi-server cluster):
        // ->onOneServer();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
