<?php

namespace App\Services;

use App\Models\CurrentStock;
use App\Models\CurrentStockByBatch;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\GoodsIssue;
use App\Models\InventoryLedgerEntry;
use App\Models\SalesSettlement;
use App\Models\StockMovement;
use App\Models\VanStockBalance;
use App\Models\VanStockBatch;
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
                $settlement->load([
                    'items.batches',
                    'creditSales',
                    'recoveries',
                    'cheques',
                ]);

                $this->reverseGLEntry($settlement);
                $this->reverseInventoryLedgerEntries($settlement);
                $this->restoreVanStockBalance($settlement);
                $this->restoreVanStockBatches($settlement);
                $this->reverseWarehouseStockForReturns($settlement);
                $this->syncCurrentStockForReturns($settlement);
                $this->reverseCustomerLedgerEntries($settlement);
                $this->markStockMovementsReversed($settlement);
                $this->resetSettlementToDraft($settlement);
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
     */
    private function reverseInventoryLedgerEntries(SalesSettlement $settlement): void
    {
        $entries = InventoryLedgerEntry::where('sales_settlement_id', $settlement->id)->get();

        foreach ($entries as $entry) {
            $this->inventoryLedgerService->recordAdjustment(
                productId: $entry->product_id,
                warehouseId: $entry->warehouse_id,
                vehicleId: $entry->vehicle_id,
                debitQty: (float) $entry->credit_qty,
                creditQty: (float) $entry->debit_qty,
                unitCost: (float) $entry->unit_cost,
                date: now()->toDateString(),
                notes: 'Reversal: '.($entry->notes ?? "SS {$settlement->settlement_number}"),
                batchId: $entry->stock_batch_id,
                salesSettlementId: $settlement->id,
            );
        }
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

            VanStockBalance::where('vehicle_id', $settlement->vehicle_id)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->first()?->increment('quantity_on_hand', $totalToRestore);
        }
    }

    /**
     * Restore van stock batches in LIFO order (reverse of the FIFO used during posting).
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

            // Restore in reverse-FIFO (newest first) to mirror the FIFO deduction done during posting
            $vanStockBatches = VanStockBatch::where('vehicle_id', $settlement->vehicle_id)
                ->where('product_id', $item->product_id)
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->get();

            $qtyToRestore = $totalToRestore;

            foreach ($vanStockBatches as $vsBatch) {
                if ($qtyToRestore <= 0) {
                    break;
                }

                $vsBatch->quantity_on_hand += $qtyToRestore;
                $vsBatch->save();
                $qtyToRestore = 0;
            }
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
                    $stockByBatch->quantity_on_hand = max(0, $stockByBatch->quantity_on_hand - $returnedQty);
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
     * Create reversing customer sub-ledger transactions (swap debit ↔ credit).
     */
    private function reverseCustomerLedgerEntries(SalesSettlement $settlement): void
    {
        $transactions = CustomerEmployeeAccountTransaction::where('sales_settlement_id', $settlement->id)->get();

        foreach ($transactions as $txn) {
            CustomerEmployeeAccountTransaction::create([
                'customer_employee_account_id' => $txn->customer_employee_account_id,
                'transaction_date' => now()->toDateString(),
                'transaction_type' => 'adjustment',
                'reference_number' => 'REV-'.($txn->reference_number ?? $settlement->settlement_number),
                'sales_settlement_id' => $settlement->id,
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
        $settlement->update([
            'status' => 'draft',
            'posted_at' => null,
            'journal_entry_id' => null,
            'gross_profit' => null,
            'total_cogs' => null,
            'cash_sales_amount' => 0,
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
