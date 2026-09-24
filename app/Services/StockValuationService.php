<?php

namespace App\Services;

use App\Models\StockBatch;
use App\Models\StockValuationLayer;
use Illuminate\Support\Facades\Log;

/**
 * Single owner of stock_valuation_layers.quantity_remaining.
 *
 * A batch can hold more than one layer (a GRN receipt plus later corrections),
 * so every consume and restore has to walk the whole set and spill over into
 * the next layer. The callers that used to take one layer with ->first() and
 * clamp it at zero silently dropped the remainder, which left current_stock
 * over-stated against current_stock_by_batch and the stock_movements ledger.
 */
class StockValuationService
{
    public const QTY_EPSILON = 0.001;

    /**
     * Take quantity out of a batch, oldest layer first, spilling into the next
     * layer when one cannot cover the whole amount.
     *
     * @throws \RuntimeException when the batch's layers hold less than requested
     */
    public function consumeBatch(int $batchId, int $warehouseId, float $quantity): void
    {
        if ($quantity <= self::QTY_EPSILON) {
            return;
        }

        $layers = StockValuationLayer::where('stock_batch_id', $batchId)
            ->where('warehouse_id', $warehouseId)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('receipt_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = $quantity;

        foreach ($layers as $layer) {
            if ($remaining <= self::QTY_EPSILON) {
                break;
            }

            $takeFromLayer = min((float) $layer->quantity_remaining, $remaining);

            $layer->quantity_remaining = (float) $layer->quantity_remaining - $takeFromLayer;
            $this->refreshLayerValue($layer);
            $layer->save();

            $remaining -= $takeFromLayer;
        }

        if ($remaining > self::QTY_EPSILON) {
            throw new \RuntimeException(sprintf(
                'Stock valuation is out of step: batch %d in warehouse %d needs %s more unit(s) than its valuation layers hold. Run "php artisan inventory:verify-consistency --fix" and post this document again.',
                $batchId,
                $warehouseId,
                rtrim(rtrim(number_format($remaining, 2, '.', ''), '0'), '.')
            ));
        }
    }

    /**
     * Put quantity back into a batch, newest layer first, so the layer that was
     * consumed last is the one refilled first.
     *
     * Returned stock is never dropped: when no layer has room left the newest
     * layer's received quantity grows with it, which keeps the layer total equal
     * to current_stock_by_batch.
     */
    public function restoreBatch(
        int $batchId,
        int $warehouseId,
        float $quantity,
        ?float $unitCost = null,
        ?int $stockMovementId = null
    ): void {
        if ($quantity <= self::QTY_EPSILON) {
            return;
        }

        $layers = StockValuationLayer::where('stock_batch_id', $batchId)
            ->where('warehouse_id', $warehouseId)
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        if ($layers->isEmpty()) {
            $this->createLayerForOrphanBatch($batchId, $warehouseId, $quantity, $unitCost, $stockMovementId);

            return;
        }

        $remaining = $quantity;

        foreach ($layers as $layer) {
            if ($remaining <= self::QTY_EPSILON) {
                break;
            }

            $headroom = (float) $layer->quantity_received - (float) $layer->quantity_remaining;

            if ($headroom <= self::QTY_EPSILON) {
                continue;
            }

            $addToLayer = min($headroom, $remaining);

            $layer->quantity_remaining = (float) $layer->quantity_remaining + $addToLayer;
            $this->refreshLayerValue($layer);
            $layer->save();

            $remaining -= $addToLayer;
        }

        if ($remaining > self::QTY_EPSILON) {
            $layer = $layers->first();
            $layer->quantity_received = (float) $layer->quantity_received + $remaining;
            $layer->quantity_remaining = (float) $layer->quantity_remaining + $remaining;
            $this->refreshLayerValue($layer);
            $layer->save();
        }
    }

    /**
     * Keep value_remaining and is_depleted in step with quantity_remaining.
     *
     * total_value holds the original receipt value and is deliberately left
     * alone — current_stock is derived from quantity_remaining * unit_cost.
     */
    private function refreshLayerValue(StockValuationLayer $layer): void
    {
        if ((float) $layer->quantity_remaining <= self::QTY_EPSILON) {
            $layer->quantity_remaining = 0;
            $layer->value_remaining = 0.0;
            $layer->is_depleted = true;

            return;
        }

        $layer->value_remaining = round((float) $layer->quantity_remaining * (float) $layer->unit_cost, 4);
        $layer->is_depleted = false;
    }

    /**
     * A batch with no layer at all cannot absorb a return, so give it one
     * rather than lose the stock. This should not happen; it is logged.
     *
     * stock_valuation_layers.stock_movement_id is NOT NULL, so a new layer can
     * only be written when the caller has a movement to tie it to.
     */
    private function createLayerForOrphanBatch(
        int $batchId,
        int $warehouseId,
        float $quantity,
        ?float $unitCost,
        ?int $stockMovementId
    ): void {
        $batch = StockBatch::find($batchId);

        if (! $batch || $stockMovementId === null) {
            Log::error('StockValuationService: cannot restore stock, no layer to put it in.', [
                'stock_batch_id' => $batchId,
                'warehouse_id' => $warehouseId,
                'quantity' => $quantity,
                'batch_found' => $batch !== null,
                'stock_movement_id' => $stockMovementId,
            ]);

            return;
        }

        $cost = $unitCost ?? (float) $batch->unit_cost;

        Log::warning('StockValuationService: batch had no valuation layer, creating one to absorb restored stock.', [
            'stock_batch_id' => $batchId,
            'warehouse_id' => $warehouseId,
            'quantity' => $quantity,
        ]);

        StockValuationLayer::create([
            'product_id' => $batch->product_id,
            'warehouse_id' => $warehouseId,
            'stock_batch_id' => $batchId,
            'stock_movement_id' => $stockMovementId,
            'grn_item_id' => $this->grnItemIdForBatch($batchId),
            'receipt_date' => $batch->receipt_date ?? now()->toDateString(),
            'quantity_received' => $quantity,
            'quantity_remaining' => $quantity,
            'unit_cost' => $cost,
            'total_value' => round($quantity * $cost, 4),
            'value_remaining' => round($quantity * $cost, 4),
            'priority_order' => $batch->priority_order ?? 99,
            'must_sell_before' => $batch->must_sell_before,
            'is_promotional' => (bool) ($batch->is_promotional ?? false),
            'is_depleted' => false,
        ]);
    }

    /**
     * The GRN item a batch was received under, taken from any layer it has in
     * another warehouse. New layers should carry it: the Goods Issue batch
     * picker joins goods_receipt_note_items, so a layer without it is counted
     * in the available total yet never offered for issue.
     */
    private function grnItemIdForBatch(int $batchId): ?int
    {
        return StockValuationLayer::where('stock_batch_id', $batchId)
            ->whereNotNull('grn_item_id')
            ->orderBy('id')
            ->value('grn_item_id');
    }
}
