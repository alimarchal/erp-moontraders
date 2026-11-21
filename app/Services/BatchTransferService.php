<?php

namespace App\Services;

use App\Models\CurrentStockByBatch;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockLedgerEntry;
use App\Models\StockMovement;
use App\Models\StockValuationLayer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BatchTransferService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly InventoryLedgerService $inventoryLedgerService
    ) {}

    /**
     * Move stock of a batch to another product: the whole quantity on hand or part of it.
     *
     * Either way the stock leaves the source product and arrives on the target as of today: an adjustment movement
     * with stock and inventory ledger rows on each side, and a new batch for the target carrying the same cost,
     * dates and supplier. Nothing already posted is changed: the batch's receipt, issues and sales keep their
     * product, so past reports, the GRN and the settlements stay as they were. Units still on a van stay on the
     * source batch and settle under the source product.
     *
     * Tables touched:
     *   stock_batches (new target batch), stock_valuation_layers (source reduced, target layer added),
     *   current_stock_by_batch (source reduced, target row added), current_stock (both products re-synced),
     *   stock_movements, stock_ledger_entries, inventory_ledger_entries (one out row and one in row each)
     *
     * @param  int  $stockBatchId  Source batch ID
     * @param  int  $targetProductId  Target product to move the quantity to
     * @param  float  $quantity  Units to transfer (up to the batch's quantity on hand)
     * @param  string  $reason  Admin's reason, kept on the new batch and the ledger rows
     * @return array{success: bool, message: string, data: array}
     */
    public function transfer(
        int $stockBatchId,
        int $targetProductId,
        float $quantity,
        string $reason
    ): array {
        try {
            DB::beginTransaction();

            $batch = StockBatch::lockForUpdate()->findOrFail($stockBatchId);

            // Determine warehouse from current_stock_by_batch (a batch lives in one warehouse)
            $csb = CurrentStockByBatch::lockForUpdate()
                ->where('stock_batch_id', $batch->id)
                ->firstOrFail();

            $sourceProductId = $batch->product_id;
            $warehouseId = $csb->warehouse_id;
            $availableQty = (float) $csb->quantity_on_hand;

            if ($sourceProductId === $targetProductId) {
                throw new \InvalidArgumentException('Source and target products must be different.');
            }

            if ($quantity <= 0 || $quantity > $availableQty) {
                throw new \InvalidArgumentException(
                    "Transfer quantity {$quantity} is invalid. Available: {$availableQty}."
                );
            }

            $isFullTransfer = abs($quantity - $availableQty) < 0.001;

            $result = $this->moveQuantity($batch, $csb, $targetProductId, $warehouseId, $quantity, $reason);
            $result['type'] = $isFullTransfer ? 'full' : 'partial';

            // Sync current_stock for both source and target from current_stock_by_batch
            $this->inventoryService->syncCurrentStockFromValuationLayers($sourceProductId, $warehouseId);
            $this->inventoryService->syncCurrentStockFromValuationLayers($targetProductId, $warehouseId);

            DB::commit();

            Log::info('BatchTransfer completed', [
                'batch_code' => $batch->batch_code,
                'from_product' => $sourceProductId,
                'to_product' => $targetProductId,
                'quantity' => $quantity,
                'type' => $isFullTransfer ? 'full' : 'partial',
                'by_user' => auth()->id(),
            ]);

            return [
                'success' => true,
                'message' => 'Batch transfer completed successfully.',
                'data' => $result,
            ];

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('BatchTransfer failed', [
                'stock_batch_id' => $stockBatchId,
                'target_product_id' => $targetProductId,
                'quantity' => $quantity,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Reduce the source batch and create a new batch for the target product with the same cost and dates.
     */
    private function moveQuantity(
        StockBatch $batch,
        CurrentStockByBatch $csb,
        int $targetProductId,
        int $warehouseId,
        float $transferQty,
        string $reason
    ): array {
        // Create a new batch for the target product, cloned from source attributes
        $newBatch = StockBatch::create([
            'batch_code' => $this->nextTransferBatchCode($batch->batch_code),
            'product_id' => $targetProductId,
            'supplier_id' => $batch->supplier_id,
            'receipt_date' => $batch->receipt_date,
            'supplier_batch_number' => $batch->supplier_batch_number,
            'lot_number' => $batch->lot_number,
            'manufacturing_date' => $batch->manufacturing_date,
            'expiry_date' => $batch->expiry_date,
            'is_promotional' => false, // Transferred partial qty is never promotional
            'must_sell_before' => $batch->must_sell_before,
            'priority_order' => $batch->priority_order,
            'unit_cost' => $batch->unit_cost,
            'selling_price' => $batch->selling_price,
            'status' => 'active',
            'is_active' => true,
            'notes' => "Transferred from {$batch->batch_code}. Reason: {$reason}",
        ]);

        // Reduce source SVL quantity_remaining (FIFO: take from earliest layer first)
        $remainingToDeduct = $transferQty;

        StockValuationLayer::where('stock_batch_id', $batch->id)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('receipt_date', 'asc')
            ->lockForUpdate()
            ->get()
            ->each(function (StockValuationLayer $layer) use (&$remainingToDeduct, $newBatch, $warehouseId) {
                if ($remainingToDeduct <= 0) {
                    return false; // Stop iteration
                }

                $deductFromLayer = min($remainingToDeduct, (float) $layer->quantity_remaining);

                // Reduce source layer
                $layer->quantity_remaining = (float) $layer->quantity_remaining - $deductFromLayer;
                $layer->is_depleted = $layer->quantity_remaining <= 0;
                $layer->save();

                // Create target SVL for transferred portion (mirrors createValuationLayer pattern)
                StockValuationLayer::create([
                    'product_id' => $newBatch->product_id,
                    'warehouse_id' => $warehouseId,
                    'stock_batch_id' => $newBatch->id,
                    'stock_movement_id' => $layer->stock_movement_id, // Trace back to original receipt
                    'grn_item_id' => $layer->grn_item_id,
                    'receipt_date' => $layer->receipt_date,
                    'quantity_received' => $deductFromLayer,
                    'quantity_remaining' => $deductFromLayer,
                    'unit_cost' => $layer->unit_cost,
                    'total_value' => $deductFromLayer * (float) $layer->unit_cost,
                    'value_remaining' => $deductFromLayer * (float) $layer->unit_cost,
                    'priority_order' => $layer->priority_order,
                    'must_sell_before' => $layer->must_sell_before,
                    'is_promotional' => false,
                    'is_depleted' => false,
                ]);

                $remainingToDeduct -= $deductFromLayer;
            });

        // Reduce source current_stock_by_batch
        $csb->quantity_on_hand = (float) $csb->quantity_on_hand - $transferQty;
        $csb->total_value = $csb->quantity_on_hand * (float) $csb->unit_cost;
        $csb->save();

        // Create target current_stock_by_batch
        CurrentStockByBatch::create([
            'product_id' => $targetProductId,
            'warehouse_id' => $warehouseId,
            'stock_batch_id' => $newBatch->id,
            'quantity_on_hand' => $transferQty,
            'unit_cost' => $csb->unit_cost,
            'selling_price' => $csb->selling_price,
            'total_value' => $transferQty * (float) $csb->unit_cost,
            'is_promotional' => false,
            'priority_order' => $csb->priority_order,
            'must_sell_before' => $csb->must_sell_before,
            'expiry_date' => $csb->expiry_date,
            'status' => 'active',
            'last_updated' => now(),
        ]);

        // Record the stock leaving the source product and arriving on the target, so the
        // per-product ledgers keep agreeing with current stock.
        $transferDate = now()->toDateString();
        $unitCost = (float) $csb->unit_cost;
        $notes = "Batch transfer {$batch->batch_code} → {$newBatch->batch_code}. Reason: {$reason}";

        $this->recordTransferLeg($batch->product_id, $batch->id, $warehouseId, -$transferQty, $unitCost, $transferDate, $newBatch, $notes);
        $this->recordTransferLeg($targetProductId, $newBatch->id, $warehouseId, $transferQty, $unitCost, $transferDate, $newBatch, $notes);

        return [
            'source_batch_code' => $batch->batch_code,
            'new_batch_code' => $newBatch->batch_code,
            'quantity_transferred' => $transferQty,
        ];
    }

    /**
     * One side of a partial transfer: an adjustment movement plus its stock and inventory ledger rows.
     */
    private function recordTransferLeg(
        int $productId,
        int $stockBatchId,
        int $warehouseId,
        float $quantity,
        float $unitCost,
        string $date,
        StockBatch $newBatch,
        string $notes
    ): void {
        $movement = StockMovement::create([
            'movement_type' => 'adjustment',
            'reference_type' => StockBatch::class,
            'reference_id' => $newBatch->id,
            'movement_date' => $date,
            'product_id' => $productId,
            'stock_batch_id' => $stockBatchId,
            'warehouse_id' => $warehouseId,
            'quantity' => $quantity,
            'uom_id' => Product::whereKey($productId)->value('uom_id'),
            'unit_cost' => $unitCost,
            'total_value' => round(abs($quantity) * $unitCost, 2),
            'created_by' => auth()->id(),
        ]);

        $previousBalance = (float) StockLedgerEntry::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('quantity_balance');

        $balance = $previousBalance + $quantity;

        StockLedgerEntry::create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'stock_batch_id' => $stockBatchId,
            'entry_date' => $date,
            'stock_movement_id' => $movement->id,
            'quantity_in' => max($quantity, 0),
            'quantity_out' => max(-$quantity, 0),
            'quantity_balance' => $balance,
            'valuation_rate' => $unitCost,
            'stock_value' => $balance * $unitCost,
            'reference_type' => StockBatch::class,
            'reference_id' => $newBatch->id,
            'created_at' => now(),
        ]);

        $this->inventoryLedgerService->recordAdjustment(
            productId: $productId,
            warehouseId: $warehouseId,
            vehicleId: null,
            debitQty: max($quantity, 0),
            creditQty: max(-$quantity, 0),
            unitCost: $unitCost,
            date: $date,
            notes: $notes,
            batchId: $stockBatchId,
        );
    }

    /**
     * The target batch is named after the original batch with a running number: BATCH-2026-0123-PT1, -PT2, …
     * A batch that was itself transferred counts from its original, so codes do not grow -PT1-PT1.
     */
    private function nextTransferBatchCode(string $sourceCode): string
    {
        $originalCode = preg_replace('/(-PT\d+)+$/', '', $sourceCode);
        $number = StockBatch::where('batch_code', 'like', $originalCode.'-PT%')->count() + 1;

        while (StockBatch::where('batch_code', $originalCode.'-PT'.$number)->exists()) {
            $number++;
        }

        return $originalCode.'-PT'.$number;
    }

    /**
     * Count draft GI items for a product to show a warning before partial transfer.
     */
    public function countDraftGoodsIssueItems(int $productId): int
    {
        return GoodsIssueItem::whereHas('goodsIssue', fn ($q) => $q->where('status', 'draft'))
            ->where('product_id', $productId)
            ->count();
    }

    /**
     * Return batches from a GRN that still have quantity remaining (available to transfer).
     */
    public function getBatchesForGrn(int $grnId): Collection
    {
        return StockBatch::query()
            ->join('current_stock_by_batch as csb', 'csb.stock_batch_id', '=', 'stock_batches.id')
            ->join('goods_receipt_note_items as grni', function ($join) {
                $join->on('grni.product_id', '=', 'stock_batches.product_id')
                    ->on('grni.batch_code', '=', 'stock_batches.batch_code');
            })
            ->where('grni.goods_receipt_note_id', $grnId)
            ->where('csb.quantity_on_hand', '>', 0)
            ->select([
                'stock_batches.id',
                'stock_batches.batch_code',
                'stock_batches.product_id',
                'csb.warehouse_id',
                'csb.quantity_on_hand',
                'csb.unit_cost',
                'csb.selling_price',
            ])
            ->with('product:id,product_name,product_code')
            ->get();
    }

    /**
     * Return batches for a specific product+warehouse from current_stock_by_batch.
     * Used by the Current Stock by-batch page's Batch Transfer button.
     */
    public function getBatchesForProductWarehouse(int $productId, int $warehouseId): Collection
    {
        return StockBatch::query()
            ->join('current_stock_by_batch as csb', 'csb.stock_batch_id', '=', 'stock_batches.id')
            ->where('stock_batches.product_id', $productId)
            ->where('csb.warehouse_id', $warehouseId)
            ->where('csb.quantity_on_hand', '>', 0)
            ->select([
                'stock_batches.id',
                'stock_batches.batch_code',
                'stock_batches.product_id',
                'csb.warehouse_id',
                'csb.quantity_on_hand',
                'csb.unit_cost',
                'csb.selling_price',
            ])
            ->with('product:id,product_name,product_code')
            ->get();
    }
}
