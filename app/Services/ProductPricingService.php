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
     * Push a new selling price to batches, their current-stock rows and the GRN lines that still
     * hold stock. This is the single implementation behind both the product edit screen and an
     * approved ticket.
     *
     * @param  Collection<int, int>|null  $onlyBatchIds  null = every batch with stock; otherwise just these batches
     * @return Collection<int, int> ids of the batches that were updated
     */
    public function cascadeSellingPrice(Product $product, float|string $newPrice, ?Collection $onlyBatchIds = null): Collection
    {
        $batchIds = $onlyBatchIds ?? $this->batchIdsWithStock($product);

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
            ->where('is_depleted', false)
            ->where('quantity_remaining', '>', 0)
            ->where('is_promotional', false)
            ->whereNotNull('grn_item_id')
            ->when($onlyBatchIds !== null, fn ($query) => $query->whereIn('stock_batch_id', $batchIds))
            ->pluck('grn_item_id')
            ->unique();

        if ($activeGrnItemIds->isNotEmpty()) {
            DB::table('goods_receipt_note_items')
                ->whereIn('id', $activeGrnItemIds)
                ->where('is_promotional', false)
                ->update(['selling_price' => $newPrice]);
        }

        return $batchIds;
    }

    /**
     * Change the selling cost of one batch, or of every batch of the product that holds stock
     * (which also sets the product's own selling price for new receipts), and log it.
     * Used by the stock adjustment posting, whichever screen the adjustment came from.
     */
    public function applyBatchSellingCost(Product $product, int $stockBatchId, float|string $newPrice, string $scope, ?int $userId): void
    {
        $oldPrice = DB::table('stock_batches')->where('id', $stockBatchId)->value('selling_price');

        if ($scope === 'all') {
            $product->update(['unit_sell_price' => $newPrice]);
            $batchIds = $this->cascadeSellingPrice($product, $newPrice);
        } else {
            $batchIds = collect([$stockBatchId]);
            $this->cascadeSellingPrice($product, $newPrice, $batchIds);
        }

        $this->logChange($product, 'selling_price', $oldPrice, $newPrice, $userId, $batchIds);
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
            'impacted_batch_ids' => $impactedBatchIds?->map(fn ($id) => (int) $id)->all(),
            'impacted_batch_count' => $impactedBatchIds?->count() ?? 0,
            // Written by the application, not left to the database's own clock, so it always agrees with posted_at.
            'changed_at' => now(),
        ]);
    }
}
