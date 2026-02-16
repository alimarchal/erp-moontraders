<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\CurrentStock;
use App\Models\CurrentStockByBatch;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockLedgerEntry;
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

        $inventoryService = app(InventoryService::class);
        $inventoryService->syncCurrentStockFromValuationLayers($item->product_id, $adjustment->warehouse_id);

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

        StockLedgerEntry::create([
            'ledger_date' => $adjustment->adjustment_date,
            'product_id' => $item->product_id,
            'warehouse_id' => $adjustment->warehouse_id,
            'stock_batch_id' => $item->stock_batch_id,
            'transaction_type' => 'adjustment',
            'reference_type' => StockAdjustment::class,
            'reference_id' => $adjustment->id,
            'quantity_in' => $item->adjustment_quantity > 0 ? $item->adjustment_quantity : 0,
            'quantity_out' => $item->adjustment_quantity < 0 ? abs($item->adjustment_quantity) : 0,
            'unit_cost' => $item->unit_cost,
            'total_value' => abs($item->adjustment_value),
            'notes' => $adjustment->reason,
        ]);

        $this->updateValuationLayer($adjustment, $item);
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
            $inventoryAccount = ChartOfAccount::where('account_code', '1151')->first();
            $warehouseCostCenter = CostCenter::where('code', 'CC006')->first();

            $expenseAccount = match ($adjustment->adjustment_type) {
                'recall' => ChartOfAccount::where('account_code', '5280')->first(),
                'damage' => ChartOfAccount::where('account_code', '5281')->first(),
                'theft' => ChartOfAccount::where('account_code', '5282')->first(),
                'expiry' => ChartOfAccount::where('account_code', '5283')->first(),
                default => ChartOfAccount::where('account_code', '5284')->first(),
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
            ->orderByRaw('CAST(SUBSTRING(adjustment_number, ?) AS UNSIGNED) DESC', [strlen($prefix) + 1])
            ->first();

        $nextNumber = $lastAdjustment
            ? (int) substr($lastAdjustment->adjustment_number, strlen($prefix)) + 1
            : 1;

        return sprintf('%s%04d', $prefix, $nextNumber);
    }
}
