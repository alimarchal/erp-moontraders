<?php

namespace App\Console\Commands\Stock;

use App\Services\BatchRecostService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecostBatchMovements extends Command
{
    /** Costs further apart than this are treated as a real defect rather than rounding. */
    private const COST_TOLERANCE = 0.01;

    protected $signature = 'stock:recost-batch-movements
        {--stock_batch_id= : Only repair this batch}
        {--dry-run : Report what would change without saving}';

    protected $description = 'Re-cost stock movements that carry a unit_cost different from their batch\'s '
        .'receipt (GRN) cost. These are left behind when a posted GRN is corrected after stock has already '
        .'been issued out of the batch: the correction restates the batch but not the documents already '
        .'posted from it.';

    public function handle(BatchRecostService $recostService): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $drifted = $this->driftedBatches();

        if ($drifted->isEmpty()) {
            $this->info('Every stock movement already matches its batch receipt cost — nothing to do.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('%d batch(es) carry movements priced off their receipt cost.', $drifted->count()));

        $rows = [];
        $totalCogsDelta = 0.0;
        $totalMovements = 0;

        foreach ($drifted as $batch) {
            $result = $recostService->recostBatch(
                (int) $batch->stock_batch_id,
                (float) $batch->receipt_cost,
                (int) $batch->grn_movement_id,
                $isDryRun
            );

            $totalCogsDelta += $result['cogs_delta'];
            $totalMovements += $result['movements'];

            $rows[] = [
                $batch->stock_batch_id,
                $batch->product_id,
                number_format((float) $batch->receipt_cost, 6),
                $result['movements'],
                $result['ledger_rows'],
                $result['issue_items'],
                $result['van_rows'],
                number_format($result['cogs_delta'], 2),
            ];
        }

        $this->table(
            ['Batch', 'Product', 'Receipt cost', 'Movements', 'Ledger rows', 'Issue items', 'Van rows', 'COGS delta'],
            $rows
        );

        if ($isDryRun) {
            $this->warn('DRY RUN — no data was changed. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Re-costed %d movement(s).', $totalMovements));
        $this->warn(sprintf(
            'Posted journal entries were NOT changed. COGS across these documents is out by %s and needs an '
            .'adjusting entry.',
            number_format($totalCogsDelta, 2)
        ));

        Log::info('Re-costed stock movements against batch receipt cost', [
            'batches' => $drifted->count(),
            'movements' => $totalMovements,
            'cogs_delta' => round($totalCogsDelta, 2),
        ]);

        return self::SUCCESS;
    }

    /**
     * Batches whose outbound movements disagree with the cost their own GRN recorded.
     *
     * @return Collection<int, object>
     */
    private function driftedBatches(): Collection
    {
        $receipts = DB::table('stock_movements')
            ->where('movement_type', 'grn')
            ->whereNotNull('stock_batch_id')
            ->groupBy('stock_batch_id')
            ->havingRaw('SUM(quantity) <> 0')
            ->select(
                'stock_batch_id',
                DB::raw('MIN(id) as grn_movement_id'),
                DB::raw('MIN(product_id) as product_id'),
                DB::raw('SUM(quantity * unit_cost) / SUM(quantity) as receipt_cost')
            );

        return DB::query()
            ->fromSub($receipts, 'receipts')
            ->when($this->option('stock_batch_id'), fn ($query, $id) => $query->where('receipts.stock_batch_id', $id))
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('stock_movements as sm')
                    ->whereColumn('sm.stock_batch_id', 'receipts.stock_batch_id')
                    ->where('sm.movement_type', '!=', 'grn')
                    ->whereRaw('ABS(sm.unit_cost - receipts.receipt_cost) > ?', [self::COST_TOLERANCE]);
            })
            ->orderBy('receipts.stock_batch_id')
            ->get();
    }
}
