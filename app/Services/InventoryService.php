<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\CurrentStock;
use App\Models\CurrentStockByBatch;
use App\Models\GoodsReceiptNote;
use App\Models\JournalEntry;
use App\Models\StockBatch;
use App\Models\StockLedgerEntry;
use App\Models\StockMovement;
use App\Models\StockValuationLayer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    /** Liability a GRN credits until the supplier's invoice arrives. */
    public const STOCK_RECEIVED_NOT_BILLED_CODE = '2142';

    /**
     * Reference of the entry that moves a pre-2142 GRN's credit from Creditors to
     * Stock Received But Not Billed.
     */
    public static function grnReclassReference(GoodsReceiptNote $grn): string
    {
        return "GRNI-RECLASS-{$grn->grn_number}";
    }

    /**
     * GRN items whose total_cost is not their accepted quantity at their unit cost.
     *
     * Stock is valued at unit_cost, but the journal entry debits Stock In Hand with
     * total_cost. When the two disagree the ledger and the stock drift apart and the
     * gap lands in Round Off — GRN-2026-0033 carried a Cerelac line at 769,919.87
     * against 2,592 × 396.05 = 1,026,559.82, and its goods issues were costed off the
     * wrong figure too.
     *
     * @return Collection<int, object>
     */
    public function itemsWithMismatchedTotals(GoodsReceiptNote $grn): Collection
    {
        return $grn->items()->with('product:id,product_name')->get()
            ->filter(fn ($item) => abs((float) $item->total_cost - (float) ($item->quantity_accepted ?? $item->quantity_received) * (float) $item->unit_cost) > 1)
            ->values();
    }

    /**
     * @throws \RuntimeException naming each line whose total disagrees with its unit cost
     */
    protected function assertItemTotalsMatchUnitCost(GoodsReceiptNote $grn): void
    {
        $mismatched = $this->itemsWithMismatchedTotals($grn);

        if ($mismatched->isEmpty()) {
            return;
        }

        throw new \RuntimeException('Line total does not equal quantity × unit cost for '.$mismatched->map(fn ($item) => sprintf(
            '%s (total %s, %s × %s = %s)',
            $item->product?->product_name ?? "product {$item->product_id}",
            number_format((float) $item->total_cost, 2),
            rtrim(rtrim(number_format((float) ($item->quantity_accepted ?? $item->quantity_received), 3, '.', ''), '0'), '.'),
            number_format((float) $item->unit_cost, 2),
            number_format((float) ($item->quantity_accepted ?? $item->quantity_received) * (float) $item->unit_cost, 2)
        ))->implode('; ').'. Correct the line before posting.');
    }

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

            $this->assertItemTotalsMatchUnitCost($grn);

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

                // Create Inventory Ledger Entry (Double Entry System)
                $ledgerService = app(InventoryLedgerService::class);
                $ledgerService->recordPurchase(
                    $item->product_id,
                    $grn->warehouse_id,
                    $item->quantity_accepted ?? $item->quantity_received,
                    $item->unit_cost,
                    $grn->id,
                    $grn->receipt_date,
                    $item->notes ?? "GRN {$grn->grn_number} - Batch {$stockBatch->batch_code}",
                    $stockBatch->id
                );
            }

            // Create Accounting Journal Entry. This throws if the entry cannot be
            // written, which rolls the whole post back: a GRN that moved stock
            // without a GL entry used to be saved with journal_entry_id = null and
            // only a line in the log, leaving inventory and the ledger apart.
            // Only an opening-stock GRN of zero value returns null here.
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
     * Dr. Inventory - Main (Asset) - Account 1151 Stock In Hand (actual cost; taxes included in cost)
     * Dr/Cr. Round Off - Account 5271 (rounding difference between invoice and actual cost)
     * Cr. FMR Allowance - Account 4210 (Liquid) or 4220 (Powder) (income/contra-cost, if any)
     * Cr. Stock Received But Not Billed - Account 2142 (amount the supplier will invoice)
     *
     * The GRN records the goods; the supplier's invoice, entered in the ledger register,
     * is what makes the amount payable (Dr 2142 / Cr 2111 Creditors). Both used to debit
     * Stock In Hand and credit Creditors, so every purchase reached the ledger twice.
     */
    protected function createGrnJournalEntry(GoodsReceiptNote $grn)
    {
        if ($grn->is_opening_stock) {
            return $this->createOpeningStockJournalEntry($grn);
        }

        try {
            // Load supplier and items with products relationship
            $grn->loadMissing('supplier', 'items.product');

            // Find required accounts from Chart of Accounts
            $inventoryAccount = ChartOfAccount::where('account_code', '1151')->first();
            $receivedNotBilledAccount = ChartOfAccount::where('account_code', self::STOCK_RECEIVED_NOT_BILLED_CODE)->first();
            $fmrAllowanceLiquidAccount = ChartOfAccount::where('account_code', '4210')->first();
            $fmrAllowancePowderAccount = ChartOfAccount::where('account_code', '4220')->first();
            $roundOffAccount = ChartOfAccount::where('account_code', '5271')->first();
            $warehouseCostCenter = CostCenter::where('code', 'CC006')->first();

            if (! $inventoryAccount) {
                throw new \RuntimeException('Inventory account 1151 (Stock In Hand) is missing from the Chart of Accounts.');
            }

            if (! $receivedNotBilledAccount) {
                throw new \RuntimeException('Account 2142 (Stock Received But Not Billed) is missing from the Chart of Accounts.');
            }

            if (! $fmrAllowanceLiquidAccount || ! $fmrAllowancePowderAccount) {
                throw new \RuntimeException('FMR Allowance accounts 4210 (Liquid) and 4220 (Powder) are missing from the Chart of Accounts.');
            }

            if (! $warehouseCostCenter) {
                throw new \RuntimeException('Cost center CC006 (Warehouse & Inventory) is missing.');
            }

            // Calculate amounts from GRN items
            $extendedValue = $grn->items->sum('extended_value');
            $totalDiscounts = $grn->items->sum('discount_value');
            $totalFmrAllowance = $grn->items->sum('fmr_allowance');
            $totalGst = $grn->items->sum('sales_tax_value');
            $totalAdvanceTax = $grn->items->sum('advance_income_tax');
            $totalExciseDuty = $grn->items->sum('excise_duty') ?? 0;

            // Calculate FMR allowance by product type
            $fmrAllowanceLiquid = 0;
            $fmrAllowancePowder = 0;
            foreach ($grn->items as $item) {
                $fmrAmount = $item->fmr_allowance ?? 0;
                if ($item->product && $item->product->is_powder) {
                    $fmrAllowancePowder += $fmrAmount;
                } else {
                    $fmrAllowanceLiquid += $fmrAmount;
                }
            }

            // Every amount that reaches the ledger is rounded to the 2 decimals the ledger
            // stores, before the lines are built. goods_receipt_note_items.total_cost carries
            // 4 decimals, so leaving the rounding to the database let a half-paisa on the
            // inventory line and a half-paisa on the round-off line both round up, and the
            // entry landed a paisa out of balance.
            $actualInventoryValue = round((float) $grn->items->sum('total_cost'), 2);
            $invoiceValue = round($extendedValue - $totalDiscounts + $totalGst + $totalAdvanceTax + $totalExciseDuty, 2);
            $fmrAllowanceLiquid = round($fmrAllowanceLiquid, 2);
            $fmrAllowancePowder = round($fmrAllowancePowder, 2);
            $totalFmrAllowance = round($fmrAllowanceLiquid + $fmrAllowancePowder, 2);

            // Amount payable to supplier (invoice less FMR allowance)
            $creditorAmount = round($invoiceValue - $totalFmrAllowance, 2);
            if ($creditorAmount < 0) {
                $creditorAmount = 0.0;
            }

            if ($actualInventoryValue <= 0) {
                Log::warning('GRN actual inventory value is zero or negative. Skipping journal entry for GRN: '.$grn->id);

                return null;
            }

            // Prepare journal entry lines
            $journalLines = [];
            $lineNo = 1;

            // Dr. Inventory (actual cost: quantity × unit_cost)
            $journalLines[] = [
                'line_no' => $lineNo++,
                'account_id' => $inventoryAccount->id,
                'debit' => $actualInventoryValue,
                'credit' => 0,
                'description' => "Inventory received - {$grn->items->count()} item(s) (qty × unit cost)",
                'cost_center_id' => $warehouseCostCenter->id,
            ];

            // Cr. FMR Allowance Liquid (if any) - Income/contra-cost
            if ($fmrAllowanceLiquid > 0) {
                $journalLines[] = [
                    'line_no' => $lineNo++,
                    'account_id' => $fmrAllowanceLiquidAccount->id,
                    'debit' => 0,
                    'credit' => $fmrAllowanceLiquid,
                    'description' => 'FMR allowance (Liquid) - income for handling returns',
                    'cost_center_id' => $warehouseCostCenter->id,
                ];
            }

            // Cr. FMR Allowance Powder (if any) - Income/contra-cost
            if ($fmrAllowancePowder > 0) {
                $journalLines[] = [
                    'line_no' => $lineNo++,
                    'account_id' => $fmrAllowancePowderAccount->id,
                    'debit' => 0,
                    'credit' => $fmrAllowancePowder,
                    'description' => 'FMR allowance (Powder) - income for handling returns',
                    'cost_center_id' => $warehouseCostCenter->id,
                ];
            }

            // Cr. Stock Received But Not Billed — the supplier's invoice, posted through the
            // ledger register, clears this into Creditors.
            $journalLines[] = [
                'line_no' => $lineNo++,
                'account_id' => $receivedNotBilledAccount->id,
                'debit' => 0,
                'credit' => $creditorAmount,
                'description' => "Received from {$grn->supplier->supplier_name}, awaiting invoice",
                'cost_center_id' => $warehouseCostCenter->id,
            ];

            // Dr/Cr Round Off — the residual of the lines above, so the entry balances to the
            // paisa by construction rather than by the invoice-versus-cost arithmetic agreeing.
            $roundingDifference = round(
                array_sum(array_column($journalLines, 'credit')) - array_sum(array_column($journalLines, 'debit')),
                2
            );

            if (abs($roundingDifference) >= 0.01) {
                if (! $roundOffAccount) {
                    Log::warning('Rounding difference detected but Round Off account (5271) not found. Skipping journal entry for GRN: '.$grn->id);

                    return null;
                }

                $journalLines[] = [
                    'line_no' => $lineNo++,
                    'account_id' => $roundOffAccount->id,
                    'debit' => $roundingDifference > 0 ? $roundingDifference : 0,
                    'credit' => $roundingDifference < 0 ? abs($roundingDifference) : 0,
                    'description' => 'Rounding adjustment on GRN',
                    'cost_center_id' => $warehouseCostCenter->id,
                ];
            }

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
                Log::info("Journal entry created for GRN {$grn->grn_number}: JE #{$result['data']->entry_number} | Inventory: {$actualInventoryValue} | Rounding: {$roundingDifference} | GST: {$totalGst} | Advance Tax: {$totalAdvanceTax} | Excise: {$totalExciseDuty} | FMR: {$totalFmrAllowance} | Creditors: {$creditorAmount}");

                return $result['data'];
            }

            throw new \RuntimeException($result['message']);
        } catch (\Exception $e) {
            Log::error("Exception creating journal entry for GRN {$grn->id}: ".$e->getMessage());

            throw $e;
        }
    }

    /**
     * Opening Stock JE: Dr. Inventory (1151), Cr. Opening Balance Equity
     */
    protected function createOpeningStockJournalEntry(GoodsReceiptNote $grn)
    {
        try {
            $grn->loadMissing('supplier', 'items');

            $inventoryAccount = ChartOfAccount::where('account_code', '1151')->first();
            $openingBalanceEquityAccount = ChartOfAccount::where('account_name', 'Opening Balance Equity')->first();
            $warehouseCostCenter = CostCenter::where('code', 'CC006')->first();

            if (! $inventoryAccount || ! $openingBalanceEquityAccount) {
                throw new \RuntimeException('Inventory account 1151 (Stock In Hand) or the Opening Balance Equity account is missing from the Chart of Accounts.');
            }

            $totalCost = $grn->items->sum('total_cost');

            if ($totalCost <= 0) {
                // Nothing to post. This is the one case where a GRN legitimately
                // carries no journal entry.
                Log::warning("Opening stock JE skipped for GRN {$grn->id}: total cost is zero.");

                return null;
            }

            $journalLines = [
                [
                    'line_no' => 1,
                    'account_id' => $inventoryAccount->id,
                    'debit' => $totalCost,
                    'credit' => 0,
                    'description' => "Opening stock - {$grn->supplier->supplier_name}",
                    'cost_center_id' => $warehouseCostCenter?->id,
                ],
                [
                    'line_no' => 2,
                    'account_id' => $openingBalanceEquityAccount->id,
                    'debit' => 0,
                    'credit' => $totalCost,
                    'description' => "Opening stock equity - {$grn->supplier->supplier_name}",
                    'cost_center_id' => $warehouseCostCenter?->id,
                ],
            ];

            $accountingService = app(AccountingService::class);
            $result = $accountingService->createJournalEntry([
                'entry_date' => Carbon::parse($grn->receipt_date)->toDateString(),
                'reference' => $grn->grn_number,
                'description' => "Opening Stock - {$grn->supplier->supplier_name} ({$grn->grn_number})",
                'lines' => $journalLines,
                'auto_post' => true,
            ]);

            if ($result['success']) {
                Log::info("Opening stock JE created for GRN {$grn->grn_number}: JE #{$result['data']->entry_number} | Amount: {$totalCost}");

                return $result['data'];
            }

            throw new \RuntimeException($result['message']);
        } catch (\Exception $e) {
            Log::error("Exception creating opening stock JE for GRN {$grn->id}: ".$e->getMessage());

            throw $e;
        }
    }

    /**
     * Reverse the GRN's own posted journal entry, line for line with debit and
     * credit swapped.
     *
     * Mirroring the entry that was actually posted is the only way to undo it
     * exactly: recomputing the lines from the GRN items could land a paisa off
     * the original rounding, and would send an opening-stock GRN (posted against
     * Opening Balance Equity) to Creditors instead.
     *
     * @throws \RuntimeException when the reversing entry cannot be written, so the
     *                           caller rolls the stock reversal back with it
     */
    protected function createGrnReversingJournalEntry(GoodsReceiptNote $grn): ?JournalEntry
    {
        if (! $grn->journal_entry_id) {
            $receiptValue = round((float) $grn->items()->sum('total_cost'), 2);

            // A zero-value opening-stock GRN never had an entry; there is nothing to undo.
            if ($receiptValue <= 0) {
                return null;
            }

            throw new \RuntimeException(
                "{$grn->grn_number} has no journal entry of its own, so its value reached the general ledger another way "
                .'(e.g. through the supplier Ledger Register). Reverse it there first; reversing only the stock would leave the ledger out of step.'
            );
        }

        $userName = auth()->user()->name ?? 'System';
        $grn->loadMissing('supplier');

        $result = app(AccountingService::class)->reverseJournalEntry(
            (int) $grn->journal_entry_id,
            "REVERSAL: GRN {$grn->grn_number} - Goods returned to {$grn->supplier->supplier_name} (Password confirmed by: {$userName})"
        );

        if (! $result['success']) {
            throw new \RuntimeException($result['message']);
        }

        // A GRN posted before Stock Received But Not Billed existed credited Creditors, and
        // accounting:repost-supplier-invoices moved that credit across with a second entry.
        // Undo that one too, or the reversal would leave Creditors and 2142 apart.
        $reclassEntryId = JournalEntry::where('reference', self::grnReclassReference($grn))
            ->where('status', 'posted')
            ->value('id');

        if ($reclassEntryId) {
            $reclassReversal = app(AccountingService::class)->reverseJournalEntry(
                (int) $reclassEntryId,
                "REVERSAL: GRN {$grn->grn_number} - Stock Received But Not Billed reclassification"
            );

            if (! $reclassReversal['success']) {
                throw new \RuntimeException($reclassReversal['message']);
            }
        }

        Log::info("Reversing journal entry created for GRN {$grn->grn_number}: JE #{$result['data']->id} mirrors JE #{$grn->journal_entry_id}");

        return $result['data'];
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
     * Generate unique batch code (supports 5+ digit sequences beyond 9999)
     */
    private function generateBatchCode(): string
    {
        $year = now()->year;
        $prefix = "BATCH-{$year}-";

        $driver = DB::getDriverName();
        $substringPos = strlen($prefix) + 1;

        if ($driver === 'pgsql') {
            $orderByRaw = "CAST(SUBSTRING(batch_code, {$substringPos}) AS INTEGER) DESC";
        } else {
            $orderByRaw = 'CAST(SUBSTRING(batch_code, ?) AS UNSIGNED) DESC';
        }

        $query = StockBatch::where('batch_code', 'like', "{$prefix}%");

        $lastBatch = $driver === 'pgsql'
            ? $query->orderByRaw($orderByRaw)->first()
            : $query->orderByRaw($orderByRaw, [$substringPos])->first();

        $nextNumber = $lastBatch
            ? (int) substr($lastBatch->batch_code, strlen($prefix)) + 1
            : 1;

        return sprintf('%s%04d', $prefix, $nextNumber);
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
            'stock_value' => round($quantityBalance * (float) $item->unit_cost, 4),
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
            'total_value' => $item->total_cost,
            'value_remaining' => $item->total_cost,
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
        // Lock and update current_stock_by_batch
        $stockByBatch = CurrentStockByBatch::lockForUpdate()->firstOrNew([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'stock_batch_id' => $batchId,
        ]);

        $stockByBatch->quantity_on_hand = ($stockByBatch->quantity_on_hand ?? 0) + $item->quantity_accepted;
        $stockByBatch->unit_cost = $item->unit_cost;
        $stockByBatch->selling_price = $item->is_promotional
            ? ($item->promotional_price ?? $item->selling_price)
            : $item->selling_price;
        $stockByBatch->total_value = ($stockByBatch->total_value ?? 0) + $item->total_cost;
        $stockByBatch->is_promotional = $item->is_promotional;
        $stockByBatch->promotional_price = $item->promotional_price;
        $stockByBatch->priority_order = $item->priority_order;
        $stockByBatch->must_sell_before = $item->must_sell_before;
        $stockByBatch->expiry_date = $item->expiry_date;
        $stockByBatch->status = 'active';
        $stockByBatch->last_updated = now();
        $stockByBatch->save();

        // Sync CurrentStock from current_stock_by_batch
        $this->syncCurrentStockFromValuationLayers($productId, $warehouseId);
    }

    /**
     * Sync CurrentStock from current_stock_by_batch, which is the record that
     * agrees with the stock_movements ledger.
     */
    public function syncCurrentStockFromValuationLayers(int $productId, int $warehouseId): void
    {
        // ⚠️  IMPORTANT — quantity comes from current_stock_by_batch, not from
        // stock_valuation_layers.
        //
        // Both used to be maintained separately, which meant two answers to
        // "how much is on hand". current_stock_by_batch is the one that agrees
        // with the stock_movements ledger, so it is the source; the layers hold
        // cost, and a bug there must not be able to change the quantity shown
        // on /inventory/current-stock.
        //
        // Value is still derived per batch as quantity * unit_cost — never
        // SUM(total_value), which stores the original receipt value and is not
        // decremented as stock is issued.
        //
        // Rule: current_stock.quantity_on_hand = SUM(csb.quantity_on_hand)
        //       current_stock.total_value      = SUM(csb.quantity_on_hand * csb.unit_cost)
        $batchData = CurrentStockByBatch::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('quantity_on_hand', '>', 0)
            ->selectRaw('
                COALESCE(SUM(quantity_on_hand), 0) as total_qty,
                COALESCE(SUM(quantity_on_hand * unit_cost), 0) as total_value
            ')
            ->first();

        $totalQty = (float) ($batchData->total_qty ?? 0);
        $totalValue = (float) ($batchData->total_value ?? 0);
        $avgCost = $totalQty > 0 ? round($totalValue / $totalQty, 6) : 0;

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

        // Lock and update CurrentStock with calculated values
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

        Log::debug('CurrentStock synced from current_stock_by_batch', [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity_on_hand' => $totalQty,
            'total_value' => $totalValue,
        ]);
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

            // A GRN can carry more than one 'grn' movement per batch (edit-special
            // corrections post the difference), so reverse the net received quantity.
            $receipts = $movements
                ->groupBy(fn (StockMovement $movement) => $movement->stock_batch_id.'|'.$movement->warehouse_id)
                ->map(function ($batchMovements) {
                    $first = $batchMovements->first();
                    $quantity = (float) $batchMovements->sum('quantity');

                    return (object) [
                        'product_id' => (int) $first->product_id,
                        'stock_batch_id' => (int) $first->stock_batch_id,
                        'warehouse_id' => (int) $first->warehouse_id,
                        'uom_id' => $first->uom_id,
                        'quantity' => $quantity,
                        'unit_cost' => $quantity > 0
                            ? (float) $batchMovements->sum(fn ($movement) => (float) $movement->quantity * (float) $movement->unit_cost) / $quantity
                            : (float) $first->unit_cost,
                    ];
                })
                ->filter(fn ($receipt) => $receipt->quantity > StockValuationService::QTY_EPSILON)
                ->values();

            $this->assertGrnStockStillOnHand($grn, $receipts);

            $stockValuation = app(StockValuationService::class);
            $ledgerService = app(InventoryLedgerService::class);
            $reversalDate = now()->toDateString();

            foreach ($receipts as $receipt) {
                $reversingMovement = StockMovement::create([
                    'movement_type' => 'adjustment',
                    'reference_type' => 'GRN Reversal',
                    'reference_id' => $grn->id,
                    'movement_date' => $reversalDate,
                    'product_id' => $receipt->product_id,
                    'stock_batch_id' => $receipt->stock_batch_id,
                    'warehouse_id' => $receipt->warehouse_id,
                    'quantity' => -$receipt->quantity,
                    'uom_id' => $receipt->uom_id,
                    'unit_cost' => $receipt->unit_cost,
                    'total_value' => round($receipt->quantity * $receipt->unit_cost, 4),
                    'created_by' => auth()->id(),
                ]);

                $previousBalance = StockLedgerEntry::where('product_id', $receipt->product_id)
                    ->where('warehouse_id', $receipt->warehouse_id)
                    ->orderBy('id', 'desc')
                    ->first();

                StockLedgerEntry::create([
                    'product_id' => $receipt->product_id,
                    'warehouse_id' => $receipt->warehouse_id,
                    'stock_batch_id' => $receipt->stock_batch_id,
                    'entry_date' => $reversalDate,
                    'stock_movement_id' => $reversingMovement->id,
                    'quantity_in' => 0,
                    'quantity_out' => $receipt->quantity,
                    'quantity_balance' => ($previousBalance->quantity_balance ?? 0) - $receipt->quantity,
                    'valuation_rate' => 0,
                    'stock_value' => 0,
                    'reference_type' => 'reversal',
                    'reference_id' => $grn->id,
                    'created_at' => now(),
                ]);

                $stockValuation->consumeBatch($receipt->stock_batch_id, $receipt->warehouse_id, $receipt->quantity);

                $stockByBatch = CurrentStockByBatch::where('stock_batch_id', $receipt->stock_batch_id)
                    ->where('warehouse_id', $receipt->warehouse_id)
                    ->lockForUpdate()
                    ->first();

                $stockByBatch->quantity_on_hand = (float) $stockByBatch->quantity_on_hand - $receipt->quantity;
                if ($stockByBatch->quantity_on_hand <= StockValuationService::QTY_EPSILON) {
                    $stockByBatch->quantity_on_hand = 0;
                    $stockByBatch->total_value = 0.0;
                    $stockByBatch->status = 'depleted';
                } else {
                    $stockByBatch->total_value = round((float) $stockByBatch->quantity_on_hand * (float) $stockByBatch->unit_cost, 2);
                }
                $stockByBatch->last_updated = now();
                $stockByBatch->save();

                if (CurrentStockByBatch::where('stock_batch_id', $receipt->stock_batch_id)->sum('quantity_on_hand') <= 0) {
                    StockBatch::whereKey($receipt->stock_batch_id)->update(['status' => 'depleted']);
                }

                $this->syncCurrentStockFromValuationLayers($receipt->product_id, $receipt->warehouse_id);

                $ledgerService->recordAdjustment(
                    $receipt->product_id,
                    $receipt->warehouse_id,
                    null,
                    0,
                    $receipt->quantity,
                    $receipt->unit_cost,
                    $reversalDate,
                    "GRN {$grn->grn_number} reversed",
                    $receipt->stock_batch_id
                );
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
     * A GRN can only be sent back while everything it received is still in its
     * batch. Once part of it has been issued, sold or adjusted, reversing the
     * whole receipt would take the batch below zero: current_stock_by_batch used
     * to clamp at zero while the ledger went negative, and the GL credited the
     * full receipt for stock that was no longer there.
     *
     * @param  Collection<int, object{product_id: int, stock_batch_id: int, warehouse_id: int, quantity: float}>  $receipts
     *
     * @throws \RuntimeException naming each batch that is short
     */
    protected function assertGrnStockStillOnHand(GoodsReceiptNote $grn, Collection $receipts): void
    {
        $shortfalls = [];

        foreach ($receipts as $receipt) {
            $onHand = (float) CurrentStockByBatch::where('stock_batch_id', $receipt->stock_batch_id)
                ->where('warehouse_id', $receipt->warehouse_id)
                ->lockForUpdate()
                ->value('quantity_on_hand');

            if ($onHand + StockValuationService::QTY_EPSILON < $receipt->quantity) {
                $batchCode = StockBatch::whereKey($receipt->stock_batch_id)->value('batch_code') ?? "#{$receipt->stock_batch_id}";
                $shortfalls[] = sprintf('batch %s has %s of the %s received', $batchCode, $this->formatQuantity($onHand), $this->formatQuantity($receipt->quantity));
            }
        }

        if ($shortfalls !== []) {
            throw new \RuntimeException(
                "Stock from {$grn->grn_number} has already been issued, sold or adjusted ("
                .implode('; ', $shortfalls)
                .'). Return the remaining stock to the supplier with a stock adjustment instead.'
            );
        }
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }
}
