<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Take a daily end-of-day inventory snapshot at 11:59 PM
Schedule::command('inventory:snapshot')->dailyAt('23:45');

// The snapshot above records current state, so a document posted late but dated earlier
// (a goods issue settled days later) leaves every snapshot in between frozen at the value
// it held before that document existed. Replay the recent window from the stock_movements
// ledger right afterwards, so history heals itself instead of needing a manual rebuild.
// Vehicle rows are left out on purpose: pass --with-vans to backfill those deliberately.
Schedule::command('inventory:snapshots:rebuild --days=90')
    ->dailyAt('23:50')
    ->name('inventory-snapshots-rolling-rebuild')
    ->withoutOverlapping();

// Database backups — twice daily
// Schedule::command('backup:run --only-db')->dailyAt('06:00')->name('backup-morning')->withoutOverlapping();
Schedule::command('backup:run --only-db')->dailyAt('23:40')->name('backup-night')->withoutOverlapping();
