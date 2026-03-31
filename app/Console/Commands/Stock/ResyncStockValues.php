<?php

namespace App\Console\Commands\Stock;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Signature('stock:resync-values {--dry-run : Preview changes without saving}')]
#[Description('Re-syncs current_stock_by_batch.total_value from authoritative GRN item total_cost to eliminate float precision drift from the GL entries.')]
class ResyncStockValues extends Command
{
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN — no data will be changed.');
        } else {
            $this->warn('LIVE RUN — data WILL be updated.');
            if (! $this->confirm('Continue?')) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        $this->info('Querying current_stock_by_batch records...');

        /*
         * Strategy:
         *
         * The authoritative per-line value is goods_receipt_note_items.total_cost,
         * stored as decimal(15,2) and used verbatim for the GL debit entry.
         *
         * current_stock_by_batch.total_value was stored as qty * unit_cost via PHP
         * float arithmetic, introducing sub-cent drift that makes the Stock Report
         * disagree with the General Ledger.
         *
         * Fix rule (per batch row):
         *  - Untouched batch (quantity_on_hand == grni.quantity_accepted):
         *      total_value = grni.total_cost  (exact GL value)
         *  - Partially consumed batch:
         *      total_value = ROUND(quantity_on_hand * unit_cost, 2)
         *      (best approximation; uses DB-stored decimal, no float drift)
         *
         * Join path:
         *   current_stock_by_batch
         *     → stock_valuation_layers (ON stock_batch_id)
         *     → goods_receipt_note_items (ON grn_item_id)
         */
        $rows = DB::table('current_stock_by_batch as csb')
            ->join('stock_valuation_layers as svl', function ($join) {
                $join->on('svl.stock_batch_id', '=', 'csb.stock_batch_id')
                    ->on('svl.warehouse_id', '=', 'csb.warehouse_id');
            })
            ->join('goods_receipt_note_items as grni', 'grni.id', '=', 'svl.grn_item_id')
            ->select(
                'csb.id',
                'csb.product_id',
                'csb.quantity_on_hand',
                'csb.unit_cost',
                'csb.total_value as current_total_value',
                'grni.quantity_accepted',
                'grni.total_cost as grn_total_cost',
            )
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No current_stock_by_batch records found via valuation layer link. Nothing to do.');

            return self::SUCCESS;
        }

        $updated = 0;
        $skipped = 0;
        $totalDrift = 0.0;

        $this->output->progressStart($rows->count());

        foreach ($rows as $row) {
            $qtyOnHand = (float) $row->quantity_on_hand;
            $unitCost = (float) $row->unit_cost;
            $qtyAccepted = (float) $row->quantity_accepted;
            $grniTotalCost = (float) $row->grn_total_cost;
            $currentTotalValue = (float) $row->current_total_value;

            // Determine the correct total_value
            if (abs($qtyOnHand - $qtyAccepted) < 0.001) {
                // Batch is fully untouched — use the exact GRN total_cost (matches GL)
                $correctValue = $grniTotalCost;
            } else {
                // Batch partially consumed — proportional value using DB decimals
                $correctValue = round($qtyOnHand * $unitCost, 2);
            }

            $drift = abs($correctValue - $currentTotalValue);

            if ($drift < 0.001) {
                $skipped++;
                $this->output->progressAdvance();

                continue;
            }

            $totalDrift += $drift;

            $this->line(sprintf(
                '  Batch CSB#%d | product_id=%d | qty=%.2f | old=%.2f | new=%.2f | drift=%.4f',
                $row->id,
                $row->product_id,
                $qtyOnHand,
                $currentTotalValue,
                $correctValue,
                $drift
            ));

            if (! $isDryRun) {
                DB::table('current_stock_by_batch')
                    ->where('id', $row->id)
                    ->update(['total_value' => $correctValue, 'last_updated' => now()]);

                Log::info('ResyncStockValues: updated CSB total_value', [
                    'csb_id' => $row->id,
                    'product_id' => $row->product_id,
                    'old_value' => $currentTotalValue,
                    'new_value' => $correctValue,
                    'drift' => $drift,
                ]);
            }

            $updated++;
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Records inspected', $rows->count()],
                ['Records updated (or would update)', $updated],
                ['Records skipped (no drift)', $skipped],
                ['Total drift corrected (Rs.)', number_format($totalDrift, 4)],
                ['Mode', $isDryRun ? 'DRY RUN' : 'LIVE'],
            ]
        );

        if ($isDryRun) {
            $this->warn('Re-run without --dry-run to apply changes.');
        } else {
            $this->info('Done. Run the Stock Availability Report to verify amounts now match the GL.');
        }

        return self::SUCCESS;
    }
}
