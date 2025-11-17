<?php

namespace App\Services;

use App\Models\GoodsIssue;
use App\Models\SalesSettlement;
use App\Models\StockMovement;
use App\Models\CurrentStock;
use App\Models\CurrentStockByBatch;
use App\Models\StockBatch;
use App\Models\VanStockBalance;
use App\Models\StockLedgerEntry;
use App\Models\StockValuationLayer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DistributionService
{
    /**
     * Post Goods Issue to transfer inventory from warehouse to vehicle
     * NOW WITH BATCH TRACKING AND PROMOTIONAL PRIORITY
     */
    public function postGoodsIssue(GoodsIssue $goodsIssue): array
    {
        try {
            DB::beginTransaction();

            if ($goodsIssue->status === 'issued') {
                throw new \Exception('Goods Issue is already posted');
            }

            if ($goodsIssue->status === 'cancelled') {
                throw new \Exception('Cannot post cancelled Goods Issue');
            }

            foreach ($goodsIssue->items as $item) {
                // Check warehouse stock availability
                $warehouseStock = CurrentStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $goodsIssue->warehouse_id)
                    ->first();

                if (!$warehouseStock || $warehouseStock->quantity_on_hand < $item->quantity_issued) {
                    throw new \Exception("Insufficient stock for product ID {$item->product_id}. Available: " . ($warehouseStock->quantity_on_hand ?? 0) . ", Required: {$item->quantity_issued}");
                }

                // Allocate stock from batches with PROMOTIONAL PRIORITY
                $batchAllocations = $this->allocateStockFromBatches(
                    $item->product_id,
                    $goodsIssue->warehouse_id,
                    $item->quantity_issued
                );

                if ($batchAllocations['total_allocated'] < $item->quantity_issued) {
                    throw new \Exception("Could not allocate sufficient stock from batches for product ID {$item->product_id}");
                }

                // Create stock movements for each batch
                foreach ($batchAllocations['batches'] as $batchAllocation) {
                    $batch = $batchAllocation['batch'];
                    $qtyFromBatch = $batchAllocation['quantity'];

                    // Create stock movement - OUT from warehouse
                    StockMovement::create([
                        'movement_type' => 'transfer',
                        'reference_type' => 'App\Models\GoodsIssue',
                        'reference_id' => $goodsIssue->id,
                        'movement_date' => $goodsIssue->issue_date,
                        'product_id' => $item->product_id,
                        'stock_batch_id' => $batch->id, // NOW TRACKING BATCH
                        'warehouse_id' => $goodsIssue->warehouse_id,
                        'quantity' => -$qtyFromBatch, // Negative for outgoing
                        'uom_id' => $item->uom_id,
                        'unit_cost' => $batch->unit_cost,
                        'total_value' => $qtyFromBatch * $batch->unit_cost,
                        'created_by' => auth()->id() ?? 1,
                    ]);

                    // Update current_stock_by_batch
                    $stockByBatch = CurrentStockByBatch::where('stock_batch_id', $batch->id)
                        ->where('warehouse_id', $goodsIssue->warehouse_id)
                        ->first();

                    if ($stockByBatch) {
                        $stockByBatch->quantity_on_hand -= $qtyFromBatch;
                        if ($stockByBatch->quantity_on_hand <= 0) {
                            $stockByBatch->quantity_on_hand = 0;
                            $stockByBatch->status = 'depleted';
                        }
                        $stockByBatch->last_updated = now();
                        $stockByBatch->save();
                    }

                    // Update stock valuation layer
                    $valuationLayer = StockValuationLayer::where('stock_batch_id', $batch->id)
                        ->where('warehouse_id', $goodsIssue->warehouse_id)
                        ->where('quantity_remaining', '>', 0)
                        ->first();

                    if ($valuationLayer) {
                        $valuationLayer->quantity_remaining -= $qtyFromBatch;
                        if ($valuationLayer->quantity_remaining < 0) {
                            $valuationLayer->quantity_remaining = 0;
                        }
                        $valuationLayer->save();
                    }
                }

                // Update warehouse current stock
                $warehouseStock->quantity_on_hand -= $item->quantity_issued;
                $warehouseStock->quantity_available -= $item->quantity_issued;
                $warehouseStock->total_value = $warehouseStock->quantity_on_hand * $warehouseStock->average_cost;
                $warehouseStock->last_updated = now();
                $warehouseStock->save();

                // Update or create van stock balance (aggregate, not batch-specific)
                $vanStock = VanStockBalance::firstOrNew([
                    'vehicle_id' => $goodsIssue->vehicle_id,
                    'product_id' => $item->product_id,
                ]);

                // Set opening balance if this is first issue of the day
                if ($vanStock->quantity_on_hand == 0 && !$vanStock->exists) {
                    $vanStock->opening_balance = 0;
                }

                $vanStock->quantity_on_hand += $item->quantity_issued;

                // Calculate weighted average cost from batch allocations
                $totalValue = 0;
                foreach ($batchAllocations['batches'] as $batchAllocation) {
                    $totalValue += $batchAllocation['quantity'] * $batchAllocation['batch']->unit_cost;
                }
                $vanStock->average_cost = $item->quantity_issued > 0 ? $totalValue / $item->quantity_issued : 0;
                $vanStock->last_updated = now();
                $vanStock->save();
            }

            // Update goods issue status
            $goodsIssue->update([
                'status' => 'issued',
                'posted_at' => now(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => "Goods Issue {$goodsIssue->issue_number} posted successfully with promotional priority",
                'data' => $goodsIssue->fresh(),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to post goods issue: ' . $e->getMessage(), [
                'goods_issue_id' => $goodsIssue->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to post Goods Issue: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Allocate stock from batches with PROMOTIONAL PRIORITY
     * Priority order: 1 (promotional) → 2 → 3 → ... → 99 (regular)
     *
     * @param int $productId
     * @param int $warehouseId
     * @param float $quantityNeeded
     * @return array ['batches' => [...], 'total_allocated' => float]
     */
    private function allocateStockFromBatches(int $productId, int $warehouseId, float $quantityNeeded): array
    {
        $allocations = [];
        $remainingQty = $quantityNeeded;

        // Get available batches ordered by PROMOTIONAL PRIORITY, then FIFO
        $batches = CurrentStockByBatch::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('quantity_on_hand', '>', 0)
            ->where('status', 'active')
            ->with('stockBatch')
            ->get()
            ->sortBy(function ($stockByBatch) {
                $batch = $stockByBatch->stockBatch;
                // Sort by: priority_order ASC (1 before 99), then receipt_date ASC (FIFO)
                return [
                    $batch->priority_order ?? 99,
                    $batch->receipt_date ?? '9999-12-31'
                ];
            });

        foreach ($batches as $stockByBatch) {
            if ($remainingQty <= 0) {
                break;
            }

            $batch = $stockByBatch->stockBatch;
            $availableQty = $stockByBatch->quantity_on_hand;
            $qtyToAllocate = min($remainingQty, $availableQty);

            $allocations[] = [
                'batch' => $batch,
                'quantity' => $qtyToAllocate,
                'is_promotional' => $batch->is_promotional ?? false,
                'priority_order' => $batch->priority_order ?? 99,
            ];

            $remainingQty -= $qtyToAllocate;
        }

        return [
            'batches' => $allocations,
            'total_allocated' => $quantityNeeded - $remainingQty,
        ];
    }

    /**
     * Post Sales Settlement to record sales, returns, and update inventory
     * WITH BATCH TRACKING
     */
    public function postSalesSettlement(SalesSettlement $settlement): array
    {
        try {
            DB::beginTransaction();

            if ($settlement->status === 'posted') {
                throw new \Exception('Sales Settlement is already posted');
            }

            // Load relationships
            $settlement->load(['items.goodsIssueItem', 'items.product']);

            // Process each product settlement
            foreach ($settlement->items as $item) {
                // Reduce van stock by quantity sold
                $vanStock = VanStockBalance::where('vehicle_id', $settlement->vehicle_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if (!$vanStock) {
                    throw new \Exception("No van stock found for product ID {$item->product_id}");
                }

                $totalToReduce = $item->quantity_sold + $item->quantity_returned + $item->quantity_shortage;
                if ($vanStock->quantity_on_hand < $totalToReduce) {
                    throw new \Exception("Insufficient van stock for product ID {$item->product_id}. Available: {$vanStock->quantity_on_hand}, Required: {$totalToReduce}");
                }

                // Allocate sold quantity from batches (promotional first)
                $batchAllocations = $this->allocateStockFromBatches(
                    $item->product_id,
                    $settlement->warehouse_id,
                    $item->quantity_sold
                );

                // Get UOM from goods issue item if available, otherwise from product
                $uomId = $item->goodsIssueItem?->uom_id ?? $item->product->base_uom_id ?? 1;

                // Create stock movements for sales (from batches)
                foreach ($batchAllocations['batches'] as $batchAllocation) {
                    StockMovement::create([
                        'movement_type' => 'sale',
                        'reference_type' => 'App\Models\SalesSettlement',
                        'reference_id' => $settlement->id,
                        'movement_date' => $settlement->settlement_date,
                        'product_id' => $item->product_id,
                        'stock_batch_id' => $batchAllocation['batch']->id,
                        'warehouse_id' => $settlement->warehouse_id,
                        'quantity' => -$batchAllocation['quantity'],
                        'uom_id' => $uomId,
                        'unit_cost' => $batchAllocation['batch']->unit_cost,
                        'total_value' => $batchAllocation['quantity'] * $batchAllocation['batch']->unit_cost,
                        'created_by' => auth()->id() ?? 1,
                    ]);
                }

                // Update van stock - subtract sold quantity
                $vanStock->quantity_on_hand -= $item->quantity_sold;
                $vanStock->last_updated = now();
                $vanStock->save();

                // If there are returns, add back to warehouse stock
                if ($item->quantity_returned > 0) {
                    // Get batch allocations for returns
                    $returnBatchAllocations = $this->allocateStockFromBatches(
                        $item->product_id,
                        $settlement->warehouse_id,
                        $item->quantity_returned
                    );

                    foreach ($returnBatchAllocations['batches'] as $batchAllocation) {
                        $batch = $batchAllocation['batch'];

                        StockMovement::create([
                            'movement_type' => 'return',
                            'reference_type' => 'App\Models\SalesSettlement',
                            'reference_id' => $settlement->id,
                            'movement_date' => $settlement->settlement_date,
                            'product_id' => $item->product_id,
                            'stock_batch_id' => $batch->id,
                            'warehouse_id' => $settlement->warehouse_id,
                            'quantity' => $batchAllocation['quantity'],
                            'uom_id' => null,
                            'unit_cost' => $batch->unit_cost,
                            'total_value' => $batchAllocation['quantity'] * $batch->unit_cost,
                            'created_by' => auth()->id() ?? 1,
                        ]);

                        // Update current_stock_by_batch for returns
                        $stockByBatch = CurrentStockByBatch::where('stock_batch_id', $batch->id)
                            ->where('warehouse_id', $settlement->warehouse_id)
                            ->first();

                        if ($stockByBatch) {
                            $stockByBatch->quantity_on_hand += $batchAllocation['quantity'];
                            $stockByBatch->status = 'active';
                            $stockByBatch->last_updated = now();
                            $stockByBatch->save();
                        }
                    }

                    // Update warehouse current stock
                    $warehouseStock = CurrentStock::firstOrNew([
                        'product_id' => $item->product_id,
                        'warehouse_id' => $settlement->warehouse_id,
                    ]);

                    $warehouseStock->quantity_on_hand += $item->quantity_returned;
                    $warehouseStock->quantity_available += $item->quantity_returned;

                    // Recalculate average cost
                    $totalQty = $warehouseStock->quantity_on_hand;
                    $totalValue = ($warehouseStock->total_value ?? 0) + ($item->quantity_returned * $item->unit_cost);
                    $warehouseStock->average_cost = $totalQty > 0 ? $totalValue / $totalQty : 0;
                    $warehouseStock->total_value = $totalValue;
                    $warehouseStock->last_updated = now();
                    $warehouseStock->save();

                    // Reduce van stock by returns
                    $vanStock->quantity_on_hand -= $item->quantity_returned;
                    $vanStock->save();
                }

                // Handle shortages (inventory adjustment)
                if ($item->quantity_shortage > 0) {
                    // Allocate shortages from batches
                    $shortageBatchAllocations = $this->allocateStockFromBatches(
                        $item->product_id,
                        $settlement->warehouse_id,
                        $item->quantity_shortage
                    );

                    foreach ($shortageBatchAllocations['batches'] as $batchAllocation) {
                        StockMovement::create([
                            'movement_type' => 'adjustment',
                            'reference_type' => 'App\Models\SalesSettlement',
                            'reference_id' => $settlement->id,
                            'movement_date' => $settlement->settlement_date,
                            'product_id' => $item->product_id,
                            'stock_batch_id' => $batchAllocation['batch']->id,
                            'warehouse_id' => $settlement->warehouse_id,
                            'quantity' => -$batchAllocation['quantity'],
                            'uom_id' => null,
                            'unit_cost' => $batchAllocation['batch']->unit_cost,
                            'total_value' => $batchAllocation['quantity'] * $batchAllocation['batch']->unit_cost,
                            'created_by' => auth()->id() ?? 1,
                        ]);
                    }

                    // Reduce van stock by shortage
                    $vanStock->quantity_on_hand -= $item->quantity_shortage;
                    $vanStock->save();
                }
            }

            // Create accounting journal entry if needed
            $journalEntry = $this->createSalesJournalEntry($settlement);

            // Update settlement status
            $settlement->update([
                'status' => 'posted',
                'posted_at' => now(),
                'journal_entry_id' => $journalEntry ? $journalEntry->id : null,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => "Sales Settlement {$settlement->settlement_number} posted successfully with promotional priority",
                'data' => $settlement->fresh(),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to post sales settlement: ' . $e->getMessage(), [
                'settlement_id' => $settlement->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to post Sales Settlement: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Create journal entry for sales settlement
     */
    protected function createSalesJournalEntry(SalesSettlement $settlement)
    {
        try {
            // Get accounts
            $cashAccount = \App\Models\ChartOfAccount::where('account_code', '1111')->first(); // Cash in Hand
            $arAccount = \App\Models\ChartOfAccount::where('account_code', '1211')->first(); // Accounts Receivable
            $salesAccount = \App\Models\ChartOfAccount::where('account_code', '4111')->first(); // Sales Revenue
            $cogsAccount = \App\Models\ChartOfAccount::where('account_code', '5111')->first(); // COGS
            $inventoryAccount = \App\Models\ChartOfAccount::where('account_code', '1161')->first(); // Inventory

            if (!$cashAccount || !$salesAccount || !$cogsAccount || !$inventoryAccount) {
                Log::warning('Required accounts not found for sales journal entry');
                return null;
            }

            $lines = [];

            // Debit: Cash (cash sales)
            if ($settlement->cash_sales_amount > 0) {
                $lines[] = [
                    'account_id' => $cashAccount->id,
                    'debit' => $settlement->cash_sales_amount,
                    'credit' => 0,
                    'description' => "Cash sales from {$settlement->employee->name}",
                    'cost_center_id' => 4, // Sales & Distribution
                ];
            }

            // Debit: Accounts Receivable (credit sales)
            if ($settlement->credit_sales_amount > 0 && $arAccount) {
                $lines[] = [
                    'account_id' => $arAccount->id,
                    'debit' => $settlement->credit_sales_amount,
                    'credit' => 0,
                    'description' => "Credit sales from {$settlement->employee->name}",
                    'cost_center_id' => 4,
                ];
            }

            // Credit: Sales Revenue
            $lines[] = [
                'account_id' => $salesAccount->id,
                'debit' => 0,
                'credit' => $settlement->total_sales_amount,
                'description' => "Sales - {$settlement->employee->name} ({$settlement->settlement_date})",
                'cost_center_id' => 4,
            ];

            // Debit: COGS (calculated from actual batch costs)
            $totalCOGS = $settlement->items->sum('total_cogs');
            $lines[] = [
                'account_id' => $cogsAccount->id,
                'debit' => $totalCOGS,
                'credit' => 0,
                'description' => "Cost of goods sold (promotional priority applied)",
                'cost_center_id' => 4,
            ];

            // Credit: Inventory
            $lines[] = [
                'account_id' => $inventoryAccount->id,
                'debit' => 0,
                'credit' => $totalCOGS,
                'description' => "Inventory sold",
                'cost_center_id' => 6, // Warehouse & Inventory
            ];

            $journalEntryData = [
                'entry_date' => $settlement->settlement_date,
                'reference' => $settlement->settlement_number,
                'description' => "Daily sales settlement - {$settlement->employee->name}",
                'reference_type' => 'App\Models\SalesSettlement',
                'reference_id' => $settlement->id,
                'lines' => $lines,
                'auto_post' => true,
            ];

            $accountingService = app(AccountingService::class);
            $result = $accountingService->createJournalEntry($journalEntryData);

            if ($result['success']) {
                return $result['data'];
            }

            return null;

        } catch (\Exception $e) {
            Log::error("Exception creating sales journal entry: " . $e->getMessage());
            return null;
        }
    }
}
