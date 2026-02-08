<?php

namespace App\Console\Commands;

use App\Services\InventoryLedgerService;
use Illuminate\Console\Command;

class SnapshotInventory extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'inventory:snapshot {date? : The date to snapshot (defaults to today)}';

    /**
     * The console command description.
     */
    protected $description = 'Create daily inventory snapshots for all products at all locations';

    /**
     * Execute the console command.
     */
    public function handle(InventoryLedgerService $ledgerService): int
    {
        $date = $this->argument('date') ?? now()->toDateString();

        $this->info("Creating inventory snapshots for {$date}...");

        try {
            $count = $ledgerService->createDailySnapshots($date);
            $this->info("Successfully created {$count} inventory snapshots for {$date}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to create snapshots: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
