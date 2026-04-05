<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Take a daily end-of-day inventory snapshot at 11:59 PM
Schedule::command('inventory:snapshot')->dailyAt('23:45');

// Log every time the scheduler runs — useful to verify cron is firing on the server
Schedule::call(function () {
    Log::info('Scheduler heartbeat', [
        'timestamp' => now()->toDateTimeString(),
        'server' => gethostname(),
    ]);
})->everyMinute();
