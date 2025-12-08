<?php

namespace App\Services;

use App\Models\CurrentStock;
use App\Models\CurrentStockByBatch;
use App\Models\GoodsIssue;
use App\Models\SalesSettlement;
use App\Models\StockMovement;
use App\Models\StockValuationLayer;
use App\Models\VanStockBalance;
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
                // Lock CurrentStock row to prevent race conditions
                $warehouseStock = CurrentStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $goodsIssue->warehouse_id)
                    ->lockForUpdate()
                    ->first();

                if (! $warehouseStock || $warehouseStock->quantity_on_hand < $item->quantity_issued) {
                    throw new \Exception("Insufficient stock for product ID {$item->product_id}. Available: ".($warehouseStock->quantity_on_hand ?? 0).", Required: {$item->quantity_issued}");
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
                        'stock_batch_id' => $batch->id,
                        'warehouse_id' => $goodsIssue->warehouse_id,
                        'quantity' => -$qtyFromBatch,
                        'uom_id' => $item->uom_id,
                        'unit_cost' => $batch->unit_cost,
                        'total_value' => $qtyFromBatch * $batch->unit_cost,
                        'created_by' => auth()->id() ?? 1,
                    ]);

                    // Lock and update current_stock_by_batch
                    $stockByBatch = CurrentStockByBatch::where('stock_batch_id', $batch->id)
                        ->where('warehouse_id', $goodsIssue->warehouse_id)
                        ->lockForUpdate()
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

                    // Lock and update stock valuation layer
                    $valuationLayer = StockValuationLayer::where('stock_batch_id', $batch->id)
                        ->where('warehouse_id', $goodsIssue->warehouse_id)
                        ->where('quantity_remaining', '>', 0)
                        ->lockForUpdate()
                        ->first();

                    if ($valuationLayer) {
                        $valuationLayer->quantity_remaining -= $qtyFromBatch;
                        if ($valuationLayer->quantity_remaining < 0) {
                            $valuationLayer->quantity_remaining = 0;
                        }
                        $valuationLayer->save();
                    }
                }

                // Recalculate CurrentStock from StockValuationLayer (source of truth)
                $this->syncCurrentStockFromValuationLayers($item->product_id, $goodsIssue->warehouse_id);

                // Update or create van stock balance (aggregate, not batch-specific)
                $vanStock = VanStockBalance::firstOrNew([
                    'vehicle_id' => $goodsIssue->vehicle_id,
                    'product_id' => $item->product_id,
                ]);

                // Set opening balance if this is first issue of the day
                if ($vanStock->quantity_on_hand == 0 && ! $vanStock->exists) {
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
            Log::error('Failed to post goods issue: '.$e->getMessage(), [
                'goods_issue_id' => $goodsIssue->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to post Goods Issue: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Allocate stock from batches with PROMOTIONAL PRIORITY
     * Priority order: 1 (promotional) → 2 → 3 → ... → 99 (regular)
     *
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
                    $batch->receipt_date ?? '9999-12-31',
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
     * Sync CurrentStock from StockValuationLayer (source of truth)
     * This ensures CurrentStock always matches the sum of valuation layers
     */
    private function syncCurrentStockFromValuationLayers(int $productId, int $warehouseId): void
    {
        // Calculate totals from stock_valuation_layers (source of truth)
        $layerData = StockValuationLayer::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->selectRaw('
                COALESCE(SUM(quantity_remaining), 0) as total_qty,
                COALESCE(SUM(quantity_remaining * unit_cost), 0) as total_value
            ')
            ->first();

        $totalQty = (float) ($layerData->total_qty ?? 0);
        $totalValue = (float) ($layerData->total_value ?? 0);
        $avgCost = $totalQty > 0 ? $totalValue / $totalQty : 0;

        // Count batches from current_stock_by_batch
        $totalBatches = CurrentStockByBatch::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('quantity_on_hand', '>', 0)
            ->count();

        $promotionalBatches = CurrentStockByBatch::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('is_promotional', true)
            ->where('quantity_on_hand', '>', 0)
            ->count();

        $priorityBatches = CurrentStockByBatch::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('priority_order', '<', 99)
            ->where('quantity_on_hand', '>', 0)
            ->count();

        // Update or create CurrentStock with calculated values
        $currentStock = CurrentStock::lockForUpdate()->firstOrNew([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
        ]);

        $currentStock->quantity_on_hand = $totalQty;
        $currentStock->quantity_available = $totalQty - ($currentStock->quantity_reserved ?? 0);
        $currentStock->average_cost = $avgCost;
        $currentStock->total_value = $totalValue;
        $currentStock->total_batches = $totalBatches;
        $currentStock->promotional_batches = $promotionalBatches;
        $currentStock->priority_batches = $priorityBatches;
        $currentStock->last_updated = now();
        $currentStock->save();

        Log::debug('CurrentStock synced from valuation layers', [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity_on_hand' => $totalQty,
            'total_value' => $totalValue,
        ]);
    }

    /**
     * Post Sales Settlement to record sales, returns, and update inventory
     * WITH BATCH TRACKING - Returns and shortages go back to SAME batch
     */
    public function postSalesSettlement(SalesSettlement $settlement): array
    {
        try {
            DB::beginTransaction();

            if ($settlement->status === 'posted') {
                throw new \Exception('Sales Settlement is already posted');
            }

            // Load relationships including batch details
            $settlement->load(['items.goodsIssueItem', 'items.product', 'items.batches.stockBatch']);

            // Calculate and store gross profit
            $totalGrossProfit = 0;

            // Process each product settlement
            foreach ($settlement->items as $item) {
                // Reduce van stock by quantity sold
                $vanStock = VanStockBalance::where('vehicle_id', $settlement->vehicle_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if (! $vanStock) {
                    throw new \Exception("No van stock found for product ID {$item->product_id}");
                }

                $totalToReduce = $item->quantity_sold + $item->quantity_returned + $item->quantity_shortage;
                if ($vanStock->quantity_on_hand < $totalToReduce) {
                    throw new \Exception("Insufficient van stock for product ID {$item->product_id}. Available: {$vanStock->quantity_on_hand}, Required: {$totalToReduce}");
                }

                // Get UOM from goods issue item if available, otherwise from product
                $uomId = $item->goodsIssueItem?->uom_id ?? $item->product->base_uom_id ?? 1;

                // Process using ACTUAL BATCH DATA from sales_settlement_item_batches
                foreach ($item->batches as $itemBatch) {
                    $batch = $itemBatch->stockBatch;
                    if (! $batch) {
                        continue;
                    }

                    // 1. SALES: Create stock movement for sold quantities from this batch
                    if ($itemBatch->quantity_sold > 0) {
                        StockMovement::create([
                            'movement_type' => 'sale',
                            'reference_type' => 'App\Models\SalesSettlement',
                            'reference_id' => $settlement->id,
                            'movement_date' => $settlement->settlement_date,
                            'product_id' => $item->product_id,
                            'stock_batch_id' => $batch->id,
                            'warehouse_id' => $settlement->warehouse_id,
                            'quantity' => -$itemBatch->quantity_sold,
                            'uom_id' => $uomId,
                            'unit_cost' => $itemBatch->unit_cost,
                            'total_value' => $itemBatch->quantity_sold * $itemBatch->unit_cost,
                            'created_by' => auth()->id() ?? 1,
                        ]);
                    }

                    // 2. RETURNS: Go back to SAME batch (not random allocation)
                    if ($itemBatch->quantity_returned > 0) {
                        StockMovement::create([
                            'movement_type' => 'return',
                            'reference_type' => 'App\Models\SalesSettlement',
                            'reference_id' => $settlement->id,
                            'movement_date' => $settlement->settlement_date,
                            'product_id' => $item->product_id,
                            'stock_batch_id' => $batch->id,
                            'warehouse_id' => $settlement->warehouse_id,
                            'quantity' => $itemBatch->quantity_returned, // Positive for return
                            'uom_id' => $uomId,
                            'unit_cost' => $itemBatch->unit_cost,
                            'total_value' => $itemBatch->quantity_returned * $itemBatch->unit_cost,
                            'created_by' => auth()->id() ?? 1,
                        ]);

                        // Update current_stock_by_batch - returns go to SAME batch
                        $stockByBatch = CurrentStockByBatch::where('stock_batch_id', $batch->id)
                            ->where('warehouse_id', $settlement->warehouse_id)
                            ->first();

                        if ($stockByBatch) {
                            $stockByBatch->quantity_on_hand += $itemBatch->quantity_returned;
                            $stockByBatch->status = 'active';
                            $stockByBatch->last_updated = now();
                            $stockByBatch->save();
                        } else {
                            // Create new stock by batch record if not exists
                            CurrentStockByBatch::create([
                                'stock_batch_id' => $batch->id,
                                'product_id' => $item->product_id,
                                'warehouse_id' => $settlement->warehouse_id,
                                'quantity_on_hand' => $itemBatch->quantity_returned,
                                'status' => 'active',
                                'last_updated' => now(),
                            ]);
                        }

                        // Update stock valuation layer for returns
                        $valuationLayer = StockValuationLayer::where('stock_batch_id', $batch->id)
                            ->where('warehouse_id', $settlement->warehouse_id)
                            ->first();

                        if ($valuationLayer) {
                            $valuationLayer->quantity_remaining += $itemBatch->quantity_returned;
                            $valuationLayer->save();
                        }
                    }

                    // 3. SHORTAGES: Record as loss from SAME batch
                    if ($itemBatch->quantity_shortage > 0) {
                        StockMovement::create([
                            'movement_type' => 'shortage', // New type for clarity
                            'reference_type' => 'App\Models\SalesSettlement',
                            'reference_id' => $settlement->id,
                            'movement_date' => $settlement->settlement_date,
                            'product_id' => $item->product_id,
                            'stock_batch_id' => $batch->id,
                            'warehouse_id' => $settlement->warehouse_id,
                            'quantity' => -$itemBatch->quantity_shortage, // Negative for loss
                            'uom_id' => $uomId,
                            'unit_cost' => $itemBatch->unit_cost,
                            'total_value' => $itemBatch->quantity_shortage * $itemBatch->unit_cost,
                            'created_by' => auth()->id() ?? 1,
                        ]);
                    }
                }

                // Update van stock - subtract all quantities (sold + returned + shortage)
                $vanStock->quantity_on_hand -= $totalToReduce;
                $vanStock->last_updated = now();
                $vanStock->save();

                // Sync warehouse CurrentStock from valuation layers for returns/shortages
                if ($item->quantity_returned > 0 || $item->quantity_shortage > 0) {
                    $this->syncCurrentStockFromValuationLayers($item->product_id, $settlement->warehouse_id);
                }

                // Calculate gross profit for this item
                $itemGrossProfit = $item->total_sales_value - $item->total_cogs;
                $totalGrossProfit += $itemGrossProfit;
            }

            // Calculate total COGS
            $totalCOGS = $settlement->items->sum('total_cogs');

            // Create accounting journal entry
            $journalEntry = $this->createSalesJournalEntry($settlement);

            // Process ledger entries for credit sales
            $ledgerService = app(LedgerService::class);
            $ledgerService->processSalesSettlement($settlement);

            // Update settlement status with gross profit and total COGS
            $settlement->update([
                'status' => 'posted',
                'posted_at' => now(),
                'journal_entry_id' => $journalEntry ? $journalEntry->id : null,
                'gross_profit' => $totalGrossProfit,
                'total_cogs' => $totalCOGS,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => "Sales Settlement {$settlement->settlement_number} posted successfully with batch tracking",
                'data' => $settlement->fresh(),
                'gross_profit' => $totalGrossProfit,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to post sales settlement: '.$e->getMessage(), [
                'settlement_id' => $settlement->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to post Sales Settlement: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Create comprehensive journal entries for sales settlement
     * Includes: Sales, COGS, Expenses, Returns, Shortages, Bank Transfers, Cheques, Credit Recoveries
     */
    protected function createSalesJournalEntry(SalesSettlement $settlement)
    {
        try {
            // Load required relationships
            $settlement->load(['employee', 'items', 'creditSales']);

            // Get all required accounts
            $accounts = $this->getAccountingAccounts();

            if (! $this->validateRequiredAccounts($accounts)) {
                Log::warning('Required accounts not found for sales journal entry', [
                    'settlement_id' => $settlement->id,
                    'missing_accounts' => $this->getMissingAccounts($accounts),
                ]);

                return null;
            }

            $lines = [];
            $employeeName = $settlement->employee->name ?? 'Unknown';

            // ====================================================================
            // 1. SALES REVENUE RECOGNITION
            // ====================================================================

            // Debit: Cash in Hand (cash sales)
            if ($settlement->cash_sales_amount > 0) {
                $lines[] = [
                    'account_id' => $accounts['cash']->id,
                    'debit' => $settlement->cash_sales_amount,
                    'credit' => 0,
                    'description' => "Cash sales - {$employeeName}",
                    'cost_center_id' => 4, // Sales & Distribution
                ];
            }

            // Debit: Accounts Receivable (credit sales)
            if ($settlement->credit_sales_amount > 0) {
                $lines[] = [
                    'account_id' => $accounts['debtors']->id,
                    'debit' => $settlement->credit_sales_amount,
                    'credit' => 0,
                    'description' => "Credit sales - {$employeeName}",
                    'cost_center_id' => 4,
                ];
            }

            // Debit: Cheques in Hand (if cheques collected)
            if ($settlement->cheques_collected > 0) {
                $lines[] = [
                    'account_id' => $accounts['earnest_money']->id, // Using earnest money as cheques receivable
                    'debit' => $settlement->cheques_collected,
                    'credit' => 0,
                    'description' => "Cheques collected - {$employeeName} ({$settlement->cheque_count} cheques)",
                    'cost_center_id' => 4,
                ];
            }

            // Credit: Sales Revenue (total sales)
            if ($settlement->total_sales_amount > 0) {
                $settlementDate = \Carbon\Carbon::parse($settlement->settlement_date)->format('d M Y');
                $lines[] = [
                    'account_id' => $accounts['sales']->id,
                    'debit' => 0,
                    'credit' => $settlement->total_sales_amount,
                    'description' => "Sales revenue - {$employeeName} ({$settlementDate})",
                    'cost_center_id' => 4,
                ];
            }

            // ====================================================================
            // 2. COST OF GOODS SOLD
            // ====================================================================

            $totalCOGS = $settlement->items->sum('total_cogs');
            if ($totalCOGS > 0) {
                // Debit: COGS
                $lines[] = [
                    'account_id' => $accounts['cogs']->id,
                    'debit' => $totalCOGS,
                    'credit' => 0,
                    'description' => "Cost of goods sold - {$settlement->items->count()} items",
                    'cost_center_id' => 4,
                ];

                // Credit: Inventory
                $lines[] = [
                    'account_id' => $accounts['inventory']->id,
                    'debit' => 0,
                    'credit' => $totalCOGS,
                    'description' => 'Inventory reduction - goods sold',
                    'cost_center_id' => 6, // Warehouse & Inventory
                ];
            }

            // ====================================================================
            // 3. RETURNS TO WAREHOUSE
            // ====================================================================

            $totalReturnValue = 0;
            foreach ($settlement->items as $item) {
                if ($item->quantity_returned > 0) {
                    $totalReturnValue += $item->quantity_returned * $item->unit_cost;
                }
            }

            if ($totalReturnValue > 0) {
                // Debit: Inventory (returns back to stock)
                $lines[] = [
                    'account_id' => $accounts['inventory']->id,
                    'debit' => $totalReturnValue,
                    'credit' => 0,
                    'description' => 'Goods returned to warehouse',
                    'cost_center_id' => 6,
                ];

                // Credit: COGS (reverse COGS for returns)
                $lines[] = [
                    'account_id' => $accounts['cogs']->id,
                    'debit' => 0,
                    'credit' => $totalReturnValue,
                    'description' => 'COGS reversal - goods returned',
                    'cost_center_id' => 4,
                ];
            }

            // ====================================================================
            // 4. SHORTAGES / INVENTORY LOSS
            // ====================================================================

            $totalShortageValue = 0;
            foreach ($settlement->items as $item) {
                if ($item->quantity_shortage > 0) {
                    $totalShortageValue += $item->quantity_shortage * $item->unit_cost;
                }
            }

            if ($totalShortageValue > 0) {
                // Debit: Miscellaneous Expenses (shortage loss)
                $lines[] = [
                    'account_id' => $accounts['misc_expense']->id,
                    'debit' => $totalShortageValue,
                    'credit' => 0,
                    'description' => "Inventory shortage/loss - {$employeeName}",
                    'cost_center_id' => 4,
                ];

                // Credit: Inventory (reduce inventory by shortage)
                $lines[] = [
                    'account_id' => $accounts['inventory']->id,
                    'debit' => 0,
                    'credit' => $totalShortageValue,
                    'description' => 'Inventory reduction - shortage',
                    'cost_center_id' => 6,
                ];
            }

            // ====================================================================
            // 5. EXPENSE RECOGNITION
            // ====================================================================

            $totalExpenses = 0;

            // Toll Tax / Labor
            if ($settlement->expense_toll_tax > 0) {
                $lines[] = [
                    'account_id' => $accounts['toll_tax']->id,
                    'debit' => $settlement->expense_toll_tax,
                    'credit' => 0,
                    'description' => "Toll tax expense - {$employeeName}",
                    'cost_center_id' => 4,
                ];
                $totalExpenses += $settlement->expense_toll_tax;
            }

            // AMR Powder Claim
            if ($settlement->expense_amr_powder_claim > 0) {
                $lines[] = [
                    'account_id' => $accounts['amr_powder']->id,
                    'debit' => $settlement->expense_amr_powder_claim,
                    'credit' => 0,
                    'description' => "AMR powder claim - {$employeeName}",
                    'cost_center_id' => 4,
                ];
                $totalExpenses += $settlement->expense_amr_powder_claim;
            }

            // AMR Liquid Claim
            if ($settlement->expense_amr_liquid_claim > 0) {
                $lines[] = [
                    'account_id' => $accounts['amr_liquid']->id,
                    'debit' => $settlement->expense_amr_liquid_claim,
                    'credit' => 0,
                    'description' => "AMR liquid claim - {$employeeName}",
                    'cost_center_id' => 4,
                ];
                $totalExpenses += $settlement->expense_amr_liquid_claim;
            }

            // Scheme Discount
            if ($settlement->expense_scheme > 0) {
                $lines[] = [
                    'account_id' => $accounts['scheme']->id,
                    'debit' => $settlement->expense_scheme,
                    'credit' => 0,
                    'description' => "Scheme discount - {$employeeName}",
                    'cost_center_id' => 4,
                ];
                $totalExpenses += $settlement->expense_scheme;
            }

            // Advance Tax
            if ($settlement->expense_advance_tax > 0) {
                $lines[] = [
                    'account_id' => $accounts['advance_tax']->id,
                    'debit' => $settlement->expense_advance_tax,
                    'credit' => 0,
                    'description' => "Advance tax - {$employeeName}",
                    'cost_center_id' => 4,
                ];
                $totalExpenses += $settlement->expense_advance_tax;
            }

            // Food/Salesman/Loader Charges (combined)
            $foodAndLabor = $settlement->expense_food_charges +
                $settlement->expense_salesman_charges +
                $settlement->expense_loader_charges;

            if ($foodAndLabor > 0) {
                $lines[] = [
                    'account_id' => $accounts['food_salesman_loader']->id,
                    'debit' => $foodAndLabor,
                    'credit' => 0,
                    'description' => "Food/Salesman/Loader charges - {$employeeName}",
                    'cost_center_id' => 4,
                ];
                $totalExpenses += $foodAndLabor;
            }

            // Percentage Expense
            if ($settlement->expense_percentage > 0) {
                $lines[] = [
                    'account_id' => $accounts['percentage']->id,
                    'debit' => $settlement->expense_percentage,
                    'credit' => 0,
                    'description' => "Percentage expense - {$employeeName}",
                    'cost_center_id' => 4,
                ];
                $totalExpenses += $settlement->expense_percentage;
            }

            // Miscellaneous Expense
            if ($settlement->expense_miscellaneous_amount > 0) {
                $lines[] = [
                    'account_id' => $accounts['misc_expense']->id,
                    'debit' => $settlement->expense_miscellaneous_amount,
                    'credit' => 0,
                    'description' => "Miscellaneous expense - {$employeeName}",
                    'cost_center_id' => 4,
                ];
                $totalExpenses += $settlement->expense_miscellaneous_amount;
            }

            // Credit: Cash in Hand (for all expenses paid)
            if ($totalExpenses > 0) {
                $lines[] = [
                    'account_id' => $accounts['cash']->id,
                    'debit' => 0,
                    'credit' => $totalExpenses,
                    'description' => 'Expenses paid by salesman',
                    'cost_center_id' => 4,
                ];
            }

            // ====================================================================
            // 6. CREDIT RECOVERIES (AR collections by salesman)
            // ====================================================================

            if ($settlement->credit_recoveries > 0) {
                // Debit: Cash in Hand
                $lines[] = [
                    'account_id' => $accounts['cash']->id,
                    'debit' => $settlement->credit_recoveries,
                    'credit' => 0,
                    'description' => "Credit recoveries collected - {$employeeName}",
                    'cost_center_id' => 4,
                ];

                // Credit: Accounts Receivable
                $lines[] = [
                    'account_id' => $accounts['debtors']->id,
                    'debit' => 0,
                    'credit' => $settlement->credit_recoveries,
                    'description' => 'Customer payments received',
                    'cost_center_id' => 4,
                ];
            }

            // ====================================================================
            // 7. BANK DEPOSITS (if bank transfers made)
            // ====================================================================

            if ($settlement->bank_transfer_amount > 0 && $settlement->bank_transfers) {
                foreach ($settlement->bank_transfers as $transfer) {
                    $amount = floatval($transfer['amount'] ?? 0);
                    if ($amount > 0) {
                        $bankAccountId = $transfer['bank_account_id'] ?? null;
                        $bankAccount = $bankAccountId ?
                            \App\Models\ChartOfAccount::where('id', $bankAccountId)->first() :
                            null;

                        if ($bankAccount) {
                            // Debit: Specific Bank Account
                            $lines[] = [
                                'account_id' => $bankAccount->id,
                                'debit' => $amount,
                                'credit' => 0,
                                'description' => "Bank deposit - {$bankAccount->account_name}",
                                'cost_center_id' => 4,
                            ];

                            // Credit: Cash in Hand
                            $lines[] = [
                                'account_id' => $accounts['cash']->id,
                                'debit' => 0,
                                'credit' => $amount,
                                'description' => 'Cash deposited to bank',
                                'cost_center_id' => 4,
                            ];
                        }
                    }
                }
            }

            // Create journal entry
            $settlementDateFormatted = \Carbon\Carbon::parse($settlement->settlement_date)->format('d M Y');
            $journalEntryData = [
                'entry_date' => $settlement->settlement_date,
                'reference' => $settlement->settlement_number,
                'description' => "Sales settlement - {$employeeName} - {$settlementDateFormatted}",
                'reference_type' => 'App\Models\SalesSettlement',
                'reference_id' => $settlement->id,
                'lines' => $lines,
                'auto_post' => true,
            ];

            $accountingService = app(AccountingService::class);
            $result = $accountingService->createJournalEntry($journalEntryData);

            if ($result['success']) {
                Log::info('Sales settlement journal entry created', [
                    'settlement_id' => $settlement->id,
                    'settlement_number' => $settlement->settlement_number,
                    'journal_entry_id' => $result['data']->id,
                    'total_lines' => count($lines),
                ]);

                return $result['data'];
            }

            Log::error('Failed to create journal entry', [
                'settlement_id' => $settlement->id,
                'result' => $result,
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Exception creating sales journal entry', [
                'settlement_id' => $settlement->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get all required accounting accounts
     */
    protected function getAccountingAccounts(): array
    {
        return [
            'cash' => \App\Models\ChartOfAccount::where('account_code', '1131')->first(),
            'debtors' => \App\Models\ChartOfAccount::where('account_code', '1111')->first(),
            'earnest_money' => \App\Models\ChartOfAccount::where('account_code', '1151')->first(),
            'advance_tax' => \App\Models\ChartOfAccount::where('account_code', '1171')->first(),
            'inventory' => \App\Models\ChartOfAccount::where('account_code', '1161')->first(),
            'sales' => \App\Models\ChartOfAccount::where('account_code', '4110')->first(),
            'cogs' => \App\Models\ChartOfAccount::where('account_code', '5111')->first(),
            'toll_tax' => \App\Models\ChartOfAccount::where('account_code', '52250')->first(),
            'amr_powder' => \App\Models\ChartOfAccount::where('account_code', '52230')->first(),
            'amr_liquid' => \App\Models\ChartOfAccount::where('account_code', '52240')->first(),
            'scheme' => \App\Models\ChartOfAccount::where('account_code', '52270')->first(),
            'food_salesman_loader' => \App\Models\ChartOfAccount::where('account_code', '52260')->first(),
            'percentage' => \App\Models\ChartOfAccount::where('account_code', '52280')->first(),
            'misc_expense' => \App\Models\ChartOfAccount::where('account_code', '52110')->first(),
        ];
    }

    /**
     * Validate that required accounts exist
     */
    protected function validateRequiredAccounts(array $accounts): bool
    {
        $required = ['cash', 'debtors', 'inventory', 'sales', 'cogs'];
        foreach ($required as $key) {
            if (! isset($accounts[$key]) || ! $accounts[$key]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get list of missing accounts for logging
     */
    protected function getMissingAccounts(array $accounts): array
    {
        $missing = [];
        foreach ($accounts as $key => $account) {
            if (! $account) {
                $missing[] = $key;
            }
        }

        return $missing;
    }
}
