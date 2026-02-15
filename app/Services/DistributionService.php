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
use App\Models\VanStockBatch;
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
            $totalIssueValue = 0.0;

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
                    $effectiveSellingPrice = $batch->is_promotional
                        ? ((float) ($batch->promotional_selling_price ?? $batch->selling_price))
                        : (float) $batch->selling_price;

                    $totalIssueCost += $lineCost;
                    $totalIssueValue += $qtyFromBatch * $effectiveSellingPrice;

                    // Create stock movement - OUT from warehouse
                    StockMovement::create([
                        'movement_type' => 'transfer',
                        'reference_type' => 'App\Models\GoodsIssue',
                        'reference_id' => $goodsIssue->id,
                        'movement_date' => $goodsIssue->issue_date,
                        'product_id' => $item->product_id,
                        'stock_batch_id' => $batch->id,
                        'warehouse_id' => $goodsIssue->warehouse_id,
                        'vehicle_id' => $goodsIssue->vehicle_id,
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

                    // Record inventory ledger entries for this specific batch (double-entry)
                    $ledgerService = app(InventoryLedgerService::class);
                    $ledgerService->recordIssue(
                        $item->product_id,
                        $goodsIssue->warehouse_id,
                        $goodsIssue->vehicle_id,
                        $goodsIssue->employee_id,
                        $qtyFromBatch,
                        $batch->unit_cost,
                        $goodsIssue->id,
                        $goodsIssue->issue_date,
                        "GI {$goodsIssue->issue_number} - Batch {$batch->batch_code}",
                        $batch->id  // Pass batch ID for traceability
                    );
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
                // dump($batchAllocations);
                foreach ($batchAllocations['batches'] as $batchAllocation) {
                    // dump('Allocating batch: ' . $batchAllocation['batch']->batch_code . ' Qty: ' . $batchAllocation['quantity']);
                    $totalValue += $batchAllocation['quantity'] * $batchAllocation['batch']->unit_cost;
                }
                $vanStock->average_cost = $item->quantity_issued > 0 ? $totalValue / $item->quantity_issued : 0;

                // Update latest issue details
                $vanStock->last_issue_number = $goodsIssue->issue_number;
                $vanStock->last_unit_cost = $item->unit_cost;
                $vanStock->last_selling_price = $item->selling_price;

                $vanStock->last_updated = now();
                $vanStock->save();

                // Create granular VanStockBatch record
                \App\Models\VanStockBatch::create([
                    'vehicle_id' => $goodsIssue->vehicle_id,
                    'product_id' => $item->product_id,
                    'goods_issue_item_id' => $item->id,
                    'goods_issue_number' => $goodsIssue->issue_number,
                    'quantity_on_hand' => $item->quantity_issued,
                    'unit_cost' => $item->unit_cost,
                    'selling_price' => $item->selling_price,
                ]);
                // Note: Ledger recording is now done at batch level inside the batch loop above
            }

            // Round totals to 2 decimals for GL
            $totalIssueCost = round($totalIssueCost, 2);
            $totalIssueValue = round($totalIssueValue, 2);

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
     * Dr Van Stock (1155) / Cr Stock In Hand (1151) for the cost total.
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
                            'vehicle_id' => $settlement->vehicle_id,
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
                            'vehicle_id' => $settlement->vehicle_id,
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
                            'vehicle_id' => $settlement->vehicle_id,
                            'quantity' => -$shortageQty, // Negative for loss
                            'uom_id' => $uomId,
                            'unit_cost' => $itemBatch->unit_cost,
                            'total_value' => $shortageQty * $itemBatch->unit_cost,
                            'created_by' => auth()->id() ?? 1,
                        ]);
                    }

                    // Record inventory ledger entries at batch level (double-entry)
                    $inventoryLedgerService = app(InventoryLedgerService::class);

                    // Record sale for this batch
                    if ($soldQty > 0) {
                        $inventoryLedgerService->recordSale(
                            $item->product_id,
                            $settlement->vehicle_id,
                            $settlement->employee_id,
                            $soldQty,
                            $itemBatch->unit_cost,
                            $settlement->id,
                            $settlement->settlement_date,
                            "Sale - SS {$settlement->settlement_number} - Batch {$batch->batch_code}",
                            $batch->id
                        );
                    }

                    // Record return for this batch (double-entry: out from vehicle, in to warehouse)
                    if ($returnedQty > 0) {
                        $inventoryLedgerService->recordReturn(
                            $item->product_id,
                            $settlement->warehouse_id,
                            $settlement->vehicle_id,
                            $settlement->employee_id,
                            $returnedQty,
                            $itemBatch->unit_cost,
                            $settlement->id,
                            $settlement->settlement_date,
                            "Return - SS {$settlement->settlement_number} - Batch {$batch->batch_code}",
                            $batch->id
                        );
                    }

                    // Record shortage for this batch
                    if ($shortageQty > 0) {
                        $inventoryLedgerService->recordShortage(
                            $item->product_id,
                            $settlement->vehicle_id,
                            $settlement->employee_id,
                            $shortageQty,
                            $itemBatch->unit_cost,
                            $settlement->id,
                            $settlement->settlement_date,
                            "Shortage - SS {$settlement->settlement_number} - Batch {$batch->batch_code}",
                            $batch->id
                        );
                    }
                }

                // Update van stock - subtract all quantities (sold + returned + shortage)
                $vanStock->quantity_on_hand -= $totalToReduce;
                $vanStock->last_updated = now();
                $vanStock->save();

                // Reduce VanStockBatch (FIFO)
                $qtyToReduceFromBatch = $totalToReduce;
                $vanStockBatches = VanStockBatch::where('vehicle_id', $settlement->vehicle_id)
                    ->where('product_id', $item->product_id)
                    ->where('quantity_on_hand', '>', 0)
                    ->orderBy('created_at')
                    ->get();

                foreach ($vanStockBatches as $vsBatch) {
                    if ($qtyToReduceFromBatch <= 0) {
                        break;
                    }

                    $deduct = min($vsBatch->quantity_on_hand, $qtyToReduceFromBatch);
                    $vsBatch->quantity_on_hand -= $deduct;
                    $vsBatch->save();

                    $qtyToReduceFromBatch -= $deduct;
                }

                // Sync warehouse CurrentStock from valuation layers for returns/shortages
                if ($item->quantity_returned > 0 || $item->quantity_shortage > 0) {
                    $this->syncCurrentStockFromValuationLayers($item->product_id, $settlement->warehouse_id);
                }

                // Calculate gross profit for this item
                $itemGrossProfit = $item->total_sales_value - $item->total_cogs;
                $totalGrossProfit += $itemGrossProfit;
                // Note: Ledger recording is now done at batch level inside the batch loop above
            }

            // Calculate total COGS
            $totalCOGS = $settlement->items->sum('total_cogs');

            // Create accounting journal entry
            $journalEntry = $this->createSalesJournalEntry($settlement);
            if (! $journalEntry) {
                throw new \Exception('Failed to create journal entry for Sales Settlement.');
            }

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

    /**
     * Create consolidated journal entries for a sales settlement.
     */
    protected function createSalesJournalEntry(SalesSettlement $settlement)
    {
        try {
            $settlement->load([
                'employee',
                'items',
                'items.batches',
                'recoveries.customer',
                'recoveries.bankAccount.chartOfAccount',
                'creditSales.customer',
                'cheques.bankAccount.chartOfAccount',
                'bankTransfers.bankAccount.chartOfAccount',
                'expenses.expenseAccount',
                'advanceTaxes',
                'bankSlips.bankAccount.chartOfAccount',
            ]);

            $accounts = $this->getAccountingAccounts();

            if (! $this->validateRequiredAccounts($accounts)) {
                Log::warning('Required accounts not found for sales journal entry', [
                    'settlement_id' => $settlement->id,
                    'missing_accounts' => $this->getMissingAccounts($accounts),
                ]);

                return null;
            }

            $lines = [];
            $addLine = function (int $accountId, float $debit, float $credit, string $description, int $costCenterId = 4) use (&$lines): void {
                $debit = round($debit, 2);
                $credit = round($credit, 2);

                if ($debit <= 0 && $credit <= 0) {
                    return;
                }

                $lines[] = [
                    'account_id' => $accountId,
                    'debit' => $debit,
                    'credit' => $credit,
                    'description' => $description,
                    'cost_center_id' => $costCenterId,
                ];
            };

            $employeeName = $settlement->employee->name ?? 'Unknown';
            $employeeLabel = $settlement->employee_id
                ? "{$employeeName} (ID: {$settlement->employee_id})"
                : $employeeName;
            $settlementDateFormatted = \Carbon\Carbon::parse($settlement->settlement_date)->format('d M Y');
            $settlementReference = $settlement->settlement_number;

            // 1. Credit Sales
            foreach ($settlement->creditSales as $creditSale) {
                $creditAmount = (float) $creditSale->sale_amount;
                if ($creditAmount <= 0) {
                    continue;
                }

                $customerName = $creditSale->customer?->customer_name ?? 'Customer';
                $invoiceNumber = $creditSale->invoice_number ? "Inv: {$creditSale->invoice_number}" : 'Inv: N/A';
                $creditDescription = "Credit Sales - {$customerName} - {$invoiceNumber} - {$employeeLabel} - {$settlementReference}";

                $addLine(
                    $accounts['debtors']->id,
                    $creditAmount,
                    0,
                    $creditDescription
                );
                $addLine(
                    $accounts['sales']->id,
                    0,
                    $creditAmount,
                    $creditDescription
                );
            }

            // 2. Recoveries Detail
            // Recoveries - cash (using salesman_clearing)
            $totalCashRecoveries = $settlement->recoveries
                ->where('payment_method', 'cash')
                ->sum('amount');

            if ($totalCashRecoveries > 0) {
                $addLine(
                    $accounts['salesman_clearing']->id,
                    $totalCashRecoveries,
                    0,
                    "Cash Recovery from Debtor - {$employeeLabel} - {$settlementReference} ({$settlementDateFormatted})"
                );
                $addLine(
                    $accounts['debtors']->id,
                    0,
                    $totalCashRecoveries,
                    "Cash Recovery from Debtor - {$employeeLabel} - {$settlementReference} ({$settlementDateFormatted})"
                );
            }

            // Recoveries - bank/online
            $settlement->recoveries
                ->reject(fn ($recovery) => $recovery->payment_method === 'cash')
                ->groupBy('bank_account_id')
                ->each(function ($group) use (&$addLine, $accounts, $employeeLabel, $settlementReference, $settlementDateFormatted): void {
                    $amount = $group->sum('amount');
                    $bankAccount = $group->first()->bankAccount;
                    $bankAccountId = $bankAccount?->chart_of_account_id ?? $accounts['bank_fallback']->id;
                    $bankName = $bankAccount?->bank_name ?? 'Bank (Unallocated)';

                    $addLine(
                        $bankAccountId,
                        $amount,
                        0,
                        "Bank Recovery from Debtor - {$bankName} - {$employeeLabel} - {$settlementReference} ({$settlementDateFormatted})"
                    );
                    $addLine(
                        $accounts['debtors']->id,
                        0,
                        $amount,
                        "Bank Recovery from Debtor - {$bankName} - {$employeeLabel} - {$settlementReference} ({$settlementDateFormatted})"
                    );
                });

            // 3. Cheque Payments (Recoveries and Sales)
            // Cheque recoveries
            $totalChequeRecoveries = $settlement->cheques->sum('amount');
            if ($totalChequeRecoveries > 0) {
                $addLine(
                    $accounts['cheques_in_hand']->id,
                    $totalChequeRecoveries,
                    0,
                    "Cheque Recovery from Debtor - {$employeeLabel} - {$settlementReference}"
                );
                $addLine(
                    $accounts['debtors']->id,
                    0,
                    $totalChequeRecoveries,
                    "Cheque Recovery from Debtor - {$employeeLabel} - {$settlementReference}"
                );
            }

            // Cheque sales
            $chequeSalesAmount = $settlement->cheque_sales_amount ?? 0;
            if ($chequeSalesAmount > 0) {
                $addLine(
                    $accounts['cheques_in_hand']->id,
                    $chequeSalesAmount,
                    0,
                    "Cheque Sales (Direct) - {$employeeLabel} - {$settlementReference}"
                );
                $addLine(
                    $accounts['sales']->id,
                    0,
                    $chequeSalesAmount,
                    "Cheque Sales (Direct) - {$employeeLabel} - {$settlementReference}"
                );
            }

            // 4. Bank Transfers
            $settlement->bankTransfers
                ->groupBy('bank_account_id')
                ->each(function ($transfers) use (&$addLine, $accounts, $employeeLabel, $settlementReference): void {
                    $amount = $transfers->sum('amount');
                    $bankAccount = $transfers->first()->bankAccount;
                    $bankAccountId = $bankAccount?->chart_of_account_id ?? $accounts['bank_fallback']->id;
                    $bankName = $bankAccount?->bank_name ?? 'Bank (Unallocated)';

                    $addLine(
                        $bankAccountId,
                        $amount,
                        0,
                        "Bank Transfer Sales - {$bankName} - {$employeeLabel} - {$settlementReference}"
                    );
                    $addLine(
                        $accounts['sales']->id,
                        0,
                        $amount,
                        "Bank Transfer Sales - {$bankName} - {$employeeLabel} - {$settlementReference}"
                    );
                });

            // 5. Cash Sales (Gross)
            $cashSalesAmount = $settlement->cash_sales_amount ?? 0;
            if ($cashSalesAmount > 0) {
                $addLine(
                    $accounts['salesman_clearing']->id,
                    $cashSalesAmount,
                    0,
                    "Gross Cash Sales (Total cash invoices) - {$employeeLabel} - {$settlementReference}"
                );
                $addLine(
                    $accounts['sales']->id,
                    0,
                    $cashSalesAmount,
                    "Gross Cash Sales (Total cash invoices) - {$employeeLabel} - {$settlementReference}"
                );
            }

            // 6. Expense Detail
            // Expenses paid from cash (using salesman_clearing)
            // We skip Advance Tax here because it's handled separately below via advanceTaxes relationship
            $advanceTaxAccountId = $accounts['advance_tax']?->id;
            foreach ($settlement->expenses as $expense) {
                if ($expense->amount > 0 && $expense->expense_account_id && $expense->expense_account_id !== $advanceTaxAccountId) {
                    $expenseAccountName = $expense->expenseAccount->account_name ?? 'Expense';
                    $addLine(
                        $expense->expense_account_id,
                        $expense->amount,
                        0,
                        "Expense: {$expenseAccountName} (Paid from salesman cash) - {$employeeLabel} - {$settlementReference}"
                    );
                    $addLine(
                        $accounts['salesman_clearing']->id,
                        0,
                        $expense->amount,
                        "Expense: {$expenseAccountName} (Paid from salesman cash) - {$employeeLabel} - {$settlementReference}"
                    );
                }
            }

            // Advance tax collected (using salesman_clearing)
            foreach ($settlement->advanceTaxes as $advanceTax) {
                if ($advanceTax->tax_amount > 0) {
                    $customerName = $advanceTax->customer->customer_name ?? 'Customer';
                    $addLine(
                        $accounts['advance_tax']->id,
                        $advanceTax->tax_amount,
                        0,
                        "Advance Tax Collected from {$customerName} - {$employeeLabel} - {$settlementReference}"
                    );
                    $addLine(
                        $accounts['salesman_clearing']->id,
                        0,
                        $advanceTax->tax_amount,
                        "Advance Tax Collected from {$customerName} - {$employeeLabel} - {$settlementReference}"
                    );
                }
            }

            // 7. Cash Shortage / Excess Adjustment
            // This ensures the Salesman Clearing Account (1123) matches the actual physical cash submitted
            // Note: settlement->expenses->sum('amount') already includes Advance Tax and AMR from the UI
            $expectedClearingBalance = $cashSalesAmount + $totalCashRecoveries - $settlement->expenses->sum('amount');
            $actualPhysicalCash = (float) $settlement->cash_collected;
            $bankSlipsTotal = $settlement->bankSlips->sum('amount');

            // Total handed over by salesman = Physical Cash + Bank Slips (deposited directly)
            $totalSubmitted = $actualPhysicalCash + $bankSlipsTotal;

            $cashDifference = round($totalSubmitted - $expectedClearingBalance, 2);

            if ($cashDifference < 0) {
                // Shortage: Dr Write Off / Expense, Cr Salesman Clearing
                $addLine(
                    $accounts['misc_expense']->id,
                    abs($cashDifference),
                    0,
                    "Cash Shortage (Expected: {$expectedClearingBalance}, Actual: {$totalSubmitted}) - {$employeeLabel} - {$settlementReference}"
                );
                $addLine(
                    $accounts['salesman_clearing']->id,
                    0,
                    abs($cashDifference),
                    "Cash Shortage Adjustment - {$employeeLabel} - {$settlementReference}"
                );
            } elseif ($cashDifference > 0) {
                // Excess: Dr Salesman Clearing, Cr Misc Income (using Write Off as fallback or 4xxx)
                $addLine(
                    $accounts['salesman_clearing']->id,
                    $cashDifference,
                    0,
                    "Cash Excess (Expected: {$expectedClearingBalance}, Actual: {$totalSubmitted}) - {$employeeLabel} - {$settlementReference}"
                );
                $addLine(
                    $accounts['misc_expense']->id, // Ideally a Misc Income account
                    0,
                    $cashDifference,
                    "Cash Excess Adjustment - {$employeeLabel} - {$settlementReference}"
                );
            }

            // 8. Final Cash Deposit (Transfer from Clearing to Main Cash)
            // After adjustments, the balance in 1123 is exactly the physical cash collected.
            // We now move it to the main Cash Account (1121).
            if ($actualPhysicalCash > 0) {
                $addLine(
                    $accounts['cash']->id,
                    $actualPhysicalCash,
                    0,
                    "Final Cash Deposit (Physical cash submitted) - {$employeeLabel} - {$settlementReference}"
                );
                $addLine(
                    $accounts['salesman_clearing']->id,
                    0,
                    $actualPhysicalCash,
                    "Final Cash Deposit (Physical cash submitted) - {$employeeLabel} - {$settlementReference}"
                );
            }

            // 8a. Bank Slips / Deposits (Cash deposited directly to bank by salesman)
            // Treated as transfer from Salesman Clearing to Bank
            $settlement->bankSlips
                ->groupBy('bank_account_id')
                ->each(function ($slips) use (&$addLine, $accounts, $employeeLabel, $settlementReference): void {
                    $amount = $slips->sum('amount');
                    $bankAccount = $slips->first()->bankAccount;
                    $bankAccountId = $bankAccount?->chart_of_account_id ?? $accounts['bank_fallback']->id;
                    $bankName = $bankAccount?->bank_name ?? 'Bank (Unallocated)';

                    $addLine(
                        $bankAccountId,
                        $amount,
                        0,
                        "Bank Deposit (Slip) - {$bankName} - {$employeeLabel} - {$settlementReference}"
                    );
                    $addLine(
                        $accounts['salesman_clearing']->id,
                        0,
                        $amount,
                        "Bank Deposit (Slip) - {$bankName} - {$employeeLabel} - {$settlementReference}"
                    );
                });

            // 9. COGS (Moved to end)
            $totalCogsForGl = $settlement->items->reduce(function ($carry, $item) {
                $itemCogsValue = $item->batches->sum(function ($batch) {
                    return (float) $batch->quantity_sold * (float) $batch->unit_cost;
                });

                if ($itemCogsValue <= 0 && $item->quantity_sold > 0) {
                    $itemCogsValue = (float) $item->quantity_sold * (float) $item->unit_cost;
                }

                return $carry + $itemCogsValue;
            }, 0);

            if ($totalCogsForGl > 0) {
                $addLine(
                    $accounts['cogs']->id,
                    $totalCogsForGl,
                    0,
                    "Cost of Goods Sold (Inventory consumed) - {$employeeLabel} - {$settlementReference}"
                );
                $addLine(
                    $accounts['inventory']->id,
                    0,
                    $totalCogsForGl,
                    "Van Stock Reduction - Goods Sold ({$settlementReference})",
                    6
                );
            }

            // 10. Returns (Moved to end)
            $totalReturnValue = $settlement->items->reduce(function ($carry, $item) {
                $itemReturnValue = $item->batches->sum(function ($batch) {
                    return (float) $batch->quantity_returned * (float) $batch->unit_cost;
                });

                if ($itemReturnValue <= 0 && $item->quantity_returned > 0) {
                    $itemReturnValue = (float) $item->quantity_returned * (float) $item->unit_cost;
                }

                return $carry + $itemReturnValue;
            }, 0);

            if ($totalReturnValue > 0) {
                $addLine(
                    $accounts['stock_in_hand']->id,
                    $totalReturnValue,
                    0,
                    "Goods Returned to Warehouse (Inventory transfer) - {$employeeLabel} - {$settlementReference}",
                    6
                );
                $addLine(
                    $accounts['inventory']->id,
                    0,
                    $totalReturnValue,
                    "Van Stock Reduction - Returns ({$settlementReference})",
                    6
                );
            }

            // 11. Inventory Shortages (Moved to end)
            $totalShortageValue = $settlement->items->reduce(function ($carry, $item) {
                $itemShortageValue = $item->batches->sum(function ($batch) {
                    return (float) $batch->quantity_shortage * (float) $batch->unit_cost;
                });

                if ($itemShortageValue <= 0 && $item->quantity_shortage > 0) {
                    $itemShortageValue = (float) $item->quantity_shortage * (float) $item->unit_cost;
                }

                return $carry + $itemShortageValue;
            }, 0);

            if ($totalShortageValue > 0) {
                if (! $accounts['misc_expense']) {
                    throw new \Exception('Inventory Shortage account (5213) not found.');
                }

                $addLine(
                    $accounts['misc_expense']->id,
                    $totalShortageValue,
                    0,
                    "Inventory Shortage/Loss (Van stock discrepancy) - {$employeeLabel} - {$settlementReference}"
                );
                $addLine(
                    $accounts['inventory']->id,
                    0,
                    $totalShortageValue,
                    "Van Stock Reduction - Shortage ({$settlementReference})",
                    6
                );
            }

            $journalEntryData = [
                'entry_date' => $settlement->settlement_date,
                'reference' => $settlement->settlement_number,
                'description' => "Consolidated Sales Settlement - {$employeeLabel} - {$settlementDateFormatted}",
                'reference_type' => 'App\\Models\\SalesSettlement',
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
            // Cash & equivalents
            'cash' => \App\Models\ChartOfAccount::where('account_code', '1121')->first(),
            'cheques_in_hand' => \App\Models\ChartOfAccount::where('account_code', '1122')->first(),
            'salesman_clearing' => \App\Models\ChartOfAccount::where('account_code', '1123')->first(),
            'bank_fallback' => \App\Models\ChartOfAccount::where('account_code', '1171')->first(),
            'debtors' => \App\Models\ChartOfAccount::where('account_code', '1111')->first(),
            'earnest_money' => \App\Models\ChartOfAccount::where('account_code', '1141')->first(),
            'advance_tax' => \App\Models\ChartOfAccount::where('account_code', '1161')->first(),
            'stock_in_hand' => \App\Models\ChartOfAccount::where('account_code', '1151')->first(),
            'inventory' => \App\Models\ChartOfAccount::where('account_code', '1155')->first(),
            'sales' => \App\Models\ChartOfAccount::where('account_code', '4110')->first(),
            'cogs' => \App\Models\ChartOfAccount::where('account_code', '5111')->first(),
            'toll_tax' => \App\Models\ChartOfAccount::where('account_code', '5272')->first(),
            'amr_powder' => \App\Models\ChartOfAccount::where('account_code', '5252')->first(),
            'amr_liquid' => \App\Models\ChartOfAccount::where('account_code', '5262')->first(),
            'scheme' => \App\Models\ChartOfAccount::where('account_code', '5292')->first(),
            'food_salesman_loader' => \App\Models\ChartOfAccount::where('account_code', '5282')->first(),
            'percentage' => \App\Models\ChartOfAccount::where('account_code', '5223')->first(),
            'misc_expense' => \App\Models\ChartOfAccount::where('account_code', '5213')->first(),
        ];
    }

    /**
     * Validate that required accounts exist
     */
    protected function validateRequiredAccounts(array $accounts): bool
    {
        $required = ['cash', 'debtors', 'stock_in_hand', 'inventory', 'sales', 'cogs', 'salesman_clearing'];
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
