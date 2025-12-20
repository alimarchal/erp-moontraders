<?php

namespace App\Services;

use App\Models\ChartOfAccount;
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

            $totalIssueCost = 0.0;

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
                    $lineCost = (float) $qtyFromBatch * (float) $batch->unit_cost;
                    $totalIssueCost += $lineCost;

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

            // Round the total cost to 2 decimals for GL
            $totalIssueCost = round($totalIssueCost, 2);

            // Create GL transfer entry: Dr 1155 Van Stock, Cr 1151 Stock In Hand
            $journalEntry = $this->createGoodsIssueJournalEntry($goodsIssue, $totalIssueCost);

            // Update goods issue status
            $goodsIssue->update([
                'status' => 'issued',
                'posted_at' => now(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => "Goods Issue {$goodsIssue->issue_number} posted successfully with promotional priority and GL posted",
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
     * Create accounting journal entry for a posted goods issue.
     * Dr Van Stock (1155) / Cr Stock In Hand (1151) for the unit-cost total.
     */
    public function createGoodsIssueJournalEntry(GoodsIssue $goodsIssue, float $totalIssueCost)
    {
        try {
            if ($totalIssueCost <= 0) {
                Log::warning('Skipping goods issue JE with zero or negative cost', [
                    'goods_issue_id' => $goodsIssue->id,
                    'total_cost' => $totalIssueCost,
                ]);

                return null;
            }

            // Required accounts and context are stored per-record on goods_issues
            $goodsIssue->loadMissing(['stockInHandAccount', 'vanStockAccount', 'employee', 'vehicle', 'issuedBy']);
            $stockInHand = $goodsIssue->stockInHandAccount;
            $vanStock = $goodsIssue->vanStockAccount;

            // Fallback: resolve defaults by account_code if not already set on the record (keeps legacy data/tests working)
            if (! $stockInHand) {
                $stockInHand = ChartOfAccount::where('account_code', '1151')->first();
                if ($stockInHand) {
                    $goodsIssue->stock_in_hand_account_id = $stockInHand->id;
                    $goodsIssue->save();
                }
            }

            if (! $vanStock) {
                $vanStock = ChartOfAccount::where('account_code', '1155')->first();
                if ($vanStock) {
                    $goodsIssue->van_stock_account_id = $vanStock->id;
                    $goodsIssue->save();
                }
            }

            if (! $stockInHand || ! $vanStock) {
                Log::error('Goods Issue missing configured GL accounts', [
                    'goods_issue_id' => $goodsIssue->id,
                    'stock_in_hand_account_id' => $goodsIssue->stock_in_hand_account_id,
                    'van_stock_account_id' => $goodsIssue->van_stock_account_id,
                ]);

                throw new \Exception('Goods Issue is missing GL accounts. Please set Stock In Hand and Van Stock accounts.');
            }

            $costCenterId = optional($goodsIssue->employee)->cost_center_id;

            $employeeName = $goodsIssue->employee->name ?? 'N/A';
            $createdBy = $goodsIssue->issuedBy->name ?? 'System';
            $vehicleNumber = $goodsIssue->vehicle->vehicle_number ?? 'N/A';

            $lines = [
                [
                    'line_no' => 1,
                    'account_id' => $vanStock->id,
                    'debit' => $totalIssueCost,
                    'credit' => 0,
                    'description' => 'Transfer to van stock (vehicle '.$vehicleNumber.'; salesman '.$employeeName.')',
                    'cost_center_id' => $costCenterId,
                ],
                [
                    'line_no' => 2,
                    'account_id' => $stockInHand->id,
                    'debit' => 0,
                    'credit' => $totalIssueCost,
                    'description' => 'Transfer from warehouse stock (issued by '.$createdBy.')',
                    'cost_center_id' => $costCenterId,
                ],
            ];

            $journalEntryData = [
                'entry_date' => $goodsIssue->issue_date,
                'reference' => $goodsIssue->issue_number,
                'description' => 'Goods Issue #'.$goodsIssue->issue_number.' - Transfer to vehicle '.$vehicleNumber.' (Salesman: '.$employeeName.'; Created by: '.$createdBy.')',
                'lines' => $lines,
                'auto_post' => true,
            ];

            $accountingService = app(AccountingService::class);
            $result = $accountingService->createJournalEntry($journalEntryData);

            if (! $result['success']) {
                throw new \Exception($result['message'] ?? 'Failed to create journal entry for goods issue');
            }

            Log::info('Goods Issue JE created', [
                'goods_issue_id' => $goodsIssue->id,
                'journal_entry_id' => $result['data']->id ?? null,
                'amount' => $totalIssueCost,
            ]);

            return $result['data'];
        } catch (\Exception $e) {
            Log::error('Error creating Goods Issue JE: '.$e->getMessage(), [
                'goods_issue_id' => $goodsIssue->id ?? null,
            ]);

            // Re-throw so outer transaction can roll back
            throw $e;
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

                // Check if batch-level quantities are populated
                $batchSoldTotal = $item->batches->sum('quantity_sold');
                $batchReturnedTotal = $item->batches->sum('quantity_returned');
                $batchShortageTotal = $item->batches->sum('quantity_shortage');

                // If batch totals are zero but item totals are non-zero, distribute to first batch
                $useFallback = ($batchSoldTotal == 0 && $batchReturnedTotal == 0 && $batchShortageTotal == 0)
                    && ($item->quantity_sold > 0 || $item->quantity_returned > 0 || $item->quantity_shortage > 0);

                // Process using ACTUAL BATCH DATA from sales_settlement_item_batches
                $isFirstBatch = true;
                foreach ($item->batches as $itemBatch) {
                    $batch = $itemBatch->stockBatch;
                    if (! $batch) {
                        continue;
                    }

                    // Determine quantities to use (fallback to item-level for first batch if batch-level is empty)
                    $soldQty = $useFallback && $isFirstBatch ? (float) $item->quantity_sold : (float) $itemBatch->quantity_sold;
                    $returnedQty = $useFallback && $isFirstBatch ? (float) $item->quantity_returned : (float) $itemBatch->quantity_returned;
                    $shortageQty = $useFallback && $isFirstBatch ? (float) $item->quantity_shortage : (float) $itemBatch->quantity_shortage;

                    // Update batch record if using fallback values
                    if ($useFallback && $isFirstBatch) {
                        $itemBatch->update([
                            'quantity_sold' => $soldQty,
                            'quantity_returned' => $returnedQty,
                            'quantity_shortage' => $shortageQty,
                        ]);
                    }

                    $isFirstBatch = false;

                    // 1. SALES: Create stock movement for sold quantities from this batch
                    if ($soldQty > 0) {
                        StockMovement::create([
                            'movement_type' => 'sale',
                            'reference_type' => 'App\Models\SalesSettlement',
                            'reference_id' => $settlement->id,
                            'movement_date' => $settlement->settlement_date,
                            'product_id' => $item->product_id,
                            'stock_batch_id' => $batch->id,
                            'warehouse_id' => $settlement->warehouse_id,
                            'quantity' => -$soldQty,
                            'uom_id' => $uomId,
                            'unit_cost' => $itemBatch->unit_cost,
                            'total_value' => $soldQty * $itemBatch->unit_cost,
                            'created_by' => auth()->id() ?? 1,
                        ]);
                    }

                    // 2. RETURNS: Go back to SAME batch (not random allocation)
                    if ($returnedQty > 0) {
                        StockMovement::create([
                            'movement_type' => 'return',
                            'reference_type' => 'App\Models\SalesSettlement',
                            'reference_id' => $settlement->id,
                            'movement_date' => $settlement->settlement_date,
                            'product_id' => $item->product_id,
                            'stock_batch_id' => $batch->id,
                            'warehouse_id' => $settlement->warehouse_id,
                            'quantity' => $returnedQty, // Positive for return
                            'uom_id' => $uomId,
                            'unit_cost' => $itemBatch->unit_cost,
                            'total_value' => $returnedQty * $itemBatch->unit_cost,
                            'created_by' => auth()->id() ?? 1,
                        ]);

                        // Update current_stock_by_batch - returns go to SAME batch
                        $stockByBatch = CurrentStockByBatch::where('stock_batch_id', $batch->id)
                            ->where('warehouse_id', $settlement->warehouse_id)
                            ->first();

                        if ($stockByBatch) {
                            $stockByBatch->quantity_on_hand += $returnedQty;
                            $stockByBatch->status = 'active';
                            $stockByBatch->last_updated = now();
                            $stockByBatch->save();
                        } else {
                            // Create new stock by batch record if not exists
                            CurrentStockByBatch::create([
                                'stock_batch_id' => $batch->id,
                                'product_id' => $item->product_id,
                                'warehouse_id' => $settlement->warehouse_id,
                                'quantity_on_hand' => $returnedQty,
                                'status' => 'active',
                                'last_updated' => now(),
                            ]);
                        }

                        // Update stock valuation layer for returns
                        $valuationLayer = StockValuationLayer::where('stock_batch_id', $batch->id)
                            ->where('warehouse_id', $settlement->warehouse_id)
                            ->first();

                        if ($valuationLayer) {
                            $valuationLayer->quantity_remaining += $returnedQty;
                            $valuationLayer->save();
                        }
                    }

                    // 3. SHORTAGES: Record as loss from SAME batch
                    if ($shortageQty > 0) {
                        StockMovement::create([
                            'movement_type' => 'shortage', // New type for clarity
                            'reference_type' => 'App\Models\SalesSettlement',
                            'reference_id' => $settlement->id,
                            'movement_date' => $settlement->settlement_date,
                            'product_id' => $item->product_id,
                            'stock_batch_id' => $batch->id,
                            'warehouse_id' => $settlement->warehouse_id,
                            'quantity' => -$shortageQty, // Negative for loss
                            'uom_id' => $uomId,
                            'unit_cost' => $itemBatch->unit_cost,
                            'total_value' => $shortageQty * $itemBatch->unit_cost,
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
            $settlement->load([
                'employee',
                'items',
                'recoveries.customer',
                'cheques',
                'bankTransfers.bankAccount.chartOfAccount',
                'expenses.expenseAccount',
                'advanceTaxes',
            ]);

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
            $settlementDateFormatted = \Carbon\Carbon::parse($settlement->settlement_date)->format('d M Y');

            // ====================================================================
            // 1. RECOVERIES (Payments against old balances)
            // ====================================================================
            $totalCashRecoveries = 0;
            $empId = $settlement->employee_id;

            foreach ($settlement->recoveries as $recovery) {
                if ($recovery->amount > 0) {
                    $customerName = $recovery->customer->customer_name ?? 'Unknown';
                    $methodLabel = $recovery->payment_method === 'cash' ? 'Cash' : 'Bank Transfer';
                    $recoveryDesc = "Recovery from {$customerName} via {$methodLabel} - Ref: {$recovery->recovery_number}";

                    // Credit: Debtors (Always credit debtors for recovery)
                    $lines[] = [
                        'account_id' => $accounts['debtors']->id,
                        'debit' => 0,
                        'credit' => $recovery->amount,
                        'description' => $recoveryDesc,
                        'cost_center_id' => 4,
                    ];

                    if ($recovery->payment_method === 'cash') {
                        // For cash, we consolidate in Step 4
                        $totalCashRecoveries += $recovery->amount;
                    } else {
                        // For bank transfer, we debit the specific bank account immediately
                        $bankAccount = \App\Models\BankAccount::with('chartOfAccount')->find($recovery->bank_account_id);
                        $bankAccountId = $bankAccount?->chart_of_account_id;

                        if ($bankAccountId) {
                            $lines[] = [
                                'account_id' => $bankAccountId,
                                'debit' => $recovery->amount,
                                'credit' => 0,
                                'description' => $recoveryDesc,
                                'cost_center_id' => 4,
                            ];
                        } else {
                            // Fallback to a default bank account if not found
                            $fallbackBank = \App\Models\ChartOfAccount::where('account_code', '1171')->first();
                            $lines[] = [
                                'account_id' => $fallbackBank ? $fallbackBank->id : $accounts['cash']->id,
                                'debit' => $recovery->amount,
                                'credit' => 0,
                                'description' => $recoveryDesc . " (Fallback Account)",
                                'cost_center_id' => 4,
                            ];
                        }
                    }
                }
            }

            // ====================================================================
            // 3. CHEQUE PAYMENTS (Recovery)
            // ====================================================================
            $chequeTotalsByBank = [];
            $chequeDetails = [];
            $totalChequesAmount = 0;
            $empId = $settlement->employee_id;

            foreach ($settlement->cheques as $cheque) {
                if ($cheque->amount > 0) {
                    $bankAccountId = null;
                    if ($cheque->bank_account_id) {
                        $bankAccount = \App\Models\BankAccount::with('chartOfAccount')->find($cheque->bank_account_id);
                        $bankAccountId = $bankAccount?->chart_of_account_id;
                    }

                    if (! $bankAccountId) {
                        $fallbackAccount = \App\Models\ChartOfAccount::where('account_code', '1171')->first();
                        $bankAccountId = $fallbackAccount ? $fallbackAccount->id : $accounts['cash']->id;
                    }

                    $chequeTotalsByBank[$bankAccountId] = ($chequeTotalsByBank[$bankAccountId] ?? 0) + $cheque->amount;
                    $totalChequesAmount += $cheque->amount;

                    $amountFormatted = number_format($cheque->amount, 0, '.', '');
                    $chequeDetails[] = "{$empId}-{$cheque->customer_id}-{$amountFormatted}";
                }
            }

            if ($totalChequesAmount > 0) {
                $chequeDesc = 'Cheque Recoveries: '.implode(', ', $chequeDetails);
                if (strlen($chequeDesc) > 250) {
                    $chequeDesc = '';
                    $count = 0;
                    foreach ($chequeDetails as $detail) {
                        $tempDesc = $chequeDesc.($chequeDesc ? ', ' : 'Cheque Recoveries: ').$detail;
                        if (strlen($tempDesc) > 240) {
                            $remaining = count($chequeDetails) - $count;
                            $chequeDesc .= " + $remaining more";
                            break;
                        }
                        $chequeDesc = $tempDesc;
                        $count++;
                    }
                }

                // Debit: Each Bank Account
                foreach ($chequeTotalsByBank as $bankAccountId => $amount) {
                    $lines[] = [
                        'account_id' => $bankAccountId,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => $chequeDesc,
                        'cost_center_id' => 4,
                    ];
                }

                // Credit: Debtors (Total for all cheques)
                $lines[] = [
                    'account_id' => $accounts['debtors']->id,
                    'debit' => 0,
                    'credit' => $totalChequesAmount,
                    'description' => $chequeDesc,
                    'cost_center_id' => 4,
                ];
            }

            // ====================================================================
            // 3. BANK TRANSFERS (Recovery)
            // ====================================================================
            foreach ($settlement->bankTransfers as $transfer) {
                if ($transfer->amount > 0 && $transfer->bank_account_id) {
                    $bankAccount = \App\Models\BankAccount::with('chartOfAccount')->find($transfer->bank_account_id);
                    $bankAccountId = $bankAccount?->chart_of_account_id;
                    $bankName = $bankAccount?->bank_name ?? 'Bank';

                    if ($bankAccountId) {
                        // Debit: Specific Bank Account
                        $lines[] = [
                            'account_id' => $bankAccountId,
                            'debit' => $transfer->amount,
                            'credit' => 0,
                            'description' => "Bank Transfer Received - {$bankName} - Ref: {$transfer->reference_number}",
                            'cost_center_id' => 4,
                        ];

                        // Credit: Debtors
                        $lines[] = [
                            'account_id' => $accounts['debtors']->id,
                            'debit' => 0,
                            'credit' => $transfer->amount,
                            'description' => 'Bank Transfer Payment - Customer Debt Reduced',
                            'cost_center_id' => 4,
                        ];
                    }
                }
            }

            // ====================================================================
            // 4. CASH CONSOLIDATION (Sales & Recoveries)
            // ====================================================================
            $cashDebit = 0;
            $cashCredit = 0;

            // 4a. Cash Recoveries (From Step 2)
            if ($totalCashRecoveries > 0) {
                $cashDebit += $totalCashRecoveries;
            }

            // 4b. Cash Sales Revenue
            $totalSalesRevenue = $settlement->items->sum('total_sales_value');
            $cashSaleRevenue = $totalSalesRevenue
                - ($settlement->credit_sales_amount ?? 0)
                - ($settlement->cheque_sales_amount ?? 0)
                - ($settlement->bank_transfer_amount ?? 0);

            if ($cashSaleRevenue > 0) {
                $cashDebit += $cashSaleRevenue;
                // Credit: Sales Revenue
                $lines[] = [
                    'account_id' => $accounts['sales']->id,
                    'debit' => 0,
                    'credit' => $cashSaleRevenue,
                    'description' => "Cash Sales Revenue - {$employeeName}",
                    'cost_center_id' => 4,
                ];
            }

            // 4c. Expenses (Credit Cash)
            foreach ($settlement->expenses as $expense) {
                if ($expense->amount > 0 && $expense->expense_account_id) {
                    $cashCredit += $expense->amount;
                    $expenseAccountName = $expense->expenseAccount->account_name ?? 'Expense';
                    // Debit: Expense Account
                    $lines[] = [
                        'account_id' => $expense->expense_account_id,
                        'debit' => $expense->amount,
                        'credit' => 0,
                        'description' => "Expense: {$expenseAccountName} - {$expense->description}",
                        'cost_center_id' => 4,
                    ];
                }
            }

            // 4d. Advance Tax (Credit Cash)
            foreach ($settlement->advanceTaxes as $advanceTax) {
                if ($advanceTax->amount > 0) {
                    $cashCredit += $advanceTax->amount;
                    // Debit: Advance Tax
                    $lines[] = [
                        'account_id' => $accounts['advance_tax']->id,
                        'debit' => $advanceTax->amount,
                        'credit' => 0,
                        'description' => "Advance Tax Collected - {$settlement->settlement_number}",
                        'cost_center_id' => 4,
                    ];
                }
            }

            // Add Consolidated Cash Line
            if ($cashDebit > $cashCredit) {
                $lines[] = [
                    'account_id' => $accounts['cash']->id,
                    'debit' => $cashDebit - $cashCredit,
                    'credit' => 0,
                    'description' => "Net Cash Collected - {$employeeName}",
                    'cost_center_id' => 4,
                ];
            } elseif ($cashCredit > $cashDebit) {
                $lines[] = [
                    'account_id' => $accounts['cash']->id,
                    'debit' => 0,
                    'credit' => $cashCredit - $cashDebit,
                    'description' => "Net Cash Paid/Short - {$employeeName}",
                    'cost_center_id' => 4,
                ];
            }

            // ====================================================================
            // 5. COST OF GOODS SOLD
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
            // 6. RETURNS TO WAREHOUSE
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
            // 7. SHORTAGES / INVENTORY LOSS
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

            // Create journal entry
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
                Log::info('Consolidated sales settlement journal entry created', [
                    'settlement_id' => $settlement->id,
                    'settlement_number' => $settlement->settlement_number,
                    'journal_entry_id' => $result['data']->id,
                    'total_lines' => count($lines),
                ]);

                return $result['data'];
            }

            Log::error('Failed to create consolidated journal entry', [
                'settlement_id' => $settlement->id,
                'result' => $result,
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Exception creating consolidated sales journal entry', [
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
            'cash' => \App\Models\ChartOfAccount::where('account_code', '1121')->first(),
            'debtors' => \App\Models\ChartOfAccount::where('account_code', '1111')->first(),
            'earnest_money' => \App\Models\ChartOfAccount::where('account_code', '1170')->first(),
            'advance_tax' => \App\Models\ChartOfAccount::where('account_code', '1161')->first(),
            'inventory' => \App\Models\ChartOfAccount::where('account_code', '1155')->first(),
            'sales' => \App\Models\ChartOfAccount::where('account_code', '4110')->first(),
            'cogs' => \App\Models\ChartOfAccount::where('account_code', '5111')->first(),
            'toll_tax' => \App\Models\ChartOfAccount::where('account_code', '5272')->first(),
            'amr_powder' => \App\Models\ChartOfAccount::where('account_code', '5252')->first(),
            'amr_liquid' => \App\Models\ChartOfAccount::where('account_code', '5262')->first(),
            'scheme' => \App\Models\ChartOfAccount::where('account_code', '5292')->first(),
            'food_salesman_loader' => \App\Models\ChartOfAccount::where('account_code', '5282')->first(),
            'percentage' => \App\Models\ChartOfAccount::where('account_code', '5292')->first(),
            'misc_expense' => \App\Models\ChartOfAccount::where('account_code', '5211')->first(),
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
