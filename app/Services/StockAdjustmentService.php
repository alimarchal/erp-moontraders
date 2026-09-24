<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\CurrentStockByBatch;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockLedgerEntry;
use App\Models\StockMovement;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockAdjustmentService
{
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

            foreach ($adjustment->items as $item) {
                if (! $item->stock_batch_id) {
                    throw new \Exception('All items must have a stock batch assigned');
                }

                $this->guardAgainstRemovingMoreThanOnHand($adjustment, $item);
                $this->processAdjustmentItem($adjustment, $item);
            }

            $journalEntry = $this->createAdjustmentJournalEntry($adjustment);

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

    protected function processAdjustmentItem(StockAdjustment $adjustment, $item): void
    {
        $ledgerService = app(InventoryLedgerService::class);
        $ledgerService->recordAdjustment(
            productId: $item->product_id,
            warehouseId: $adjustment->warehouse_id,
            vehicleId: null,
            debitQty: $item->adjustment_quantity > 0 ? $item->adjustment_quantity : 0,
            creditQty: $item->adjustment_quantity < 0 ? abs($item->adjustment_quantity) : 0,
            unitCost: $item->unit_cost,
            date: $adjustment->adjustment_date,
            notes: "{$adjustment->adjustment_type} - {$adjustment->reason}",
            batchId: $item->stock_batch_id,
            stockAdjustmentId: $adjustment->id
        );

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

        $movement = StockMovement::create([
            'movement_type' => 'adjustment',
            'reference_type' => StockAdjustment::class,
            'reference_id' => $adjustment->id,
            'movement_date' => $adjustment->adjustment_date,
            'product_id' => $item->product_id,
            'stock_batch_id' => $item->stock_batch_id,
            'warehouse_id' => $adjustment->warehouse_id,
            'quantity' => $item->adjustment_quantity,
            'uom_id' => $item->uom_id,
            'unit_cost' => $item->unit_cost,
            'total_value' => abs($item->adjustment_value),
            'created_by' => auth()->id(),
        ]);

        $previousEntry = StockLedgerEntry::where('product_id', $item->product_id)
            ->where('warehouse_id', $adjustment->warehouse_id)
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        $quantityBalance = ($previousEntry->quantity_balance ?? 0) + $item->adjustment_quantity;

        StockLedgerEntry::create([
            'product_id' => $item->product_id,
            'warehouse_id' => $adjustment->warehouse_id,
            'stock_batch_id' => $item->stock_batch_id,
            'entry_date' => $adjustment->adjustment_date,
            'stock_movement_id' => $movement->id,
            'quantity_in' => $item->adjustment_quantity > 0 ? $item->adjustment_quantity : 0,
            'quantity_out' => $item->adjustment_quantity < 0 ? abs($item->adjustment_quantity) : 0,
            'quantity_balance' => $quantityBalance,
            'valuation_rate' => $item->unit_cost,
            'stock_value' => $quantityBalance * $item->unit_cost,
            'reference_type' => StockAdjustment::class,
            'reference_id' => $adjustment->id,
            'created_at' => now(),
        ]);

        $this->updateValuationLayer($adjustment, $item, $movement);

        // ⚠️  This call MUST remain the final step in processAdjustmentItem.
        // It re-aggregates current_stock from stock_valuation_layers using
        // SUM(quantity_remaining * unit_cost) — the only accurate formula.
        // Removing or reordering this call will leave current_stock stale
        // and the /inventory/current-stock totals will be wrong.
        $inventoryService = app(InventoryService::class);
        $inventoryService->syncCurrentStockFromValuationLayers($item->product_id, $adjustment->warehouse_id);
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

    protected function createAdjustmentJournalEntry(StockAdjustment $adjustment)
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

            $totalValue = $adjustment->items->sum('adjustment_value');
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
