<?php

namespace App\Console\Commands\Accounting;

use App\Models\RevenueDetail;
use App\Services\RevenueDetailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PostRevenueJournals extends Command
{
    protected $signature = 'revenue:post-journals {--dry-run : List what would be posted without changing anything}';

    protected $description = 'Create the missing GL journal (DR 1112 Pending Claims Debtors, CR 4260 Other Revenue) for revenue entries that were posted before revenue posting reached the ledger';

    public function handle(RevenueDetailService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $entries = RevenueDetail::query()
            ->with(['revenueCategory', 'supplier'])
            ->whereNotNull('posted_at')
            ->whereNull('journal_entry_id')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        if ($entries->isEmpty()) {
            $this->info('Every posted revenue entry already has its journal — nothing to do.');

            return self::SUCCESS;
        }

        $rows = [];
        $failed = 0;

        foreach ($entries as $entry) {
            $result = 'would post';

            if (! $dryRun) {
                try {
                    DB::transaction(function () use ($service, $entry) {
                        $journal = $service->createJournalEntry($entry);
                        $entry->update(['journal_entry_id' => $journal->id]);
                    });
                    $result = 'posted';
                } catch (\Throwable $e) {
                    $failed++;
                    $result = 'FAILED: '.$e->getMessage();
                }
            }

            $rows[] = [
                $entry->id,
                $entry->transaction_date->toDateString(),
                $entry->supplier?->supplier_name,
                $entry->revenueCategory?->name,
                number_format((float) $entry->amount, 2),
                $result,
            ];
        }

        $this->table(['ID', 'Date', 'Supplier', 'Category', 'Amount', 'Result'], $rows);
        $this->line(match (true) {
            $dryRun => 'DRY RUN — nothing was changed. Re-run without --dry-run to post.',
            $failed > 0 => "{$failed} entries failed — fix the cause above and run again.",
            default => 'Total '.number_format((float) $entries->sum('amount'), 2).' posted to 4260 Other Revenue.',
        });

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
