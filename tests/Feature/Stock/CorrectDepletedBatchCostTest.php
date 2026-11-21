<?php

use App\Models\CurrentStockByBatch;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockValuationLayer;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A batch received 240 at 360.35 and written off after its cost was mistyped as 3060.35.
 *
 * @return array{batch: StockBatch, layer: StockValuationLayer}
 */
function mistypedEmptyBatch(float $onHand = 0): array
{
    $uom = Uom::factory()->create();
    $warehouse = Warehouse::factory()->create(['disabled' => false]);
    $product = Product::factory()->create(['uom_id' => $uom->id]);
    $batch = StockBatch::factory()->create(['product_id' => $product->id, 'unit_cost' => 3060.35, 'status' => 'depleted']);
    $receipt = StockMovement::create([
        'movement_type' => 'grn', 'reference_type' => 'App\\Models\\GoodsReceiptNote', 'reference_id' => $batch->id,
        'movement_date' => now()->toDateString(), 'product_id' => $product->id, 'stock_batch_id' => $batch->id,
        'warehouse_id' => $warehouse->id, 'quantity' => 240, 'uom_id' => $uom->id, 'unit_cost' => 360.35,
        'total_value' => 86484, 'created_by' => User::factory()->create()->id,
    ]);
    $layer = StockValuationLayer::create([
        'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'stock_batch_id' => $batch->id, 'stock_movement_id' => $receipt->id,
        'receipt_date' => now()->toDateString(), 'quantity_received' => 240, 'quantity_remaining' => $onHand,
        'unit_cost' => 3060.35, 'total_value' => 86484, 'value_remaining' => $onHand * 3060.35, 'priority_order' => 99,
    ]);
    CurrentStockByBatch::create([
        'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'stock_batch_id' => $batch->id,
        'quantity_on_hand' => $onHand, 'unit_cost' => 3060.35, 'total_value' => $onHand * 3060.35,
    ]);

    return ['batch' => $batch, 'layer' => $layer];
}

it('changes nothing on a dry run', function () {
    ['batch' => $batch] = mistypedEmptyBatch();

    $this->artisan('stock:correct-depleted-batch-cost', ['batch' => $batch->batch_code, 'cost' => 360.35, '--dry-run' => true])
        ->expectsOutputToContain('DRY RUN')->assertSuccessful();

    expect((float) $batch->fresh()->unit_cost)->toBe(3060.35);
});

it('puts the cost of an empty batch right on the batch, its layers and current stock rows', function () {
    ['batch' => $batch, 'layer' => $layer] = mistypedEmptyBatch();

    $this->artisan('stock:correct-depleted-batch-cost', ['batch' => (string) $batch->id, 'cost' => 360.35])->assertSuccessful();

    expect((float) $batch->fresh()->unit_cost)->toBe(360.35)
        ->and((float) $layer->fresh()->unit_cost)->toBe(360.35)
        ->and((float) $layer->fresh()->value_remaining)->toBe(0.0)
        ->and((float) CurrentStockByBatch::where('stock_batch_id', $batch->id)->value('unit_cost'))->toBe(360.35);
});

it('refuses a batch that still holds stock, since that would change the stock value', function () {
    ['batch' => $batch] = mistypedEmptyBatch(onHand: 5);

    $this->artisan('stock:correct-depleted-batch-cost', ['batch' => $batch->batch_code, 'cost' => 360.35])
        ->expectsOutputToContain('still holds stock')->assertFailed();

    expect((float) $batch->fresh()->unit_cost)->toBe(3060.35);
});
