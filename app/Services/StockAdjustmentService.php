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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockAdjustmentService
{
    public function createAdjustment(array $data): array
    {
        try {
            DB::beginTransaction();

            $adjustmentNumber = $this->generateAdjustmentNumber();

            $adjustment = StockAdjustment::create([
                'adjustment_number' => $adjustmentNumber,
                'adjustment_date' => $data['adjustment_date'],
                'warehouse_id' => $data['warehouse_id'],
                'adjustment_type' => $data['adjustment_type'],
                'product_recall_id' => $data['product_recall_id'] ?? null,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
            ]);

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
            batchId: $item->stock_batch_id
        );

        $stockByBatch = CurrentStockByBatch::where('stock_batch_id', $item->stock_batch_id)
            ->where('warehouse_id', $adjustment->warehouse_id)
            ->lockForUpdate()
            ->first();

        if ($stockByBatch) {
            $stockByBatch->quantity_on_hand += $item->adjustment_quantity;
            if ($stockByBatch->quantity_on_hand <= 0) {
                $stockByBatch->quantity_on_hand = 0;
                $stockByBatch->status = 'depleted';
            }
            $stockByBatch->total_value = $stockByBatch->quantity_on_hand * $stockByBatch->unit_cost;
            $stockByBatch->save();
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

        $this->updateValuationLayer($adjustment, $item);

        $inventoryService = app(InventoryService::class);
        $inventoryService->syncCurrentStockFromValuationLayers($item->product_id, $adjustment->warehouse_id);
    }

    protected function updateValuationLayer(StockAdjustment $adjustment, $item): void
    {
        if ($item->adjustment_quantity < 0) {
            $qtyToReduce = abs($item->adjustment_quantity);
            $layers = StockValuationLayer::where('product_id', $item->product_id)
                ->where('warehouse_id', $adjustment->warehouse_id)
                ->where('stock_batch_id', $item->stock_batch_id)
                ->where('quantity_remaining', '>', 0)
                ->orderBy('receipt_date')
                ->get();

            foreach ($layers as $layer) {
                if ($qtyToReduce <= 0) {
                    break;
                }

                $qtyFromThisLayer = min($layer->quantity_remaining, $qtyToReduce);
                $layer->quantity_remaining -= $qtyFromThisLayer;
                $layer->value_remaining = $layer->quantity_remaining * $layer->unit_cost;
                $layer->save();

                $qtyToReduce -= $qtyFromThisLayer;
            }
        } else {
            StockValuationLayer::create([
                'product_id' => $item->product_id,
                'warehouse_id' => $adjustment->warehouse_id,
                'stock_batch_id' => $item->stock_batch_id,
                'receipt_date' => $adjustment->adjustment_date,
                'transaction_type' => 'adjustment',
                'reference_type' => StockAdjustment::class,
                'reference_id' => $adjustment->id,
                'quantity_received' => $item->adjustment_quantity,
                'quantity_remaining' => $item->adjustment_quantity,
                'unit_cost' => $item->unit_cost,
                'total_value' => $item->adjustment_value,
                'value_remaining' => $item->adjustment_value,
            ]);
        }
    }

    protected function createAdjustmentJournalEntry(StockAdjustment $adjustment)
    {
        try {
            $inventoryAccount = ChartOfAccount::where('account_name', 'Stock In Hand')->first();
            $warehouseCostCenter = CostCenter::where('name', 'Warehouse')->first();

            $expenseAccount = match ($adjustment->adjustment_type) {
                'recall' => ChartOfAccount::where('account_name', 'Stock Loss on Recalls')->first(),
                'damage' => ChartOfAccount::where('account_name', 'Stock Loss - Damage')->first(),
                'theft' => ChartOfAccount::where('account_name', 'Stock Loss - Theft')->first(),
                'expiry' => ChartOfAccount::where('account_name', 'Stock Loss - Expiry')->first(),
                default => ChartOfAccount::where('account_name', 'Stock Loss - Other')->first(),
            };

            if (! $inventoryAccount || ! $expenseAccount) {
                Log::warning("Required accounts not found for adjustment {$adjustment->id}");

                return null;
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

            return $result['success'] ? $result['data'] : null;
        } catch (\Exception $e) {
            Log::error('Failed to create journal entry for adjustment: '.$e->getMessage());

            return null;
        }
    }

    public function generateAdjustmentNumber(): string
    {
        $year = now()->year;
        $prefix = "SA-{$year}-";

        $lastAdjustment = StockAdjustment::where('adjustment_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastAdjustment
            ? (int) substr($lastAdjustment->adjustment_number, strlen($prefix)) + 1
            : 1;

        return sprintf('%s%04d', $prefix, $nextNumber);
    }
}
