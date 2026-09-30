<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\CurrentStockByBatch;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockLedgerEntry;
use App\Models\StockMovement;
use App\Models\StockValuationLayer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockAdjustmentService
{
    /**
     * A line's unit cost this close to what the batch is carried at is not a cost correction.
     * The form loads the batch master's 2-decimal cost while stock carries the receipt's six.
     */
    private const COST_TOLERANCE = 0.01;

    public function __construct(private StockValuationService $stockValuation) {}

    public function createAdjustment(array $data): array
    {
        try {
            DB::beginTransaction();

            $adjustment = $this->createAdjustmentRecord([
                'adjustment_date' => $data['adjustment_date'],
                'warehouse_id' => $data['warehouse_id'],
                'adjustment_type' => $data['adjustment_type'],
                'product_recall_id' => $data['product_recall_id'] ?? null,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
            ]);
            $adjustmentNumber = $adjustment->adjustment_number;

            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $adjustment->items()->create($itemData);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'data' => $adjustment->fresh('items'),
                'message' => "Stock adjustment {$adjustmentNumber} created successfully",
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create stock adjustment: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to create stock adjustment: '.$e->getMessage(),
            ];
        }
    }

    public function postAdjustment(StockAdjustment $adjustment): array
    {
        try {
            DB::beginTransaction();

            if ($adjustment->status !== 'draft') {
                throw new \Exception('Only draft adjustments can be posted');
            }

            if ($adjustment->items->isEmpty()) {
                throw new \Exception('Cannot post adjustment without items');
            }

            $revaluationValue = 0.0;

            foreach ($adjustment->items as $item) {
                if (! $item->stock_batch_id) {
                    throw new \Exception('All items must have a stock batch assigned');
                }

                $this->guardAgainstRemovingMoreThanOnHand($adjustment, $item);
                $revaluationValue += $this->processAdjustmentItem($adjustment, $item);
            }

            $journalEntry = $this->createAdjustmentJournalEntry($adjustment, $revaluationValue);

            $adjustment->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => auth()->id(),
                'journal_entry_id' => $journalEntry?->id,
            ]);

            DB::commit();

            return [
                'success' => true,
                'data' => $adjustment->fresh(),
                'message' => "Adjustment {$adjustment->adjustment_number} posted successfully",
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to post stock adjustment: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to post stock adjustment: '.$e->getMessage(),
            ];
        }
    }

    /**
     * A draft keeps the quantity counted when it was written, but the batch can be issued
     * from before it is posted. Removing more than the batch now holds would floor current
     * stock at zero while the ledger and the journal still take the full quantity out.
     */
    protected function guardAgainstRemovingMoreThanOnHand(StockAdjustment $adjustment, $item): void
    {
        if ($item->adjustment_quantity >= 0) {
            return;
        }

        $onHand = (float) CurrentStockByBatch::where('stock_batch_id', $item->stock_batch_id)
            ->where('warehouse_id', $adjustment->warehouse_id)
            ->lockForUpdate()
            ->get(['quantity_on_hand'])
            ->sum('quantity_on_hand');

        $removing = abs((float) $item->adjustment_quantity);

        if ($removing - $onHand > 0.001) {
            $batchCode = StockBatch::whereKey($item->stock_batch_id)->value('batch_code');
            $productName = $item->product?->product_name ?? "product {$item->product_id}";

            throw new \Exception(sprintf(
                '%s batch %s has only %s in stock now, but this adjustment removes %s (it was counted at %s). Recount and update the draft before posting.',
                $productName,
                $batchCode,
                rtrim(rtrim(number_format($onHand, 3, '.', ''), '0'), '.'),
                rtrim(rtrim(number_format($removing, 3, '.', ''), '0'), '.'),
                rtrim(rtrim(number_format((float) $item->system_quantity, 3, '.', ''), '0'), '.')
            ));
        }
    }

    /**
     * Post one line and return the value its cost correction changed the batch by.
     *
     * A line whose unit cost differs from what the batch is carried at revalues the batch
     * first, so the counted difference then moves at the corrected cost.
     */
    protected function processAdjustmentItem(StockAdjustment $adjustment, $item): float
    {
        $revaluationValue = $this->revalueBatch($adjustment, $item);

        if ($revaluationValue !== null && (float) $item->adjustment_quantity != 0.0) {
            // The line's own movement below carries the quantity; this zero-quantity one is
            // what marks the revaluation in all three ledgers for the nightly rebuild.
            $this->recordMovement($adjustment, $item, 0.0);
        }

        $movement = $this->recordMovement($adjustment, $item, (float) $item->adjustment_quantity);

        $stockByBatch = CurrentStockByBatch::where('stock_batch_id', $item->stock_batch_id)
            ->where('warehouse_id', $adjustment->warehouse_id)
            ->lockForUpdate()
            ->first();

        if ($stockByBatch) {
            $qtyBefore = (float) $stockByBatch->quantity_on_hand;
            $stockByBatch->quantity_on_hand += $item->adjustment_quantity;
            if ($stockByBatch->quantity_on_hand <= 0) {
                $stockByBatch->quantity_on_hand = 0;
                $stockByBatch->total_value = 0.0;
                $stockByBatch->status = 'depleted';
            } elseif ($item->adjustment_quantity > 0) {
                // Increase: add the exact adjustment value
                $stockByBatch->total_value = round((float) ($stockByBatch->total_value ?? 0) + (float) $item->adjustment_value, 4);
                // Goods issues only allocate from active rows, so stock found on a depleted
                // batch would otherwise sit on the books but never be issued.
                $stockByBatch->status = 'active';
            } else {
                // Decrease: proportional deduction
                $ratio = $qtyBefore > 0 ? $stockByBatch->quantity_on_hand / $qtyBefore : 0;
                $stockByBatch->total_value = round((float) ($stockByBatch->total_value ?? 0) * $ratio, 4);
            }
            $stockByBatch->save();
        }

        if ($item->adjustment_quantity > 0) {
            StockBatch::whereKey($item->stock_batch_id)
                ->where('status', 'depleted')
                ->update(['status' => 'active', 'is_active' => true]);
        }

        if ($item->adjustment_quantity < 0) {
            $remainingQty = CurrentStockByBatch::where('stock_batch_id', $item->stock_batch_id)
                ->sum('quantity_on_hand');

            if ($remainingQty <= 0) {
                $batch = StockBatch::find($item->stock_batch_id);
                if ($batch) {
                    $batch->status = $adjustment->adjustment_type === 'recall' ? 'recalled' : 'depleted';
                    $batch->is_active = false;
                    $batch->save();
                }
            }
        }

        $this->updateValuationLayer($adjustment, $item, $movement);

        // ⚠️  This call MUST remain the final step in processAdjustmentItem.
        // It re-aggregates current_stock from stock_valuation_layers using
        // SUM(quantity_remaining * unit_cost) — the only accurate formula.
        // Removing or reordering this call will leave current_stock stale
        // and the /inventory/current-stock totals will be wrong.
        $inventoryService = app(InventoryService::class);
        $inventoryService->syncCurrentStockFromValuationLayers($item->product_id, $adjustment->warehouse_id);

        return $revaluationValue ?? 0.0;
    }

    /**
     * Write the line's quantity to the inventory ledger, stock_movements and the stock
     * ledger together, so the three always hold the same rows for an adjustment.
     */
    protected function recordMovement(StockAdjustment $adjustment, $item, float $quantity): StockMovement
    {
        app(InventoryLedgerService::class)->recordAdjustment(
            productId: $item->product_id,
            warehouseId: $adjustment->warehouse_id,
            vehicleId: null,
            debitQty: $quantity > 0 ? $quantity : 0,
            creditQty: $quantity < 0 ? abs($quantity) : 0,
            unitCost: $item->unit_cost,
            date: $adjustment->adjustment_date,
            notes: "{$adjustment->adjustment_type} - {$adjustment->reason}",
            batchId: $item->stock_batch_id,
            stockAdjustmentId: $adjustment->id
        );

        $movement = StockMovement::create([
            'movement_type' => 'adjustment',
            'reference_type' => StockAdjustment::class,
            'reference_id' => $adjustment->id,
            'movement_date' => $adjustment->adjustment_date,
            'product_id' => $item->product_id,
            'stock_batch_id' => $item->stock_batch_id,
            'warehouse_id' => $adjustment->warehouse_id,
            'quantity' => $quantity,
            'uom_id' => $item->uom_id,
            'unit_cost' => $item->unit_cost,
            'total_value' => round(abs($quantity) * (float) $item->unit_cost, 4),
            'created_by' => auth()->id(),
        ]);

        $previousEntry = StockLedgerEntry::where('product_id', $item->product_id)
            ->where('warehouse_id', $adjustment->warehouse_id)
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        $quantityBalance = ($previousEntry->quantity_balance ?? 0) + $quantity;

        StockLedgerEntry::create([
            'product_id' => $item->product_id,
            'warehouse_id' => $adjustment->warehouse_id,
            'stock_batch_id' => $item->stock_batch_id,
            'entry_date' => $adjustment->adjustment_date,
            'stock_movement_id' => $movement->id,
            'quantity_in' => $quantity > 0 ? $quantity : 0,
            'quantity_out' => $quantity < 0 ? abs($quantity) : 0,
            'quantity_balance' => $quantityBalance,
            'valuation_rate' => $item->unit_cost,
            'stock_value' => $quantityBalance * $item->unit_cost,
            'reference_type' => StockAdjustment::class,
            'reference_id' => $adjustment->id,
            'created_at' => now(),
        ]);

        return $movement;
    }

    /**
     * Carry the batch at the line's unit cost when it differs from the batch's current cost.
     *
     * The quantity on hand is restated in current_stock_by_batch and every valuation layer
     * of the batch, and the batch master takes the new cost because goods issues price what
     * they take from it. Returns the value the batch changed by, or null when the cost stands.
     *
     * @throws \RuntimeException when the batch also holds stock in another warehouse, whose
     *                           value this adjustment's warehouse cannot restate, or when
     *                           part of it is still on a van
     */
    protected function revalueBatch(StockAdjustment $adjustment, $item): ?float
    {
        $stockByBatch = CurrentStockByBatch::where('stock_batch_id', $item->stock_batch_id)
            ->where('warehouse_id', $adjustment->warehouse_id)
            ->lockForUpdate()
            ->first();

        $newCost = (float) $item->unit_cost;
        $oldCost = (float) ($stockByBatch?->unit_cost ?? 0);

        if (! $stockByBatch || abs($newCost - $oldCost) <= self::COST_TOLERANCE) {
            return null;
        }

        $heldElsewhere = CurrentStockByBatch::where('stock_batch_id', $item->stock_batch_id)
            ->where('warehouse_id', '!=', $adjustment->warehouse_id)
            ->where('quantity_on_hand', '>', 0)
            ->exists();

        if ($heldElsewhere) {
            throw new \RuntimeException(sprintf(
                'Batch %s also holds stock in another warehouse, so its unit cost cannot be changed from this warehouse. Post the line at %s.',
                StockBatch::whereKey($item->stock_batch_id)->value('batch_code'),
                number_format($oldCost, 2, '.', '')
            ));
        }

        $onVans = $this->quantityOnVans((int) $item->stock_batch_id);

        if ($onVans > StockValuationService::QTY_EPSILON) {
            throw new \RuntimeException(sprintf(
                'Batch %s still has %s out on a van. Settle it before changing the unit cost, or returns will come back at the old cost and put Stock In Hand out of step.',
                StockBatch::whereKey($item->stock_batch_id)->value('batch_code'),
                rtrim(rtrim(number_format($onVans, 3, '.', ''), '0'), '.')
            ));
        }

        $quantity = (float) $stockByBatch->quantity_on_hand;

        $stockByBatch->unit_cost = $newCost;
        $stockByBatch->total_value = round($quantity * $newCost, 4);
        $stockByBatch->save();

        StockValuationLayer::where('stock_batch_id', $item->stock_batch_id)
            ->where('warehouse_id', $adjustment->warehouse_id)
            ->lockForUpdate()
            ->get()
            ->each(function (StockValuationLayer $layer) use ($newCost): void {
                $layer->unit_cost = $newCost;
                $layer->value_remaining = round((float) $layer->quantity_remaining * $newCost, 4);
                $layer->save();
            });

        StockBatch::whereKey($item->stock_batch_id)->update(['unit_cost' => $newCost]);

        return round($quantity * ($newCost - $oldCost), 2);
    }

    /**
     * Move the adjusted quantity through the batch's valuation layers.
     *
     * A count shortage is taken out oldest layer first; a count surplus goes
     * back into the batch's own layer at its receipt cost. Creating a separate
     * layer for a surplus is what broke current_stock before: that layer had no
     * grn_item_id, so the Goods Issue batch picker (which joins
     * goods_receipt_note_items) never offered it, the quantity could never be
     * issued, and it stayed on the books forever.
     */
    protected function updateValuationLayer(StockAdjustment $adjustment, $item, StockMovement $movement): void
    {
        $quantity = (float) $item->adjustment_quantity;

        if ($quantity < 0) {
            $this->stockValuation->consumeBatch(
                (int) $item->stock_batch_id,
                (int) $adjustment->warehouse_id,
                abs($quantity)
            );

            return;
        }

        $this->stockValuation->restoreBatch(
            (int) $item->stock_batch_id,
            (int) $adjustment->warehouse_id,
            $quantity,
            (float) $item->unit_cost,
            $movement->id
        );
    }

    /**
     * @param  float  $revaluationValue  what cost corrections on the lines changed stock by, on top of their counted quantities
     */
    /**
     * What of a batch is on vans: issued, less what was sold, returned or found short.
     * Quantities are signed as stock_movements stores them, issues and sales negative.
     */
    protected function quantityOnVans(int $stockBatchId): float
    {
        return (float) StockMovement::where('stock_batch_id', $stockBatchId)
            ->whereNotNull('vehicle_id')
            ->selectRaw("COALESCE(SUM(CASE movement_type
                WHEN 'transfer' THEN -quantity
                WHEN 'sale' THEN quantity
                WHEN 'return' THEN -quantity
                WHEN 'shortage' THEN quantity
                ELSE 0 END), 0) as quantity")
            ->value('quantity');
    }

    protected function createAdjustmentJournalEntry(StockAdjustment $adjustment, float $revaluationValue = 0.0)
    {
        try {
            $inventoryAccount = ChartOfAccount::where('account_name', 'Stock In Hand')->first();
            // Looked up by code, as the GRN and goods issue postings do. The name differs
            // between installs ("Warehouse & Inventory" in production), so a name lookup
            // silently left every adjustment line without a cost center.
            $warehouseCostCenter = CostCenter::where('code', 'CC006')->first();

            $expenseAccount = match ($adjustment->adjustment_type) {
                'recall' => ChartOfAccount::where('account_name', 'Stock Loss on Recalls')->first(),
                'damage' => ChartOfAccount::where('account_name', 'Stock Loss - Damage')->first(),
                'theft' => ChartOfAccount::where('account_name', 'Stock Loss - Theft')->first(),
                'expiry' => ChartOfAccount::where('account_name', 'Stock Loss - Expiry')->first(),
                default => ChartOfAccount::where('account_name', 'Stock Loss - Other')->first(),
            };

            if (! $inventoryAccount || ! $expenseAccount) {
                throw new \RuntimeException(
                    'Required GL account not found for a '.$adjustment->adjustment_type.' adjustment '
                    .'(Stock In Hand and its Stock Loss account must both exist). Nothing was posted.'
                );
            }

            $totalValue = round((float) $adjustment->items->sum('adjustment_value') + $revaluationValue, 2);
            $isNegativeAdjustment = $totalValue < 0;
            $absValue = abs($totalValue);

            if ($absValue == 0) {
                return null;
            }

            $journalLines = [];

            if ($isNegativeAdjustment) {
                $journalLines[] = [
                    'line_no' => 1,
                    'account_id' => $expenseAccount->id,
                    'debit' => $absValue,
                    'credit' => 0,
                    'description' => ucfirst($adjustment->adjustment_type)." - {$adjustment->reason}",
                    'cost_center_id' => $warehouseCostCenter?->id,
                ];
                $journalLines[] = [
                    'line_no' => 2,
                    'account_id' => $inventoryAccount->id,
                    'debit' => 0,
                    'credit' => $absValue,
                    'description' => "Inventory reduction - {$adjustment->adjustment_number}",
                    'cost_center_id' => $warehouseCostCenter?->id,
                ];
            } else {
                $journalLines[] = [
                    'line_no' => 1,
                    'account_id' => $inventoryAccount->id,
                    'debit' => $absValue,
                    'credit' => 0,
                    'description' => "Inventory increase - {$adjustment->adjustment_number}",
                    'cost_center_id' => $warehouseCostCenter?->id,
                ];
                $journalLines[] = [
                    'line_no' => 2,
                    'account_id' => $expenseAccount->id,
                    'debit' => 0,
                    'credit' => $absValue,
                    'description' => ucfirst($adjustment->adjustment_type)." reversal - {$adjustment->reason}",
                    'cost_center_id' => $warehouseCostCenter?->id,
                ];
            }

            $journalEntryData = [
                'entry_date' => $adjustment->adjustment_date->toDateString(),
                'reference' => $adjustment->adjustment_number,
                'description' => 'Stock Adjustment - '.ucfirst($adjustment->adjustment_type),
                'lines' => $journalLines,
                'auto_post' => true,
            ];

            $accountingService = app(AccountingService::class);
            $result = $accountingService->createJournalEntry($journalEntryData);

            if (! $result['success']) {
                throw new \RuntimeException('Journal entry could not be created: '.$result['message']);
            }

            return $result['data'];
        } catch (\Exception $e) {
            // Rethrown so postAdjustment rolls the whole posting back. Returning null here used
            // to let the adjustment post anyway — stock reduced, GL untouched — leaving a
            // silent difference between the stock report and Stock In Hand.
            Log::error('Failed to create journal entry for adjustment: '.$e->getMessage());

            throw $e;
        }
    }

    public function generateAdjustmentNumber(): string
    {
        $year = now()->year;
        $prefix = "SA-{$year}-";

        $nextNumber = StockAdjustment::withTrashed()
            ->where('adjustment_number', 'like', "{$prefix}%")
            ->pluck('adjustment_number')
            ->map(fn (string $number): int => (int) substr($number, strlen($prefix)))
            ->max() + 1;

        return sprintf('%s%04d', $prefix, $nextNumber);
    }

    public function createAdjustmentRecord(array $data): StockAdjustment
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return StockAdjustment::create([
                    'adjustment_number' => $this->generateAdjustmentNumber(),
                    'adjustment_date' => $data['adjustment_date'],
                    'warehouse_id' => $data['warehouse_id'],
                    'adjustment_type' => $data['adjustment_type'],
                    'product_recall_id' => $data['product_recall_id'] ?? null,
                    'reason' => $data['reason'],
                    'notes' => $data['notes'] ?? null,
                    'status' => $data['status'] ?? 'draft',
                ]);
            } catch (QueryException $exception) {
                if (! $this->isAdjustmentNumberCollision($exception) || $attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Unable to generate a unique stock adjustment number.');
    }

    protected function isAdjustmentNumberCollision(QueryException $exception): bool
    {
        return in_array($exception->getCode(), ['23000', '23505'], true)
            && str_contains($exception->getMessage(), 'adjustment_number');
    }
}
