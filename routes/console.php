<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Take a daily end-of-day inventory snapshot at 11:59 PM
Schedule::command('inventory:snapshot')->dailyAt('23:45');

// Database backups — twice daily
Schedule::command('backup:run --only-db')->dailyAt('06:55')->name('backup-morning')->withoutOverlapping();
Schedule::command('backup:run --only-db')->dailyAt('23:50')->name('backup-night')->withoutOverlapping();
