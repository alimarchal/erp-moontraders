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
// Vehicle rows are included: nothing else writes them, so leaving them out here would stop
// van history the day after the one-off backfill. --include-backdated=3 stretches the window
// past 90 days whenever something posted in the last 3 days (covering a missed night) is dated
// earlier than that.
Schedule::command('inventory:snapshots:rebuild --days=90 --with-vans --include-backdated=3')
    ->dailyAt('23:50')
    ->name('inventory-snapshots-rolling-rebuild')
    ->withoutOverlapping();

// The rebuild above replays the ledger; this checks that the four places warehouse stock is
// recorded still hold the same number (ledger, current_stock_by_batch, stock_valuation_layers,
// current_stock). Deliberately without --fix: a silent auto-repair would hide whichever posting
// path caused the drift. The alert email says which command to run.
Schedule::command('inventory:verify-consistency')
    ->dailyAt('23:55')
    ->name('inventory-consistency-check')
    ->withoutOverlapping();

// The general ledger's Stock In Hand and Van Stock against the value of the stock itself.
// Report only: a gap means some document posted at a different value than it moved stock.
Schedule::command('accounting:reconcile-stock-gl')
    ->dailyAt('23:58')
    ->name('stock-ledger-reconciliation')
    ->withoutOverlapping();

// Database backups — twice daily
// Schedule::command('backup:run --only-db')->dailyAt('06:00')->name('backup-morning')->withoutOverlapping();
Schedule::command('backup:run --only-db')->dailyAt('23:40')->name('backup-night')->withoutOverlapping();
