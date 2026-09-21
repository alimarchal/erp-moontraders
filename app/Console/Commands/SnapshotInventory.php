<?php

namespace App\Console\Commands;

use App\Services\InventoryLedgerService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SnapshotInventory extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'inventory:snapshot
        {date? : Date to label the snapshot with. Only today is accepted — see --force}
        {--force : Label the current position with a past date anyway}';

    /**
     * The console command description.
     */
    protected $description = 'Record the current warehouse stock position as the end-of-day snapshot for today. '
        .'This reads live stock, so it cannot reconstruct an earlier day — use inventory:snapshots:rebuild for that.';

    /**
     * Execute the console command.
     */
    public function handle(InventoryLedgerService $ledgerService): int
    {
        $today = now()->toDateString();
        $argument = $this->argument('date');

        if ($argument !== null && ! Carbon::hasFormat($argument, 'Y-m-d')) {
            $this->error("date must be a valid date in Y-m-d format, got \"{$argument}\".");

            return self::FAILURE;
        }

        $date = $argument === null ? $today : Carbon::createFromFormat('Y-m-d', $argument)->toDateString();

        // The snapshot is a photograph of live stock, so the date is only ever a label. Writing
        // today's position under an earlier date silently overwrites that day's real history
        // with numbers that include everything posted since.
        if ($date !== $today) {
            if (! $this->option('force')) {
                $this->error("Cannot snapshot {$date}: this command records stock as it is right now, and would store today's position under that date.");
                $this->line("To rebuild {$date} from the stock_movements ledger:");
                $this->line("  php artisan inventory:snapshots:rebuild {$date} {$date}");
                $this->line('Pass --force to label the current position with that date anyway.');

                return self::FAILURE;
            }

            $this->warn("--force: storing today's stock position under {$date}. This overwrites that date's history.");
        }

        $this->info("Creating inventory snapshots for {$date}...");

        try {
            $count = $ledgerService->createDailySnapshots($date);
            $this->info("Successfully created {$count} inventory snapshots for {$date}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to create snapshots: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
