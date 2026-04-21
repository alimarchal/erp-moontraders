<?php

namespace App\Console\Commands\Stock;

use App\Models\CurrentStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResyncStockValues extends Command
{
    protected $signature = 'stock:resync-values {--dry-run : Preview changes without saving}';

    protected $description = 'Re-syncs current_stock_by_batch, stock_valuation_layers, and current_stock total_value columns from authoritative GRN item total_cost to eliminate float precision drift.';

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

            if ($drift < 0.0001) {
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
                ['[Phase A] CSB records inspected', $rows->count()],
                ['[Phase A] CSB records updated (or would update)', $updated],
                ['[Phase A] CSB records skipped (no drift)', $skipped],
                ['[Phase A] CSB total drift corrected (Rs.)', number_format($totalDrift, 4)],
                ['Mode', $isDryRun ? 'DRY RUN' : 'LIVE'],
            ]
        );

        // ─────────────────────────────────────────────────────────────
        // PHASE B — Fix stock_valuation_layers.total_value
        // Same strategy as Phase A but applied to the FIFO layer rows.
        // ─────────────────────────────────────────────────────────────
        $this->newLine();
        $this->info('Phase B — Fixing stock_valuation_layers.total_value ...');

        $svlRows = DB::table('stock_valuation_layers as svl')
            ->join('goods_receipt_note_items as grni', 'grni.id', '=', 'svl.grn_item_id')
            ->select(
                'svl.id',
                'svl.product_id',
                'svl.quantity_remaining',
                'svl.unit_cost',
                'svl.total_value as current_total_value',
                'grni.quantity_accepted',
                'grni.total_cost as grn_total_cost',
            )
            ->get();

        $svlUpdated = 0;
        $svlSkipped = 0;
        $svlTotalDrift = 0.0;

        $this->output->progressStart($svlRows->count());

        foreach ($svlRows as $row) {
            $qtyRemaining = (float) $row->quantity_remaining;
            $unitCost = (float) $row->unit_cost;
            $qtyAccepted = (float) $row->quantity_accepted;
            $grniTotalCost = (float) $row->grn_total_cost;
            $currentTotalValue = (float) $row->current_total_value;

            if (abs($qtyRemaining - $qtyAccepted) < 0.001) {
                $correctValue = $grniTotalCost;
            } elseif ($qtyRemaining <= 0) {
                $correctValue = 0.0;
            } else {
                $correctValue = round($qtyRemaining * $unitCost, 2);
            }

            $drift = abs($correctValue - $currentTotalValue);

            if ($drift < 0.0001) {
                $svlSkipped++;
                $this->output->progressAdvance();

                continue;
            }

            $svlTotalDrift += $drift;

            $this->line(sprintf(
                '  SVL#%d | product_id=%d | qty_remaining=%.2f | old=%.2f | new=%.2f | drift=%.4f',
                $row->id,
                $row->product_id,
                $qtyRemaining,
                $currentTotalValue,
                $correctValue,
                $drift
            ));

            if (! $isDryRun) {
                DB::table('stock_valuation_layers')
                    ->where('id', $row->id)
                    ->update(['total_value' => $correctValue]);

                Log::info('ResyncStockValues: updated SVL total_value', [
                    'svl_id' => $row->id,
                    'product_id' => $row->product_id,
                    'old_value' => $currentTotalValue,
                    'new_value' => $correctValue,
                    'drift' => $drift,
                ]);
            }

            $svlUpdated++;
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        $this->newLine();
        $this->table(
            ['Phase B Metric', 'Value'],
            [
                ['SVL records inspected', $svlRows->count()],
                ['SVL records updated (or would update)', $svlUpdated],
                ['SVL records skipped (no drift)', $svlSkipped],
                ['SVL total drift corrected (Rs.)', number_format($svlTotalDrift, 4)],
            ]
        );

        // ─────────────────────────────────────────────────────────────
        // PHASE C — Re-sync current_stock.total_value from SVL
        // After Phase B fixes SVL, re-aggregate into current_stock so
        // the /inventory/current-stock page shows the correct totals.
        //
        // ⚠️  MUST use SUM(quantity_remaining * unit_cost) — NOT SUM(total_value).
        // stock_valuation_layers.total_value is the original receipt total and is
        // never decremented by sales, issues, or stock adjustments. Using it here
        // would cause current_stock to show pre-adjustment values even after stock
        // was reduced — exactly the bug that inflated Engro inventory by Rs 6,512
        // after SA-2026-0001 on 2026-04-21 (8 × Olper 1500ml + 27 × Olper TBA).
        // ─────────────────────────────────────────────────────────────
        $this->newLine();
        $this->info('Phase C — Re-syncing current_stock from fixed valuation layers ...');

        $productWarehousePairs = DB::table('stock_valuation_layers')
            ->select('product_id', 'warehouse_id')
            ->distinct()
            ->get();

        $csUpdated = 0;
        $csTotalDrift = 0.0;

        $this->output->progressStart($productWarehousePairs->count());

        foreach ($productWarehousePairs as $pair) {
            $layerData = DB::table('stock_valuation_layers')
                ->where('product_id', $pair->product_id)
                ->where('warehouse_id', $pair->warehouse_id)
                ->where('quantity_remaining', '>', 0)
                ->selectRaw('COALESCE(SUM(quantity_remaining), 0) as total_qty, COALESCE(SUM(quantity_remaining * unit_cost), 0) as total_value')
                ->first();

            $totalQty = (float) ($layerData->total_qty ?? 0);
            $totalValue = round((float) ($layerData->total_value ?? 0), 2);
            $avgCost = $totalQty > 0 ? round($totalValue / $totalQty, 6) : 0.0;

            $currentStock = CurrentStock::where('product_id', $pair->product_id)
                ->where('warehouse_id', $pair->warehouse_id)
                ->first();

            if (! $currentStock) {
                $this->output->progressAdvance();

                continue;
            }

            $drift = abs($totalValue - (float) $currentStock->total_value);

            if ($drift < 0.0001) {
                $this->output->progressAdvance();

                continue;
            }

            $csTotalDrift += $drift;

            $this->line(sprintf(
                '  CS product_id=%d warehouse_id=%d | old=%.2f | new=%.2f | drift=%.4f',
                $pair->product_id,
                $pair->warehouse_id,
                (float) $currentStock->total_value,
                $totalValue,
                $drift
            ));

            if (! $isDryRun) {
                $currentStock->total_value = $totalValue;
                $currentStock->average_cost = $avgCost;
                $currentStock->quantity_on_hand = $totalQty;
                $currentStock->quantity_available = max(0, $totalQty - ($currentStock->quantity_reserved ?? 0));
                $currentStock->last_updated = now();
                $currentStock->save();

                Log::info('ResyncStockValues: updated current_stock total_value', [
                    'product_id' => $pair->product_id,
                    'warehouse_id' => $pair->warehouse_id,
                    'old_value' => (float) $currentStock->getOriginal('total_value'),
                    'new_value' => $totalValue,
                    'drift' => $drift,
                ]);
            }

            $csUpdated++;
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        $this->newLine();
        $this->table(
            ['Phase C Metric', 'Value'],
            [
                ['current_stock pairs inspected', $productWarehousePairs->count()],
                ['current_stock records updated (or would update)', $csUpdated],
                ['current_stock total drift corrected (Rs.)', number_format($csTotalDrift, 4)],
            ]
        );

        if ($isDryRun) {
            $this->warn('Re-run without --dry-run to apply changes.');
        } else {
            $this->info('Done. Stock Availability Report, /inventory/current-stock, and GL should now all agree.');
        }

        // ─────────────────────────────────────────────────────────────
        // PHASE D — Populate daily_inventory_snapshots for opening stock date
        //
        // The historical Stock Availability Report first checks
        // daily_inventory_snapshots. If that table is empty it falls back to
        // stock_ledger_entries whose stock_value is a *cumulative running balance*
        // per product+warehouse, causing the per-batch GROUP BY in the fallback
        // query to double-count values. Creating accurate snapshots from the
        // now-fixed current_stock_by_batch data bypasses that fallback entirely.
        // ─────────────────────────────────────────────────────────────
        $this->newLine();
        $this->info('Phase D — Creating daily_inventory_snapshots for opening stock date ...');

        $openingDates = DB::table('goods_receipt_notes')
            ->where('is_opening_stock', true)
            ->distinct()
            ->pluck('receipt_date');

        if ($openingDates->isEmpty()) {
            $this->warn('  No opening stock GRNs found — skipping Phase D.');
        }

        $snapCreated = 0;
        $snapUpdated = 0;

        foreach ($openingDates as $snapDate) {
            // Skip dates that already have scheduler-created snapshots.
            // Phase D writes CURRENT stock levels to historical dates, which
            // would overwrite accurate snapshots the nightly scheduler already
            // created on that date. Only populate if no snapshot exists yet.
            $existingCount = DB::table('daily_inventory_snapshots')
                ->where('date', $snapDate)
                ->count();

            if ($existingCount > 0) {
                $this->warn("  Skipping {$snapDate} — {$existingCount} snapshots already exist (created by scheduler).");

                continue;
            }

            $this->line("  Snapshotting date: {$snapDate}");

            // Aggregate correct values from current_stock_by_batch per product+warehouse
            $csbAgg = DB::table('current_stock_by_batch')
                ->where('status', 'active')
                ->whereNotNull('warehouse_id')
                ->selectRaw('product_id, warehouse_id, SUM(quantity_on_hand) as qty, SUM(total_value) as val')
                ->groupBy('product_id', 'warehouse_id')
                ->get();

            $this->output->progressStart($csbAgg->count());

            foreach ($csbAgg as $row) {
                $qty = (float) $row->qty;
                $val = round((float) $row->val, 2);
                $avgCost = $qty > 0 ? round($val / $qty, 2) : 0.0;

                $exists = DB::table('daily_inventory_snapshots')
                    ->where('date', $snapDate)
                    ->where('product_id', $row->product_id)
                    ->where('warehouse_id', $row->warehouse_id)
                    ->whereNull('vehicle_id')
                    ->exists();

                if ($isDryRun) {
                    $this->line(sprintf(
                        '  [D] %s p=%d w=%d qty=%.2f avg=%.2f val=%.2f [%s]',
                        $snapDate, $row->product_id, $row->warehouse_id, $qty, $avgCost, $val,
                        $exists ? 'UPDATE' : 'INSERT'
                    ));
                    $exists ? $snapUpdated++ : $snapCreated++;
                } else {
                    DB::table('daily_inventory_snapshots')->updateOrInsert(
                        [
                            'date' => $snapDate,
                            'product_id' => $row->product_id,
                            'warehouse_id' => $row->warehouse_id,
                            'vehicle_id' => null,
                        ],
                        [
                            'quantity_on_hand' => $qty,
                            'average_cost' => $avgCost,
                            'total_value' => $val,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                    $exists ? $snapUpdated++ : $snapCreated++;
                }

                $this->output->progressAdvance();
            }

            $this->output->progressFinish();
        }

        $this->newLine();
        $this->table(
            ['Phase D Metric', 'Value'],
            [
                ['Snapshots inserted', $snapCreated],
                ['Snapshots updated', $snapUpdated],
            ]
        );

        if ($isDryRun) {
            $this->warn('Re-run without --dry-run to apply changes.');
        } else {
            $this->info('Done. Historical Stock Availability Report for the opening stock date will now show correct values.');
        }

        return self::SUCCESS;
    }
}
