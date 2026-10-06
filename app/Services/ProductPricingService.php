<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPriceChangeLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductPricingService
{
    /**
     * Batch ids that still hold sellable (non-promotional) stock for a product.
     *
     * @return Collection<int, int>
     */
    public function batchIdsWithStock(Product $product): Collection
    {
        $fromValuation = DB::table('stock_valuation_layers')
            ->where('product_id', $product->id)
            ->where('is_depleted', false)
            ->where('quantity_remaining', '>', 0)
            ->where('is_promotional', false)
            ->whereNotNull('stock_batch_id')
            ->pluck('stock_batch_id');

        $fromCurrentStock = DB::table('current_stock_by_batch')
            ->where('product_id', $product->id)
            ->where('quantity_on_hand', '>', 0)
            ->where('is_promotional', false)
            ->whereNotNull('stock_batch_id')
            ->pluck('stock_batch_id');

        return $fromValuation->merge($fromCurrentStock)->unique()->values();
    }

    /**
     * Push a new selling price to the given batches, their current-stock rows
     * and the GRN lines that still have stock.
     *
     * @param  Collection<int, int>  $batchIds
     */
    public function applySellingPriceToBatches(Product $product, Collection $batchIds, float|string $newPrice): void
    {
        if ($batchIds->isNotEmpty()) {
            DB::table('stock_batches')
                ->whereIn('id', $batchIds)
                ->where('is_promotional', false)
                ->update(['selling_price' => $newPrice]);

            DB::table('current_stock_by_batch')
                ->whereIn('stock_batch_id', $batchIds)
                ->where('is_promotional', false)
                ->update(['selling_price' => $newPrice]);
        }

        $activeGrnItemIds = DB::table('stock_valuation_layers')
            ->where('product_id', $product->id)
            ->whereIn('stock_batch_id', $batchIds)
            ->where('is_depleted', false)
            ->where('quantity_remaining', '>', 0)
            ->where('is_promotional', false)
            ->whereNotNull('grn_item_id')
            ->pluck('grn_item_id')
            ->unique();

        if ($activeGrnItemIds->isNotEmpty()) {
            DB::table('goods_receipt_note_items')
                ->whereIn('id', $activeGrnItemIds)
                ->where('is_promotional', false)
                ->update(['selling_price' => $newPrice]);
        }
    }

    /**
     * @param  Collection<int, int>|null  $impactedBatchIds
     */
    public function logChange(Product $product, string $priceType, mixed $oldPrice, mixed $newPrice, ?int $userId, ?Collection $impactedBatchIds = null): void
    {
        ProductPriceChangeLog::create([
            'product_id' => $product->id,
            'changed_by' => $userId,
            'price_type' => $priceType,
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'impacted_batch_ids' => $impactedBatchIds?->all(),
            'impacted_batch_count' => $impactedBatchIds?->count() ?? 0,
        ]);
    }
}
