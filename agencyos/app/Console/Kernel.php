<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('agencyos:subscription-reminders')->dailyAt('08:00')->timezone(config('agencyos.timezone'))->withoutOverlapping();
        $schedule->command('agencyos:seo-monitors')->everyMinute()->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
