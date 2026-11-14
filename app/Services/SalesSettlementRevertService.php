<?php

namespace App\Services;

use App\Models\CurrentStock;
use App\Models\CurrentStockByBatch;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\GoodsIssue;
use App\Models\InventoryLedgerEntry;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\VanStockBalance;
use App\Models\VanStockBatch;
use App\Services\BankRegister\SettlementBankRegisterSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesSettlementRevertService
{
    public function __construct(
        private AccountingService $accountingService,
        private InventoryLedgerService $inventoryLedgerService,
        private StockValuationService $stockValuation,
        private InventoryService $inventoryService,
    ) {}

    /**
     * Fully revert a posted sales settlement back to draft, reversing all
     * inventory, ledger, and GL impacts within a single transaction.
     *
     * @return array{success: bool, message: string}
     */
    public function revert(SalesSettlement $settlement): array
    {
        try {
            $preCheck = $this->performPreChecks($settlement);

            if (! $preCheck['ok']) {
                return ['success' => false, 'message' => $preCheck['message']];
            }

            DB::transaction(function () use ($settlement) {
                // A second revert of the same settlement (a double click, a resent form) waits on this lock and then
                // finds it already back in draft.
                $lockedStatus = SalesSettlement::whereKey($settlement->id)->lockForUpdate()->value('status');

                if ($lockedStatus !== 'posted') {
                    throw new \RuntimeException('Only posted settlements can be reverted.');
                }

                $settlement->load([
                    'items.batches',
                    'creditSales',
                    'recoveries',
                    'cheques',
                ]);

                $this->assertReturnedStockStillOnHand($settlement);

                $this->reverseGLEntry($settlement);
                $this->reverseInventoryLedgerEntries($settlement);
                $this->restoreVanStockBalance($settlement);
                $this->restoreVanStockBatches($settlement);
                $this->reverseWarehouseStockForReturns($settlement);
                $this->syncCurrentStockForReturns($settlement);
                $this->reverseCustomerLedgerEntries($settlement);
                $this->markStockMovementsReversed($settlement);
                $this->resetSettlementToDraft($settlement);

                // the settlement journal was reversed above: its bank register lines are reversed with it
                app(SettlementBankRegisterSync::class)->sync($settlement->fresh(), auth()->id());
            });

            Log::info('Sales settlement reverted successfully', [
                'settlement_id' => $settlement->id,
                'settlement_number' => $settlement->settlement_number,
                'reverted_by' => auth()->id(),
            ]);

            return [
                'success' => true,
                'message' => "Settlement {$settlement->settlement_number} has been reverted to draft successfully.",
            ];
        } catch (\Exception $e) {
            Log::error('Failed to revert sales settlement', [
                'settlement_id' => $settlement->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Failed to revert settlement: '.$e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function performPreChecks(SalesSettlement $settlement): array
    {
        if ($settlement->status !== 'posted') {
            return ['ok' => false, 'message' => 'Only posted settlements can be reverted.'];
        }

        if (! $settlement->journal_entry_id) {
            return ['ok' => false, 'message' => 'Settlement has no GL journal entry — data integrity issue. Contact support.'];
        }

        $clearedCheques = $settlement->cheques()->where('status', 'cleared')->count();

        if ($clearedCheques > 0) {
            return [
                'ok' => false,
                'message' => "Cannot revert: {$clearedCheques} cheque(s) linked to this settlement have already been cleared.",
            ];
        }

        // The shortage was recovered from (or, on older payslips, the excess paid with) that month's salary; taking the
        // settlement back now would leave the payroll wrong.
        if (app(PayrollService::class)->settlementIsInPayroll($settlement)) {
            return [
                'ok' => false,
                'message' => 'Cannot revert: this settlement\'s cash short or excess is already in the approved payroll of '.$settlement->settlement_date->format('F Y').'.',
            ];
        }

        // Reverting this settlement puts its Goods Issue back into an "active"
        // workflow state, which requires re-acquiring the vehicle lock. If a
        // newer GI already holds that lock on the same vehicle, the revert
        // would violate the unique index — surface a friendly message instead
        // of letting a raw DB error bubble up to the user.
        $blockingGi = GoodsIssue::where('active_vehicle_lock', $settlement->vehicle_id)
            ->where('id', '!=', $settlement->goods_issue_id)
            ->first();

        if ($blockingGi) {
            return [
                'ok' => false,
                'message' => "Cannot revert: vehicle is already locked by an active Goods Issue {$blockingGi->issue_number}. Delete or settle that GI first.",
            ];
        }

        return ['ok' => true, 'message' => ''];
    }

    /**
     * Create a reversing GL journal entry (REV- prefix, swapped debit/credit).
     */
    private function reverseGLEntry(SalesSettlement $settlement): void
    {
        $result = $this->accountingService->reverseJournalEntry(
            $settlement->journal_entry_id,
            "Revert Sales Settlement {$settlement->settlement_number}"
        );

        if (! $result['success']) {
            throw new \Exception('GL reversal failed: '.$result['message']);
        }
    }

    /**
     * Create offsetting inventory ledger entries (swap debit_qty ↔ credit_qty).
     *
     * Rows are netted per product / location / batch / cost rather than copied one by
     * one, so a settlement that was posted and reverted before reverses only what is
     * currently standing; copying every row would reverse the earlier reversals too.
     */
    private function reverseInventoryLedgerEntries(SalesSettlement $settlement): void
    {
        $standing = InventoryLedgerEntry::where('sales_settlement_id', $settlement->id)
            ->selectRaw('product_id, warehouse_id, vehicle_id, stock_batch_id, unit_cost,
                SUM(debit_qty) - SUM(credit_qty) as net_qty')
            ->groupBy('product_id', 'warehouse_id', 'vehicle_id', 'stock_batch_id', 'unit_cost')
            ->get();

        foreach ($standing as $group) {
            $netQty = round((float) $group->net_qty, 4);

            if (abs($netQty) <= 0.0001) {
                continue;
            }

            $this->inventoryLedgerService->recordAdjustment(
                productId: $group->product_id,
                warehouseId: $group->warehouse_id,
                vehicleId: $group->vehicle_id,
                debitQty: $netQty < 0 ? abs($netQty) : 0.0,
                creditQty: $netQty > 0 ? $netQty : 0.0,
                unitCost: (float) $group->unit_cost,
                date: now()->toDateString(),
                notes: "Reversal: SS {$settlement->settlement_number}",
                batchId: $group->stock_batch_id,
                salesSettlementId: $settlement->id,
            );
        }
    }

    /**
     * Refuse the revert when stock this settlement returned to the warehouse is no longer there: it was issued
     * again, sold or adjusted after posting. Taking it out anyway would leave current_stock_by_batch, the
     * valuation layers and the stock ledger disagreeing (the same rule a GRN reversal follows).
     *
     * @throws \RuntimeException naming the batches that fall short
     */
    private function assertReturnedStockStillOnHand(SalesSettlement $settlement): void
    {
        $returnedByBatch = [];

        foreach ($settlement->items as $item) {
            foreach ($item->batches as $itemBatch) {
                if ((float) $itemBatch->quantity_returned > 0) {
                    $returnedByBatch[$itemBatch->stock_batch_id] = ($returnedByBatch[$itemBatch->stock_batch_id] ?? 0) + (float) $itemBatch->quantity_returned;
                }
            }
        }

        $shortfalls = [];

        foreach ($returnedByBatch as $batchId => $returnedQty) {
            $onHand = (float) CurrentStockByBatch::where('stock_batch_id', $batchId)
                ->where('warehouse_id', $settlement->warehouse_id)
                ->lockForUpdate()
                ->value('quantity_on_hand');

            if ($onHand + StockValuationService::QTY_EPSILON < $returnedQty) {
                $batchCode = StockBatch::whereKey($batchId)->value('batch_code') ?? "#{$batchId}";
                $shortfalls[] = sprintf('batch %s has %s of the %s returned', $batchCode, $this->formatQuantity($onHand), $this->formatQuantity($returnedQty));
            }
        }

        if ($shortfalls !== []) {
            throw new \RuntimeException(
                "Stock returned by {$settlement->settlement_number} has since been issued, sold or adjusted ("
                .implode('; ', array_slice($shortfalls, 0, 3))
                .(count($shortfalls) > 3 ? '; and '.(count($shortfalls) - 3).' more batches' : '')
                .'). Reverse the later goods issue or adjustment first, then revert this settlement.'
            );
        }
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }

    private function productLabel(int $productId): string
    {
        return Product::whereKey($productId)->value('product_name') ?? "product #{$productId}";
    }

    /**
     * Restore van stock balance by adding back all quantities moved during posting.
     */
    private function restoreVanStockBalance(SalesSettlement $settlement): void
    {
        foreach ($settlement->items as $item) {
            $totalToRestore = (float) $item->quantity_sold
                + (float) $item->quantity_returned
                + (float) $item->quantity_shortage;

            if ($totalToRestore <= 0) {
                continue;
            }

            $vanStock = VanStockBalance::where('vehicle_id', $settlement->vehicle_id)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->first();

            if (! $vanStock) {
                throw new \RuntimeException("The van stock row for {$this->productLabel($item->product_id)} on this vehicle is missing, so its quantity cannot be put back. Nothing was reverted.");
            }

            $vanStock->quantity_on_hand = (float) $vanStock->quantity_on_hand + $totalToRestore;
            $vanStock->last_updated = now();
            $vanStock->save();
        }
    }

    /**
     * Put the quantities back on the van batch rows they came off.
     *
     * Each settlement item belongs to a goods issue item, and that item's van batch row
     * is where its stock was issued to the van, so it is restored there. Only when that
     * row no longer exists does the quantity go to the van's newest batch for the product.
     */
    private function restoreVanStockBatches(SalesSettlement $settlement): void
    {
        foreach ($settlement->items as $item) {
            $totalToRestore = (float) $item->quantity_sold
                + (float) $item->quantity_returned
                + (float) $item->quantity_shortage;

            if ($totalToRestore <= 0) {
                continue;
            }

            $vanStockBatch = VanStockBatch::where('vehicle_id', $settlement->vehicle_id)
                ->where('product_id', $item->product_id)
                ->where('goods_issue_item_id', $item->goods_issue_item_id)
                ->lockForUpdate()
                ->first()
                ?? VanStockBatch::where('vehicle_id', $settlement->vehicle_id)
                    ->where('product_id', $item->product_id)
                    ->orderByDesc('created_at')
                    ->lockForUpdate()
                    ->first();

            if (! $vanStockBatch) {
                throw new \RuntimeException("No van batch row for {$this->productLabel($item->product_id)} exists on this vehicle, so its quantity cannot be put back. Nothing was reverted.");
            }

            $vanStockBatch->increment('quantity_on_hand', $totalToRestore);
        }
    }

    /**
     * Undo warehouse stock increases that were caused by returns during posting.
     */
    private function reverseWarehouseStockForReturns(SalesSettlement $settlement): void
    {
        foreach ($settlement->items as $item) {
            foreach ($item->batches as $itemBatch) {
                $returnedQty = (float) $itemBatch->quantity_returned;

                if ($returnedQty <= 0) {
                    continue;
                }

                $batchId = $itemBatch->stock_batch_id;

                // Reverse CurrentStockByBatch
                $stockByBatch = CurrentStockByBatch::where('stock_batch_id', $batchId)
                    ->where('warehouse_id', $settlement->warehouse_id)
                    ->lockForUpdate()
                    ->first();

                if ($stockByBatch) {
                    $stockByBatch->quantity_on_hand = (float) $stockByBatch->quantity_on_hand - $returnedQty;
                    $stockByBatch->total_value = round($stockByBatch->quantity_on_hand * (float) $itemBatch->unit_cost, 2);

                    if ($stockByBatch->quantity_on_hand <= 0) {
                        $stockByBatch->status = 'depleted';
                    }

                    $stockByBatch->last_updated = now();
                    $stockByBatch->save();
                }

                // Take the returned goods back out of the batch's layers, oldest
                // first, so a batch holding several layers stays in step with
                // current_stock_by_batch.
                $this->stockValuation->consumeBatch($batchId, $settlement->warehouse_id, $returnedQty);
            }
        }
    }

    /**
     * Re-sync CurrentStock from valuation layers for products that had returns.
     */
    private function syncCurrentStockForReturns(SalesSettlement $settlement): void
    {
        $affectedProductIds = $settlement->items
            ->filter(fn ($item) => $item->batches->sum('quantity_returned') > 0)
            ->pluck('product_id')
            ->unique();

        foreach ($affectedProductIds as $productId) {
            $this->syncCurrentStockFromValuationLayers($productId, $settlement->warehouse_id);
        }
    }

    /**
     * Create reversing customer sub-ledger transactions (swap debit ↔ credit), each linked
     * to the row it undoes. Only rows still in effect are reversed: on a second revert the
     * first posting and its reversals have already cancelled out.
     */
    private function reverseCustomerLedgerEntries(SalesSettlement $settlement): void
    {
        $transactions = CustomerEmployeeAccountTransaction::where('sales_settlement_id', $settlement->id)
            ->whereNull('reverses_transaction_id')
            ->whereDoesntHave('reversals')
            ->get();

        foreach ($transactions as $txn) {
            CustomerEmployeeAccountTransaction::create([
                'customer_employee_account_id' => $txn->customer_employee_account_id,
                'transaction_date' => now()->toDateString(),
                'transaction_type' => 'adjustment',
                'reference_number' => 'REV-'.($txn->reference_number ?? $settlement->settlement_number),
                'sales_settlement_id' => $settlement->id,
                'reverses_transaction_id' => $txn->id,
                'invoice_number' => $txn->invoice_number,
                'description' => 'Reversal: '.($txn->description ?? "SS {$settlement->settlement_number}"),
                'debit' => $txn->credit,
                'credit' => $txn->debit,
                'payment_method' => $txn->payment_method,
                'cheque_number' => $txn->cheque_number,
                'cheque_date' => $txn->cheque_date,
                'bank_account_id' => $txn->bank_account_id,
                'notes' => "Reversal of transaction #{$txn->id} for settlement {$settlement->settlement_number}",
                'created_by' => auth()->id(),
            ]);
        }
    }

    /**
     * Create reversing stock movement rows for the settlement's van activity.
     *
     * Each reversal keeps the movement_type of the activity it undoes. Everything
     * that reads this ledger decides whether a row belongs to the warehouse or to
     * the van from its type — the snapshot rebuild, the consistency check and the
     * Van Stock Batch report all do. Writing reversals as 'adjustment' instead put
     * a reversed sale on the warehouse's books, where nothing physically arrived,
     * and left it off the van's, so a reverted settlement pushed the warehouse
     * ledger above current stock by the quantity that had been sold.
     *
     * The rows are netted per batch and type rather than copied one by one, so a
     * settlement that is posted and reverted more than once reverses only what is
     * currently standing — copying every row would reverse earlier reversals too.
     */
    private function markStockMovementsReversed(SalesSettlement $settlement): void
    {
        $standing = StockMovement::where('reference_type', 'App\\Models\\SalesSettlement')
            ->where('reference_id', $settlement->id)
            ->whereIn('movement_type', ['sale', 'return', 'shortage'])
            ->selectRaw('movement_type, product_id, stock_batch_id, warehouse_id, vehicle_id, uom_id,
                MAX(unit_cost) as unit_cost,
                SUM(quantity) as net_quantity,
                SUM(total_value) as net_value')
            ->groupBy('movement_type', 'product_id', 'stock_batch_id', 'warehouse_id', 'vehicle_id', 'uom_id')
            ->get();

        foreach ($standing as $group) {
            $netQuantity = (float) $group->net_quantity;

            if (abs($netQuantity) <= 0.001) {
                continue;
            }

            StockMovement::create([
                'movement_type' => $group->movement_type,
                'reference_type' => 'App\\Models\\SalesSettlement',
                'reference_id' => $settlement->id,
                'movement_date' => now()->toDateString(),
                'product_id' => $group->product_id,
                'stock_batch_id' => $group->stock_batch_id,
                'warehouse_id' => $group->warehouse_id,
                'vehicle_id' => $group->vehicle_id,
                'quantity' => -$netQuantity,
                'uom_id' => $group->uom_id,
                'unit_cost' => $group->unit_cost,
                'total_value' => abs((float) $group->net_value),
                'created_by' => auth()->id(),
            ]);
        }
    }

    /**
     * Reset the settlement back to draft and record audit fields. Also
     * re-acquires the vehicle lock on the linked Goods Issue: when the
     * settlement was originally posted, the lock was released — reverting
     * puts the GI back into an "active" workflow state, so the vehicle must
     * be locked again to prevent a parallel issue from being created.
     */
    private function resetSettlementToDraft(SalesSettlement $settlement): void
    {
        // Excess-cash rows are written by the posting itself (and feed the ROI report),
        // so they go with it; re-posting writes the current figure again.
        $settlement->excessAmounts()->delete();

        $settlement->update([
            'status' => 'draft',
            'posted_at' => null,
            'journal_entry_id' => null,
            'gross_profit' => null,
            'total_cogs' => null,
            'reverted_at' => now(),
            'reverted_by' => auth()->id(),
        ]);

        GoodsIssue::whereKey($settlement->goods_issue_id)
            ->update(['active_vehicle_lock' => DB::raw('vehicle_id')]);
    }

    /**
     * Sync CurrentStock for a product/warehouse via the one implementation in
     * InventoryService, rather than the copy that used to live here.
     */
    private function syncCurrentStockFromValuationLayers(int $productId, int $warehouseId): void
    {
        $this->inventoryService->syncCurrentStockFromValuationLayers($productId, $warehouseId);
    }
}
