<?php

namespace App\Services;

use App\Models\CurrentStockByBatch;
use App\Models\GoodsIssueItem;
use App\Models\StockBatch;
use App\Models\StockValuationLayer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BatchTransferService
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    /**
     * Transfer a full batch (or partial quantity) from one product to another.
     *
     * For FULL transfer  – all tables referencing this batch get product_id updated in-place.
     * For PARTIAL transfer – source batch is reduced; a new cloned batch is created for the target.
     *
     * Tables touched (mirrors what GRN post/reverse touches):
     *   1. stock_batches              – product_id (full) | new batch created (partial)
     *   2. stock_valuation_layers     – product_id (full) | split: reduce source, create target SVL
     *   3. current_stock_by_batch     – product_id (full) | split: reduce source, create target CSB
     *   4. current_stock              – synced for both products via syncCurrentStockFromValuationLayers
     *   5. goods_issue_items          – product_id updated for DRAFT GIs only (full transfer only)
     *   6. stock_movements            – product_id updated (full) | new transfer movements (partial)
     *
     * @param  int  $stockBatchId  Source batch ID
     * @param  int  $targetProductId  Target product to assign the batch/quantity to
     * @param  float  $quantity  Units to transfer (== batch qty means full transfer)
     * @param  string  $reason  Admin's reason note recorded in logs
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

            $result = $isFullTransfer
                ? $this->executeFullTransfer($batch, $csb, $targetProductId, $warehouseId, $reason)
                : $this->executePartialTransfer($batch, $csb, $targetProductId, $warehouseId, $quantity, $reason);

            // Sync current_stock for both source and target from SVL (source of truth)
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
     * Full transfer: update product_id in-place across ALL related tables.
     * No new records are created; the existing batch is simply reassigned.
     */
    private function executeFullTransfer(
        StockBatch $batch,
        CurrentStockByBatch $csb,
        int $targetProductId,
        int $warehouseId,
        string $reason
    ): array {
        $sourceProductId = $batch->product_id;

        // 1. stock_batches – reassign product
        $batch->product_id = $targetProductId;
        $batch->save();

        // 2. stock_valuation_layers – all layers for this batch
        StockValuationLayer::where('stock_batch_id', $batch->id)
            ->update(['product_id' => $targetProductId]);

        // 3. current_stock_by_batch – reassign product
        $csb->product_id = $targetProductId;
        $csb->save();

        // 4. stock_movements – all movements tied to this batch
        DB::table('stock_movements')
            ->where('stock_batch_id', $batch->id)
            ->update(['product_id' => $targetProductId]);

        // 5. goods_issue_items – DRAFT GIs only (posted GIs are immutable historical records)
        $affectedDraftItems = $this->reassignDraftGoodsIssueItems(
            $sourceProductId,
            $targetProductId,
            $batch->id
        );

        return [
            'type' => 'full',
            'batch_code' => $batch->batch_code,
            'quantity_transferred' => $csb->quantity_on_hand,
            'draft_gi_items_updated' => $affectedDraftItems,
        ];
    }

    /**
     * Partial transfer: reduce source batch, create a new cloned batch for the target product.
     *
     * We do NOT touch goods_issue_items for partial transfers because we cannot
     * automatically determine which portion of a draft GI item maps to the transferred
     * quantity. The admin is shown a warning if draft GIs exist.
     */
    private function executePartialTransfer(
        StockBatch $batch,
        CurrentStockByBatch $csb,
        int $targetProductId,
        int $warehouseId,
        float $transferQty,
        string $reason
    ): array {
        // Create a new batch for the target product, cloned from source attributes
        $newBatch = StockBatch::create([
            'batch_code' => $batch->batch_code.'-PT'.now()->format('Hm'),
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
            'notes' => "Partial transfer from {$batch->batch_code}. Reason: {$reason}",
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

        return [
            'type' => 'partial',
            'source_batch_code' => $batch->batch_code,
            'new_batch_code' => $newBatch->batch_code,
            'quantity_transferred' => $transferQty,
            'draft_gi_items_updated' => 0,
        ];
    }

    /**
     * For full batch transfers: find draft GI items that referenced the source product
     * and whose quantities could have been fulfilled from this specific batch, then
     * point them to the target product.
     *
     * Only DRAFT GIs are updated – posted/issued GIs are immutable historical records.
     */
    private function reassignDraftGoodsIssueItems(
        int $sourceProductId,
        int $targetProductId,
        int $batchId
    ): int {
        return GoodsIssueItem::whereHas('goodsIssue', fn ($q) => $q->where('status', 'draft'))
            ->where('product_id', $sourceProductId)
            ->update(['product_id' => $targetProductId]);
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
