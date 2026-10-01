<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('agencyos:subscription-reminders')->dailyAt('08:00')->timezone(config('agencyos.timezone'))->withoutOverlapping();
Schedule::command('agencyos:seo-monitors')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
