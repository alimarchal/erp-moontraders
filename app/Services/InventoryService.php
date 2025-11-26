<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\CurrentStock;
use App\Models\CurrentStockByBatch;
use App\Models\GoodsReceiptNote;
use App\Models\StockBatch;
use App\Models\StockLedgerEntry;
use App\Models\StockMovement;
use App\Models\StockValuationLayer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                    'lot_number' => $item->lot_number,
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
                    'selling_price' => $item->selling_price,
                    'storage_location' => $item->storage_location,
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
                    'uom_id' => $item->stock_uom_id,
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
                'message' => "GRN {$grn->grn_number} posted successfully to inventory".($journalEntry ? ' and accounting' : ''),
                'data' => $grn->fresh(),
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Failed to post GRN: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Create Journal Entry for GRN posting
     *
     * Accounting Entries:
     * Dr. Inventory - Main (Asset) - Account 1161 Stock In Hand (net of discounts)
     * Dr. Inventory - GST Component (Asset) - Account 1162 (if any)
     * Dr. Inventory - Tax Component (Asset) - Account 1163 (if any)
     * Dr. Inventory - Excise Duty (Asset) - Account 1164 (if any)
     * Cr. FMR Allowance - Account 4210 (income/contra-cost, if any)
     * Cr. Accounts Payable (Liability) - Account 2111 Creditors (amount payable to supplier)
     */
    protected function createGrnJournalEntry(GoodsReceiptNote $grn)
    {
        try {
            // Load supplier relationship if not already loaded
            $grn->loadMissing('supplier', 'items');

            // Find required accounts from Chart of Accounts
            $inventoryAccount = ChartOfAccount::where('account_code', '1161')->first();
            $apAccount = ChartOfAccount::where('account_code', '2111')->first();
            $fmrAllowanceAccount = ChartOfAccount::where('account_code', '4210')->first();

            if (! $inventoryAccount) {
                Log::warning('Inventory account (1161 - Stock In Hand) not found in Chart of Accounts. Skipping journal entry for GRN: '.$grn->id);

                return null;
            }

            if (! $apAccount) {
                Log::warning('Accounts Payable account (2111 - Creditors) not found in Chart of Accounts. Skipping journal entry for GRN: '.$grn->id);

                return null;
            }

            // Calculate amounts from GRN items
            $extendedValue = $grn->items->sum('extended_value');
            $totalDiscounts = $grn->items->sum('discount_value');
            $totalFmrAllowance = $grn->items->sum('fmr_allowance');
            $totalGst = $grn->items->sum('sales_tax_value');
            $totalAdvanceTax = $grn->items->sum('advance_income_tax');
            $totalExciseDuty = $grn->items->sum('excise_duty') ?? 0;

            // Calculate inventory components
            // Total inventory cost = extended value - discounts + all taxes
            $totalInventoryAsset = $extendedValue - $totalDiscounts + $totalGst + $totalAdvanceTax + $totalExciseDuty;

            // Calculate amount payable to supplier (what we actually pay)
            // Creditors = Total Inventory - FMR Allowance
            $creditorAmount = $totalInventoryAsset - $totalFmrAllowance;

            if ($totalInventoryAsset <= 0) {
                Log::warning('GRN net inventory value is zero or negative. Skipping journal entry for GRN: '.$grn->id);

                return null;
            }

            // Prepare journal entry lines
            $journalLines = [];
            $lineNo = 1;

            // Dr. Inventory (all costs capitalized into one account)
            $journalLines[] = [
                'line_no' => $lineNo++,
                'account_id' => $inventoryAccount->id,
                'debit' => $totalInventoryAsset,
                'credit' => 0,
                'description' => "Inventory received - {$grn->items->count()} item(s) (full cost including taxes)",
                'cost_center_id' => null,
            ];

            // Cr. FMR Allowance (if any) - Income/contra-cost
            if ($totalFmrAllowance > 0 && $fmrAllowanceAccount) {
                $journalLines[] = [
                    'line_no' => $lineNo++,
                    'account_id' => $fmrAllowanceAccount->id,
                    'debit' => 0,
                    'credit' => $totalFmrAllowance,
                    'description' => 'FMR allowance - income for handling returns',
                    'cost_center_id' => null,
                ];
            }

            // Cr. Accounts Payable (amount payable to supplier)
            $journalLines[] = [
                'line_no' => $lineNo++,
                'account_id' => $apAccount->id,
                'debit' => 0,
                'credit' => $creditorAmount,
                'description' => "Amount payable to {$grn->supplier->supplier_name}",
                'cost_center_id' => null,
            ];

            // Prepare journal entry data
            $journalEntryData = [
                'entry_date' => Carbon::parse($grn->receipt_date)->toDateString(),
                'reference' => $grn->supplier_invoice_number ?? $grn->grn_number,
                'description' => "GRN #{$grn->grn_number} - Goods received from {$grn->supplier->supplier_name}",
                'lines' => $journalLines,
                'auto_post' => true, // Automatically post the entry
            ];

            // Create journal entry using AccountingService
            $accountingService = app(AccountingService::class);
            $result = $accountingService->createJournalEntry($journalEntryData);

            if ($result['success']) {
                Log::info("Journal entry created for GRN {$grn->grn_number}: JE #{$result['data']->entry_number} | Inventory Main: {$inventoryMain} | GST: {$totalGst} | Advance Tax: {$totalAdvanceTax} | Excise: {$totalExciseDuty} | FMR: {$totalFmrAllowance} | Creditors: {$creditorAmount} | Total Inventory Asset: {$totalInventoryAsset}");

                return $result['data'];
            } else {
                Log::error("Failed to create journal entry for GRN {$grn->grn_number}: ".$result['message']);

                return null;
            }

        } catch (\Exception $e) {
            Log::error("Exception creating journal entry for GRN {$grn->id}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Create Reversing Journal Entry for GRN reversal
     *
     * Reversal Entries (opposite of posting entries):
     * Dr. Accounts Payable (Liability) - Account 2111 Creditors
     * Dr. FMR Allowance - Account 4210 (reverse the credit, if any)
     * Cr. Inventory (Asset) - Account 1161 Stock In Hand (full cost including taxes)
     */
    protected function createGrnReversingJournalEntry(GoodsReceiptNote $grn)
    {
        try {
            // Load supplier relationship if not already loaded
            $grn->loadMissing('supplier', 'items');

            // Find required accounts from Chart of Accounts
            $inventoryAccount = ChartOfAccount::where('account_code', '1161')->first();
            $apAccount = ChartOfAccount::where('account_code', '2111')->first();
            $fmrAllowanceAccount = ChartOfAccount::where('account_code', '4210')->first();

            if (! $inventoryAccount || ! $apAccount) {
                Log::warning('Required accounts not found in Chart of Accounts. Skipping reversing journal entry for GRN: '.$grn->id);

                return null;
            }

            // Calculate amounts from GRN items (same as posting)
            $extendedValue = $grn->items->sum('extended_value');
            $totalDiscounts = $grn->items->sum('discount_value');
            $totalFmrAllowance = $grn->items->sum('fmr_allowance');
            $totalGst = $grn->items->sum('sales_tax_value');
            $totalAdvanceTax = $grn->items->sum('advance_income_tax');
            $totalExciseDuty = $grn->items->sum('excise_duty') ?? 0;

            // Calculate inventory components (same as posting)
            $totalInventoryAsset = $extendedValue - $totalDiscounts + $totalGst + $totalAdvanceTax + $totalExciseDuty;
            $creditorAmount = $totalInventoryAsset - $totalFmrAllowance;

            if ($totalInventoryAsset <= 0) {
                Log::warning('GRN net inventory value is zero or negative. Skipping reversing journal entry for GRN: '.$grn->id);

                return null;
            }

            // Build detailed description
            $userName = auth()->user()->name ?? 'System';
            $description = "REVERSAL: GRN {$grn->grn_number} - Goods returned to {$grn->supplier->supplier_name} (Password confirmed by: {$userName})";
            $itemCount = $grn->items->count();
            $itemsText = $itemCount === 1 ? '1 item' : "{$itemCount} items";

            // Prepare reversing journal entry lines (opposite of posting entries)
            $journalLines = [];
            $lineNo = 1;

            // Dr. Accounts Payable - reverse the credit
            $journalLines[] = [
                'line_no' => $lineNo++,
                'account_id' => $apAccount->id,
                'debit' => $creditorAmount,
                'credit' => 0,
                'description' => "Reversal - Liability to {$grn->supplier->supplier_name} reduced ({$itemsText})",
                'cost_center_id' => null,
            ];

            // Dr. FMR Allowance (if any) - reverse the credit
            if ($totalFmrAllowance > 0 && $fmrAllowanceAccount) {
                $journalLines[] = [
                    'line_no' => $lineNo++,
                    'account_id' => $fmrAllowanceAccount->id,
                    'debit' => $totalFmrAllowance,
                    'credit' => 0,
                    'description' => 'Reversal - FMR allowance',
                    'cost_center_id' => null,
                ];
            }

            // Cr. Inventory (all costs) - reverse the debit
            $journalLines[] = [
                'line_no' => $lineNo++,
                'account_id' => $inventoryAccount->id,
                'debit' => 0,
                'credit' => $totalInventoryAsset,
                'description' => "Reversal - Inventory returned to supplier ({$itemsText})",
                'cost_center_id' => null,
            ];

            // Prepare reversing journal entry data
            $journalEntryData = [
                'entry_date' => now()->toDateString(),
                'reference' => $grn->supplier_invoice_number ?? $grn->grn_number,
                'description' => $description,
                'lines' => $journalLines,
                'auto_post' => true, // Automatically post the entry
            ];

            // Create journal entry using AccountingService
            $accountingService = app(AccountingService::class);
            $result = $accountingService->createJournalEntry($journalEntryData);

            if ($result['success']) {
                Log::info("Journal entry created for GRN {$grn->grn_number}: JE #{$result['data']->id} | Total Inventory: {$totalInventoryAsset} | FMR: {$totalFmrAllowance} | Creditors: {$creditorAmount}");

                return $result['data'];
            } else {
                Log::error("Failed to create reversing journal entry for GRN {$grn->grn_number}: ".$result['message']);

                return null;
            }

        } catch (\Exception $e) {
            Log::error("Exception creating reversing journal entry for GRN {$grn->id}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Cancel or delete draft payments associated with a GRN
     */
    protected function cancelDraftPaymentsForGrn(GoodsReceiptNote $grn)
    {
        try {
            // Find all draft payments associated with this GRN
            $draftPayments = $grn->payments()
                ->where('status', 'draft')
                ->get();

            if ($draftPayments->isEmpty()) {
                Log::info("No draft payments found for GRN {$grn->grn_number}");

                return;
            }

            foreach ($draftPayments as $payment) {
                // Check if this payment is ONLY for this GRN or has other GRNs
                $otherGrnsCount = $payment->grns()
                    ->where('goods_receipt_notes.id', '!=', $grn->id)
                    ->where('goods_receipt_notes.status', '!=', 'reversed')
                    ->count();

                if ($otherGrnsCount > 0) {
                    // Payment has other non-reversed GRNs, just remove this GRN's allocation
                    $payment->grnAllocations()
                        ->where('grn_id', $grn->id)
                        ->delete();

                    // Recalculate payment amount
                    $remainingAmount = $payment->grnAllocations()->sum('amount_allocated');
                    $payment->amount = $remainingAmount;
                    $payment->save();

                    Log::info("Removed GRN {$grn->grn_number} allocation from payment {$payment->payment_number}");
                } else {
                    // Payment is only for this GRN, delete the entire payment
                    $payment->grnAllocations()->delete();
                    $payment->delete();

                    Log::info("Deleted draft payment {$payment->payment_number} for reversed GRN {$grn->grn_number}");
                }
            }

        } catch (\Exception $e) {
            Log::error("Error cancelling draft payments for GRN {$grn->id}: ".$e->getMessage());
            // Don't throw exception, just log it - reversal should still succeed
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

                // Update batch status if needed (no quantity_on_hand in stock_batches)
                $batch = StockBatch::find($movement->stock_batch_id);
                if ($batch) {
                    // Check if batch is depleted by looking at current_stock_by_batch
                    $remainingQty = CurrentStockByBatch::where('stock_batch_id', $batch->id)
                        ->sum('quantity_on_hand');

                    if ($remainingQty <= 0) {
                        $batch->status = 'depleted';
                        $batch->save();
                    }
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

            // Cancel or delete any associated draft payments
            $this->cancelDraftPaymentsForGrn($grn);

            // Create reversing journal entry for GL
            $this->createGrnReversingJournalEntry($grn);

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
                'message' => 'Failed to reverse GRN: '.$e->getMessage(),
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
