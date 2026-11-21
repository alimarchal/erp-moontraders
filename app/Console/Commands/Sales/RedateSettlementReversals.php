<?php

namespace App\Console\Commands\Sales;

use App\Models\AccountingPeriod;
use App\Services\DatabaseTriggerGuard;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RedateSettlementReversals extends Command
{
    /**
     * Posted journal entries are immutable, which is the accounting control. The guard is
     * lifted only for the moment the date is rewritten, and always put back.
     */
    private const JOURNAL_GUARD = 'trg_block_posted_journal_updates';

    /**
     * Rows written by one revert are stamped within a few seconds of its reversing journal entry.
     */
    private const REVERT_WINDOW_SECONDS = 5;

    protected $signature = 'sales-settlement:redate-reversals
        {settlement? : Settlement id or number. Omit to check every reverted settlement}
        {--dry-run : Report what would change without saving}';

    protected $description = 'Move the reversals of a reverted sales settlement onto the date of the posting they undo. '
        .'Reverts used to book their reversals on the day of the revert, so the settlement date carried both the '
        .'reverted and the re-posted settlement (credit, recovery and sales twice) until the revert day.';

    public function handle(): int
    {
        $reversals = $this->reversals();

        if ($reversals->isEmpty()) {
            $this->info('No settlement reversal is booked on a different date from the posting it undoes — nothing to do.');

            return self::SUCCESS;
        }

        if (! $this->option('dry-run')) {
            try {
                app(DatabaseTriggerGuard::class)->assertTriggersCanBeCreated('journal_entries');
            } catch (RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        $moved = 0;

        foreach ($reversals as $reversal) {
            $from = substr((string) $reversal->entry_date, 0, 10);
            $to = substr((string) $reversal->original_date, 0, 10);
            $label = "{$reversal->settlement_number}: reversal entry {$reversal->id} {$from} → {$to}";

            if (AccountingPeriod::closedCovering($from) || AccountingPeriod::closedCovering($to)) {
                $this->warn("{$label} skipped: a closed accounting period covers one of the dates.");

                continue;
            }

            $rows = $this->rowsOfRevert($reversal, $from);

            $this->info($label);
            $this->table(['Table', 'Rows to move'], [
                ['journal_entries', 1],
                ['customer_employee_account_transactions', count($rows['customer'])],
                ['inventory_ledger_entries', count($rows['inventory'])],
                ['stock_movements', count($rows['movements'])],
                ['stock_ledger_entries', count($rows['stock_ledger'])],
            ]);

            if ($this->option('dry-run')) {
                continue;
            }

            DB::transaction(function () use ($rows, $to): void {
                DB::table('customer_employee_account_transactions')->whereIn('id', $rows['customer'])->update(['transaction_date' => $to]);
                DB::table('inventory_ledger_entries')->whereIn('id', $rows['inventory'])->update(['date' => $to]);
                DB::table('stock_movements')->whereIn('id', $rows['movements'])->update(['movement_date' => $to]);
                DB::table('stock_ledger_entries')->whereIn('id', $rows['stock_ledger'])->update(['entry_date' => $to]);
            });

            $this->moveJournalEntry((int) $reversal->id, $to);

            Log::info('Re-dated a sales settlement reversal', [
                'settlement' => $reversal->settlement_number,
                'journal_entry' => $reversal->id,
                'from' => $from,
                'to' => $to,
            ]);

            $moved++;
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN — no data was changed. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        $this->info("Done: {$moved} reversal(s) moved.");

        if ($moved > 0) {
            $this->newLine();
            $this->line('Rebuild the affected snapshots next, e.g.:');
            $this->line('  php artisan inventory:snapshots:rebuild <earliest date> --with-vans --dry-run');
        }

        return self::SUCCESS;
    }

    /**
     * Reversing journal entries of settlements that are dated differently from the entry they reverse.
     *
     * @return Collection<int, object{id: int, entry_date: string, created_at: string, original_date: string, settlement_id: int, settlement_number: string}>
     */
    private function reversals(): Collection
    {
        $key = $this->argument('settlement');

        return DB::table('journal_entries as reversal')
            ->join('journal_entries as original', 'original.id', '=', 'reversal.reverses_entry_id')
            ->join('sales_settlements as ss', 'ss.settlement_number', '=', 'original.reference')
            ->where('reversal.status', 'posted')
            ->whereColumn('reversal.entry_date', '!=', 'original.entry_date')
            ->when($key !== null, fn ($query) => is_numeric($key)
                ? $query->where('ss.id', $key)
                : $query->where('ss.settlement_number', $key))
            ->orderBy('reversal.id')
            ->get([
                'reversal.id', 'reversal.entry_date', 'reversal.created_at',
                'original.entry_date as original_date', 'ss.id as settlement_id', 'ss.settlement_number',
            ]);
    }

    /**
     * The sub-ledger rows the same revert wrote: stamped with the reversal's date, by the same
     * settlement, within seconds of the reversing journal entry and before the settlement was
     * posted again.
     *
     * @return array{customer: list<int>, inventory: list<int>, movements: list<int>, stock_ledger: list<int>}
     */
    private function rowsOfRevert(object $reversal, string $reversalDate): array
    {
        $revertedAt = Carbon::parse($reversal->created_at);
        $windowStart = $revertedAt->copy()->subSeconds(self::REVERT_WINDOW_SECONDS);
        $windowEnd = $revertedAt->copy()->addSeconds(self::REVERT_WINDOW_SECONDS);

        $nextPostingAt = DB::table('journal_entries')
            ->where('reference', $reversal->settlement_number)
            ->where('id', '>', $reversal->id)
            ->orderBy('id')
            ->value('created_at');

        if ($nextPostingAt !== null && Carbon::parse($nextPostingAt)->lt($windowEnd)) {
            $windowEnd = Carbon::parse($nextPostingAt)->subSecond();
        }

        $window = [$windowStart, $windowEnd];

        $movementIds = DB::table('stock_movements')
            ->where('reference_type', 'App\\Models\\SalesSettlement')
            ->where('reference_id', $reversal->settlement_id)
            ->whereDate('movement_date', $reversalDate)
            ->whereBetween('created_at', $window)
            ->pluck('id')
            ->all();

        return [
            'customer' => DB::table('customer_employee_account_transactions')
                ->where('sales_settlement_id', $reversal->settlement_id)
                ->whereNotNull('reverses_transaction_id')
                ->whereDate('transaction_date', $reversalDate)
                ->whereBetween('created_at', $window)
                ->pluck('id')
                ->all(),
            'inventory' => DB::table('inventory_ledger_entries')
                ->where('sales_settlement_id', $reversal->settlement_id)
                ->where('transaction_type', 'adjustment')
                ->whereDate('date', $reversalDate)
                ->whereBetween('created_at', $window)
                ->pluck('id')
                ->all(),
            'movements' => $movementIds,
            'stock_ledger' => DB::table('stock_ledger_entries')->whereIn('stock_movement_id', $movementIds)->pluck('id')->all(),
        ];
    }

    /**
     * The entry's accounting period is left to trg_auto_set_accounting_period_update, which
     * re-resolves it from the new date and refuses a date no open period covers.
     */
    private function moveJournalEntry(int $journalEntryId, string $newDate): void
    {
        $restore = app(DatabaseTriggerGuard::class)->suspend('journal_entries', [self::JOURNAL_GUARD]);

        try {
            DB::table('journal_entries')->where('id', $journalEntryId)->update(['entry_date' => $newDate]);
            $this->line("  journal entry {$journalEntryId} moved to {$newDate}");
        } finally {
            $restore();
        }
    }
}
