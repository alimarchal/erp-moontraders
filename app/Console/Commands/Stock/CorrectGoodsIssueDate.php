<?php

namespace App\Console\Commands\Stock;

use App\Models\GoodsIssue;
use App\Services\DatabaseTriggerGuard;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CorrectGoodsIssueDate extends Command
{
    /**
     * Posted journal entries are immutable, which is the accounting control. The guard is
     * lifted only for the moment the date is rewritten, and always put back.
     */
    private const JOURNAL_GUARD = 'trg_block_posted_journal_updates';

    protected $signature = 'goods-issue:correct-date
        {goods_issue : Goods issue id or issue number}
        {date? : Correct date (Y-m-d). Defaults to the date of its sales settlement}
        {--dry-run : Report what would change without saving}';

    protected $description = 'Move a posted goods issue, and everything posted from it, onto its correct date. '
        .'A mistyped issue date leaves the warehouse overstated and the van negative until that date arrives, '
        .'because the stock is sold before the ledger says it left the warehouse.';

    public function handle(): int
    {
        $goodsIssue = $this->resolveGoodsIssue();

        if (! $goodsIssue) {
            return self::FAILURE;
        }

        $settlement = DB::table('sales_settlements')->where('goods_issue_id', $goodsIssue->id)->first();
        $newDate = $this->resolveDate($settlement);

        if ($newDate === null) {
            return self::FAILURE;
        }

        $currentDate = Carbon::parse($goodsIssue->issue_date)->toDateString();

        if ($newDate === $currentDate) {
            $this->info("{$goodsIssue->issue_number} is already dated {$newDate} — nothing to do.");

            return self::SUCCESS;
        }

        if ($settlement && $newDate > Carbon::parse($settlement->settlement_date)->toDateString()) {
            $this->error("Refusing: {$newDate} is after the settlement date "
                .Carbon::parse($settlement->settlement_date)->toDateString()
                .'. Stock cannot leave the warehouse after it was sold.');

            return self::FAILURE;
        }

        $journalEntry = DB::table('journal_entries')
            ->where('reference', $goodsIssue->issue_number)
            ->where('status', 'posted')
            ->first();

        // Probed before anything is written: the stock tables are updated first, and a
        // privilege failure later would leave them moved while the journal entry stayed put.
        if ($journalEntry && ! $this->option('dry-run')) {
            try {
                app(DatabaseTriggerGuard::class)->assertTriggersCanBeCreated('journal_entries');
            } catch (RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        $targets = $this->targets($goodsIssue, $journalEntry);

        $this->info("{$goodsIssue->issue_number}: {$currentDate} → {$newDate}"
            .($settlement ? "  (settlement {$settlement->settlement_number} is dated "
                .Carbon::parse($settlement->settlement_date)->toDateString().')' : ''));

        $this->table(['Table', 'Rows to move'], collect($targets)->map(fn ($count, $table) => [$table, $count])->values()->all());

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN — no data was changed. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        $movementIds = $this->movementIds($goodsIssue);

        DB::transaction(function () use ($goodsIssue, $newDate, $movementIds): void {
            DB::table('goods_issues')->where('id', $goodsIssue->id)->update(['issue_date' => $newDate]);
            DB::table('stock_movements')->whereIn('id', $movementIds)->update(['movement_date' => $newDate]);
            DB::table('stock_ledger_entries')->whereIn('stock_movement_id', $movementIds)->update(['entry_date' => $newDate]);
            DB::table('inventory_ledger_entries')->where('goods_issue_id', $goodsIssue->id)->update(['date' => $newDate]);
        });

        if ($journalEntry) {
            $this->moveJournalEntry((int) $journalEntry->id, $newDate);
        }

        $this->info('Done.');
        Log::info('Corrected a goods issue date', [
            'goods_issue' => $goodsIssue->issue_number,
            'from' => $currentDate,
            'to' => $newDate,
            'journal_entry' => $journalEntry->id ?? null,
        ]);

        $this->newLine();
        $this->line('Rebuild the affected snapshots next, e.g.:');
        $this->line("  php artisan inventory:snapshots:rebuild {$newDate} --supplier_id={$goodsIssue->supplier_id} --with-vans --dry-run");

        return self::SUCCESS;
    }

    private function resolveGoodsIssue(): ?object
    {
        $key = $this->argument('goods_issue');

        $goodsIssue = GoodsIssue::query()
            ->when(is_numeric($key), fn ($query) => $query->where('id', $key), fn ($query) => $query->where('issue_number', $key))
            ->first();

        if (! $goodsIssue) {
            $this->error("Goods issue \"{$key}\" not found.");

            return null;
        }

        return $goodsIssue;
    }

    private function resolveDate(?object $settlement): ?string
    {
        $given = $this->argument('date');

        if ($given === null) {
            if (! $settlement) {
                $this->error('No settlement for this goods issue, so there is no date to fall back on. Pass one.');

                return null;
            }

            return Carbon::parse($settlement->settlement_date)->toDateString();
        }

        if (! Carbon::hasFormat($given, 'Y-m-d')) {
            $this->error("date must be a valid date in Y-m-d format, got \"{$given}\".");

            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $given)->toDateString();
    }

    /**
     * @return array<string, int>
     */
    private function targets(object $goodsIssue, ?object $journalEntry): array
    {
        $movementIds = $this->movementIds($goodsIssue);

        return [
            'goods_issues' => 1,
            'stock_movements' => count($movementIds),
            'stock_ledger_entries' => DB::table('stock_ledger_entries')->whereIn('stock_movement_id', $movementIds)->count(),
            'inventory_ledger_entries' => DB::table('inventory_ledger_entries')->where('goods_issue_id', $goodsIssue->id)->count(),
            'journal_entries' => $journalEntry ? 1 : 0,
        ];
    }

    /**
     * @return list<int>
     */
    private function movementIds(object $goodsIssue): array
    {
        return DB::table('stock_movements')
            ->where('reference_type', GoodsIssue::class)
            ->where('reference_id', $goodsIssue->id)
            ->pluck('id')
            ->all();
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
