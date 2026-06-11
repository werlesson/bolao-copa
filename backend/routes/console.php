<?php

use App\Jobs\SendUpcomingMatchReminders;
use App\Jobs\SyncMatchResults;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SyncMatchResults)->everyMinute();
Schedule::job(new SendUpcomingMatchReminders)->hourly();
