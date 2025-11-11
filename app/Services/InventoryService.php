<?php

namespace App\Services;

use App\Models\GoodsReceiptNote;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockLedgerEntry;
use App\Models\StockValuationLayer;
use App\Models\CurrentStock;
use App\Models\CurrentStockByBatch;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InventoryService
{
    /**
     * Post GRN to inventory - creates stock batches and updates inventory
     */
    public function postGrnToInventory(GoodsReceiptNote $grn): array
    {
        try {
            DB::beginTransaction();

            if ($grn->status === 'posted') {
                throw new \Exception('GRN is already posted');
            }

            if ($grn->status === 'cancelled') {
                throw new \Exception('Cannot post cancelled GRN');
            }

            foreach ($grn->items as $item) {
                $batchCode = $this->generateBatchCode();

                $stockBatch = StockBatch::create([
                    'batch_code' => $batchCode,
                    'product_id' => $item->product_id,
                    'supplier_id' => $grn->supplier_id,
                    'receipt_date' => $grn->receipt_date,
                    'supplier_batch_number' => $item->batch_number,
                    'manufacturing_date' => $item->manufacturing_date,
                    'expiry_date' => $item->expiry_date,
                    'promotional_campaign_id' => $item->promotional_campaign_id,
                    'is_promotional' => $item->is_promotional,
                    'promotional_selling_price' => $item->promotional_price,
                    'promotional_discount_percent' => $item->promotional_discount_percent,
                    'must_sell_before' => $item->must_sell_before,
                    'priority_order' => $item->priority_order ?? 99,
                    'selling_strategy' => $item->selling_strategy ?? 'fifo',
                    'unit_cost' => $item->unit_cost,
                    'status' => 'active',
                ]);

                $stockMovement = StockMovement::create([
                    'movement_type' => 'grn',
                    'reference_type' => 'App\Models\GoodsReceiptNote',
                    'reference_id' => $grn->id,
                    'movement_date' => $grn->receipt_date,
                    'product_id' => $item->product_id,
                    'stock_batch_id' => $stockBatch->id,
                    'warehouse_id' => $grn->warehouse_id,
                    'quantity' => $item->quantity_accepted,
                    'uom_id' => $item->uom_id,
                    'unit_cost' => $item->unit_cost,
                    'total_value' => $item->total_cost,
                    'created_by' => auth()->id() ?? 1,
                ]);

                $this->createStockLedgerEntry($stockMovement, $item);

                $this->createValuationLayer($stockMovement, $item, $stockBatch->id);

                $this->updateCurrentStock($item->product_id, $grn->warehouse_id, $stockBatch->id, $item);
            }

            // Create Accounting Journal Entry
            $journalEntry = $this->createGrnJournalEntry($grn);

            $grn->update([
                'status' => 'posted',
                'posted_at' => now(),
                'journal_entry_id' => $journalEntry ? $journalEntry->id : null,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => "GRN {$grn->grn_number} posted successfully to inventory" . ($journalEntry ? " and accounting" : ""),
                'data' => $grn->fresh(),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to post GRN: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Create Journal Entry for GRN posting
     * Dr. Inventory (Asset) - Account 1161 Stock In Hand
     * Cr. Accounts Payable (Liability) - Account 2111 Creditors
     */
    protected function createGrnJournalEntry(GoodsReceiptNote $grn)
    {
        try {
            // Find the Inventory and Accounts Payable accounts from Chart of Accounts
            $inventoryAccount = ChartOfAccount::where('account_code', '1161')->first();
            $apAccount = ChartOfAccount::where('account_code', '2111')->first();

            if (!$inventoryAccount) {
                Log::warning('Inventory account (1161 - Stock In Hand) not found in Chart of Accounts. Skipping journal entry for GRN: ' . $grn->id);
                return null;
            }

            if (!$apAccount) {
                Log::warning('Accounts Payable account (2111 - Creditors) not found in Chart of Accounts. Skipping journal entry for GRN: ' . $grn->id);
                return null;
            }

            // Calculate total amount from GRN items
            $totalAmount = $grn->items->sum('total_cost');

            if ($totalAmount <= 0) {
                Log::warning('GRN total amount is zero or negative. Skipping journal entry for GRN: ' . $grn->id);
                return null;
            }

            // Prepare journal entry data
            $journalEntryData = [
                'entry_date' => Carbon::parse($grn->receipt_date)->toDateString(),
                'description' => "GRN #{$grn->grn_number} - Goods received from {$grn->supplier->name}",
                'reference_type' => 'App\Models\GoodsReceiptNote',
                'reference_id' => $grn->id,
                'lines' => [
                    [
                        'account_id' => $inventoryAccount->id,
                        'debit' => $totalAmount,
                        'credit' => 0,
                        'description' => "Inventory received - {$grn->items->count()} item(s)",
                        'cost_center_id' => 1,
                    ],
                    [
                        'account_id' => $apAccount->id,
                        'debit' => 0,
                        'credit' => $totalAmount,
                        'description' => "Amount payable to {$grn->supplier->name}",
                        'cost_center_id' => 1,
                    ],
                ],
                'auto_post' => true, // Automatically post the entry
            ];

            // Create journal entry using AccountingService
            $accountingService = app(AccountingService::class);
            $result = $accountingService->createJournalEntry($journalEntryData);

            if ($result['success']) {
                Log::info("Journal entry created for GRN {$grn->grn_number}: JE #{$result['data']->entry_number}");
                return $result['data'];
            } else {
                Log::error("Failed to create journal entry for GRN {$grn->grn_number}: " . $result['message']);
                return null;
            }

        } catch (\Exception $e) {
            Log::error("Exception creating journal entry for GRN {$grn->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate unique batch code
     */
    private function generateBatchCode(): string
    {
        $year = now()->year;
        $lastBatch = StockBatch::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastBatch ? (int) substr($lastBatch->batch_code, -4) + 1 : 1;

        return sprintf('BATCH-%d-%04d', $year, $nextNumber);
    }

    /**
     * Create stock ledger entry for audit trail
     */
    private function createStockLedgerEntry(StockMovement $movement, $item): void
    {
        $previousBalance = StockLedgerEntry::where('product_id', $item->product_id)
            ->where('warehouse_id', $movement->warehouse_id)
            ->orderBy('id', 'desc')
            ->first();

        $quantityBalance = ($previousBalance->quantity_balance ?? 0) + $item->quantity_accepted;

        StockLedgerEntry::create([
            'product_id' => $item->product_id,
            'warehouse_id' => $movement->warehouse_id,
            'stock_batch_id' => $movement->stock_batch_id,
            'entry_date' => $movement->movement_date,
            'stock_movement_id' => $movement->id,
            'quantity_in' => $item->quantity_accepted,
            'quantity_out' => 0,
            'quantity_balance' => $quantityBalance,
            'valuation_rate' => $item->unit_cost,
            'stock_value' => $quantityBalance * $item->unit_cost,
            'reference_type' => $movement->reference_type,
            'reference_id' => $movement->reference_id,
            'created_at' => now(),
        ]);
    }

    /**
     * Create valuation layer for FIFO costing
     */
    private function createValuationLayer(StockMovement $movement, $item, $batchId): void
    {
        StockValuationLayer::create([
            'product_id' => $item->product_id,
            'warehouse_id' => $movement->warehouse_id,
            'stock_batch_id' => $batchId,
            'stock_movement_id' => $movement->id,
            'grn_item_id' => $item->id,
            'receipt_date' => $movement->movement_date,
            'quantity_received' => $item->quantity_accepted,
            'quantity_remaining' => $item->quantity_accepted,
            'unit_cost' => $item->unit_cost,
            'total_value' => $item->quantity_accepted * $item->unit_cost,
            'priority_order' => $item->priority_order,
            'must_sell_before' => $item->must_sell_before,
            'is_promotional' => $item->is_promotional,
        ]);
    }

    /**
     * Update current stock summary tables
     */
    private function updateCurrentStock($productId, $warehouseId, $batchId, $item): void
    {
        $stockByBatch = CurrentStockByBatch::firstOrNew([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'stock_batch_id' => $batchId,
        ]);

        $stockByBatch->quantity_on_hand = ($stockByBatch->quantity_on_hand ?? 0) + $item->quantity_accepted;
        $stockByBatch->unit_cost = $item->unit_cost;
        $stockByBatch->total_value = $stockByBatch->quantity_on_hand * $stockByBatch->unit_cost;
        $stockByBatch->is_promotional = $item->is_promotional;
        $stockByBatch->promotional_price = $item->promotional_price;
        $stockByBatch->priority_order = $item->priority_order;
        $stockByBatch->must_sell_before = $item->must_sell_before;
        $stockByBatch->expiry_date = $item->expiry_date;
        $stockByBatch->status = 'active';
        $stockByBatch->last_updated = now();
        $stockByBatch->save();

        $currentStock = CurrentStock::firstOrNew([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
        ]);

        $totalQty = ($currentStock->quantity_on_hand ?? 0) + $item->quantity_accepted;
        $totalValue = ($currentStock->total_value ?? 0) + $item->total_cost;
        $avgCost = $totalQty > 0 ? $totalValue / $totalQty : 0;

        $currentStock->quantity_on_hand = $totalQty;
        $currentStock->quantity_available = $totalQty - ($currentStock->quantity_reserved ?? 0);
        $currentStock->average_cost = $avgCost;
        $currentStock->total_value = $totalValue;
        $currentStock->last_updated = now();

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

        $currentStock->total_batches = $totalBatches;
        $currentStock->promotional_batches = $promotionalBatches;
        $currentStock->priority_batches = $priorityBatches;
        $currentStock->save();
    }

    /**
     * Reverse a posted GRN - creates reversing entries
     */
    public function reverseGrnInventory(GoodsReceiptNote $grn): array
    {
        try {
            DB::beginTransaction();

            if ($grn->status !== 'posted') {
                throw new \Exception('Only posted GRNs can be reversed');
            }

            // Find all stock movements related to this GRN
            $movements = StockMovement::where('reference_type', 'App\\Models\\GoodsReceiptNote')
                ->where('reference_id', $grn->id)
                ->where('movement_type', 'grn')
                ->get();

            if ($movements->isEmpty()) {
                throw new \Exception('No stock movements found for this GRN');
            }

            foreach ($movements as $movement) {
                // Create reversing movement
                $reversingMovement = StockMovement::create([
                    'movement_type' => 'adjustment',
                    'reference_type' => 'GRN Reversal',
                    'reference_id' => $grn->id,
                    'movement_date' => now()->toDateString(),
                    'product_id' => $movement->product_id,
                    'stock_batch_id' => $movement->stock_batch_id,
                    'warehouse_id' => $movement->warehouse_id,
                    'quantity' => -$movement->quantity,
                    'uom_id' => $movement->uom_id,
                    'unit_cost' => $movement->unit_cost,
                    'total_value' => abs((float) $movement->quantity) * (float) $movement->unit_cost,
                    'created_by' => auth()->id(),
                ]);

                // Create reversing ledger entry
                $previousBalance = StockLedgerEntry::where('product_id', $movement->product_id)
                    ->where('warehouse_id', $movement->warehouse_id)
                    ->orderBy('id', 'desc')
                    ->first();

                $quantityBalance = ($previousBalance->quantity_balance ?? 0) - $movement->quantity;

                StockLedgerEntry::create([
                    'product_id' => $movement->product_id,
                    'warehouse_id' => $movement->warehouse_id,
                    'stock_batch_id' => $movement->stock_batch_id,
                    'entry_date' => now()->toDateString(),
                    'stock_movement_id' => $reversingMovement->id,
                    'quantity_in' => 0,
                    'quantity_out' => $movement->quantity,
                    'quantity_balance' => $quantityBalance,
                    'valuation_rate' => 0,
                    'stock_value' => 0,
                    'reference_type' => 'reversal',
                    'reference_id' => $grn->id,
                    'created_at' => now(),
                ]);

                // Update or create reversing valuation layer
                $this->createReversingValuationLayer(
                    $movement->product_id,
                    $movement->warehouse_id,
                    $movement->quantity,
                    $movement->stock_batch_id
                );

                // Update batch quantity
                $batch = StockBatch::find($movement->stock_batch_id);
                if ($batch) {
                    $batch->quantity_on_hand -= $movement->quantity;
                    if ($batch->quantity_on_hand <= 0) {
                        $batch->status = 'depleted';
                        $batch->quantity_on_hand = 0;
                    }
                    $batch->save();
                }

                // Update current stock by batch
                $stockByBatch = CurrentStockByBatch::where('product_id', $movement->product_id)
                    ->where('warehouse_id', $movement->warehouse_id)
                    ->where('stock_batch_id', $movement->stock_batch_id)
                    ->first();

                if ($stockByBatch) {
                    $stockByBatch->quantity_on_hand -= $movement->quantity;
                    if ($stockByBatch->quantity_on_hand <= 0) {
                        $stockByBatch->quantity_on_hand = 0;
                        $stockByBatch->status = 'depleted';
                    }
                    $stockByBatch->total_value = $stockByBatch->quantity_on_hand * $stockByBatch->unit_cost;
                    $stockByBatch->last_updated = now();
                    $stockByBatch->save();
                }

                // Update current stock summary
                $currentStock = CurrentStock::where('product_id', $movement->product_id)
                    ->where('warehouse_id', $movement->warehouse_id)
                    ->first();

                if ($currentStock) {
                    $currentStock->quantity_on_hand -= $movement->quantity;
                    $currentStock->quantity_available -= $movement->quantity;
                    if ($currentStock->quantity_on_hand < 0) {
                        $currentStock->quantity_on_hand = 0;
                    }
                    if ($currentStock->quantity_available < 0) {
                        $currentStock->quantity_available = 0;
                    }

                    // Recalculate totals
                    $allBatches = CurrentStockByBatch::where('product_id', $movement->product_id)
                        ->where('warehouse_id', $movement->warehouse_id)
                        ->get();

                    $totalValue = $allBatches->sum('total_value');
                    $totalQty = $allBatches->sum('quantity_on_hand');

                    $currentStock->total_value = $totalValue;
                    $currentStock->average_cost = $totalQty > 0 ? $totalValue / $totalQty : 0;
                    $currentStock->last_updated = now();
                    $currentStock->save();
                }
            }

            // Update GRN status
            $grn->status = 'reversed';
            $grn->reversed_at = now();
            $grn->reversed_by = auth()->id();
            $grn->save();

            DB::commit();

            return [
                'success' => true,
                'message' => "GRN '{$grn->grn_number}' reversed successfully",
                'data' => $grn,
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Failed to reverse GRN: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Create reversing valuation layer
     */
    protected function createReversingValuationLayer($productId, $warehouseId, $quantity, $batchId)
    {
        // Find the most recent valuation layer for this batch
        $layer = StockValuationLayer::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('stock_batch_id', $batchId)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($layer) {
            $layer->quantity_remaining -= $quantity;
            if ($layer->quantity_remaining < 0) {
                $layer->quantity_remaining = 0;
            }
            $layer->total_value = $layer->quantity_remaining * $layer->unit_cost;
            $layer->save();
        }
    }
}
