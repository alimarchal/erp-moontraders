<?php

use App\Models\CurrentStockByBatch;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;

function createStockMovement(int $productId, int $batchId, int $warehouseId, int $uomId, int $userId): int
{
    return DB::table('stock_movements')->insertGetId([
        'movement_type' => 'grn',
        'movement_date' => now()->toDateString(),
        'product_id' => $productId,
        'stock_batch_id' => $batchId,
        'warehouse_id' => $warehouseId,
        'quantity' => 50,
        'uom_id' => $uomId,
        'unit_cost' => 80.00,
        'total_value' => 4000.00,
        'created_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function () {
    Permission::create(['name' => 'product-edit']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('product-edit');
    $this->actingAs($this->user);

    $this->uom = Uom::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create([
        'uom_id' => $this->uom->id,
        'unit_sell_price' => 100.00,
        'valuation_method' => 'FIFO',
    ]);
});

it('cascades selling price to active non-promotional batches when unit_sell_price changes', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'selling_price' => 100.00,
        'is_promotional' => false,
        'status' => 'active',
    ]);

    $grn = GoodsReceiptNote::factory()->create(['warehouse_id' => $this->warehouse->id, 'supplier_id' => $this->supplier->id]);
    $grnItem = GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $grn->id,
        'product_id' => $this->product->id,
        'selling_price' => 100.00,
        'is_promotional' => false,
        'line_no' => 1,
    ]);

    $smId = createStockMovement($this->product->id, $batch->id, $this->warehouse->id, $this->uom->id, $this->user->id);

    DB::table('stock_valuation_layers')->insert([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'stock_movement_id' => $smId,
        'grn_item_id' => $grnItem->id,
        'receipt_date' => now()->toDateString(),
        'quantity_received' => 50,
        'quantity_remaining' => 30,
        'unit_cost' => 80.00,
        'is_depleted' => false,
        'is_promotional' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 30,
        'unit_cost' => 80.00,
        'selling_price' => 100.00,
        'is_promotional' => false,
        'status' => 'active',
        'last_updated' => now(),
    ]);

    $this->put(route('products.update', $this->product), [
        'product_code' => $this->product->product_code,
        'product_name' => $this->product->product_name,
        'uom_id' => $this->uom->id,
        'valuation_method' => 'FIFO',
        'unit_sell_price' => 150.00,
    ])->assertRedirect(route('products.index'));

    expect((float) $batch->fresh()->selling_price)->toBe(150.00);
    expect((float) $grnItem->fresh()->selling_price)->toBe(150.00);
    expect((float) CurrentStockByBatch::where('stock_batch_id', $batch->id)->first()->selling_price)->toBe(150.00);
});

it('does not change promotional batch selling prices', function () {
    $promoBatch = StockBatch::factory()->promotional()->create([
        'product_id' => $this->product->id,
        'selling_price' => 100.00,
        'promotional_selling_price' => 80.00,
        'status' => 'active',
    ]);

    $regularBatch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'selling_price' => 100.00,
        'is_promotional' => false,
        'status' => 'active',
    ]);

    // Only the regular batch has non-promotional stock available
    DB::table('stock_valuation_layers')->insert([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $regularBatch->id,
        'stock_movement_id' => DB::table('stock_movements')->insertGetId([
            'movement_type' => 'grn', 'movement_date' => now()->toDateString(),
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'quantity' => 50, 'uom_id' => $this->uom->id, 'unit_cost' => 80,
            'total_value' => 4000, 'created_by' => $this->user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]),
        'receipt_date' => now()->toDateString(),
        'quantity_received' => 50, 'quantity_remaining' => 50,
        'unit_cost' => 80.00, 'is_depleted' => false, 'is_promotional' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->put(route('products.update', $this->product), [
        'product_code' => $this->product->product_code,
        'product_name' => $this->product->product_name,
        'uom_id' => $this->uom->id,
        'valuation_method' => 'FIFO',
        'unit_sell_price' => 200.00,
    ])->assertRedirect(route('products.index'));

    expect((float) $promoBatch->fresh()->selling_price)->toBe(100.00);
    expect((float) $promoBatch->fresh()->promotional_selling_price)->toBe(80.00);
    expect((float) $regularBatch->fresh()->selling_price)->toBe(200.00);
});

it('does not change batch selling prices when no stock remains', function () {
    $depletedBatch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'selling_price' => 100.00,
        'is_promotional' => false,
        'status' => 'active',
    ]);

    $activeBatch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'selling_price' => 100.00,
        'is_promotional' => false,
        'status' => 'active',
    ]);

    // Depleted batch — quantity_remaining = 0
    DB::table('stock_valuation_layers')->insert([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $depletedBatch->id,
        'stock_movement_id' => DB::table('stock_movements')->insertGetId([
            'movement_type' => 'grn', 'movement_date' => now()->toDateString(),
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'quantity' => 50, 'uom_id' => $this->uom->id, 'unit_cost' => 80,
            'total_value' => 4000, 'created_by' => $this->user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]),
        'receipt_date' => now()->toDateString(),
        'quantity_received' => 50, 'quantity_remaining' => 0,
        'unit_cost' => 80.00, 'is_depleted' => true, 'is_promotional' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Active batch — has stock remaining
    DB::table('stock_valuation_layers')->insert([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $activeBatch->id,
        'stock_movement_id' => DB::table('stock_movements')->insertGetId([
            'movement_type' => 'grn', 'movement_date' => now()->toDateString(),
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'quantity' => 50, 'uom_id' => $this->uom->id, 'unit_cost' => 80,
            'total_value' => 4000, 'created_by' => $this->user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]),
        'receipt_date' => now()->toDateString(),
        'quantity_received' => 50, 'quantity_remaining' => 30,
        'unit_cost' => 80.00, 'is_depleted' => false, 'is_promotional' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->put(route('products.update', $this->product), [
        'product_code' => $this->product->product_code,
        'product_name' => $this->product->product_name,
        'uom_id' => $this->uom->id,
        'valuation_method' => 'FIFO',
        'unit_sell_price' => 250.00,
    ])->assertRedirect(route('products.index'));

    expect((float) $depletedBatch->fresh()->selling_price)->toBe(100.00);
    expect((float) $activeBatch->fresh()->selling_price)->toBe(250.00);
});

it('does not cascade when unit_sell_price is unchanged', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'selling_price' => 90.00,
        'is_promotional' => false,
        'status' => 'active',
    ]);

    $this->put(route('products.update', $this->product), [
        'product_code' => $this->product->product_code,
        'product_name' => $this->product->product_name,
        'uom_id' => $this->uom->id,
        'valuation_method' => 'FIFO',
        'unit_sell_price' => 100.00,
    ])->assertRedirect(route('products.index'));

    expect((float) $batch->fresh()->selling_price)->toBe(90.00);
});

it('cascades to batches when stock exists only in current_stock_by_batch', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'selling_price' => 100.00,
        'is_promotional' => false,
        'status' => 'active',
    ]);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 12,
        'unit_cost' => 80.00,
        'selling_price' => 100.00,
        'is_promotional' => false,
        'status' => 'active',
        'last_updated' => now(),
    ]);

    $this->put(route('products.update', $this->product), [
        'product_code' => $this->product->product_code,
        'product_name' => $this->product->product_name,
        'uom_id' => $this->uom->id,
        'valuation_method' => 'FIFO',
        'unit_sell_price' => 358.89,
    ])->assertRedirect(route('products.index'));

    expect((float) $batch->fresh()->selling_price)->toBe(358.89);
    expect((float) CurrentStockByBatch::where('stock_batch_id', $batch->id)->first()->selling_price)->toBe(358.89);
});

it('only updates grn items with active stock valuation layers', function () {
    $grn = GoodsReceiptNote::factory()->create(['warehouse_id' => $this->warehouse->id, 'supplier_id' => $this->supplier->id]);

    $activeGrnItem = GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $grn->id,
        'product_id' => $this->product->id,
        'selling_price' => 100.00,
        'is_promotional' => false,
        'line_no' => 1,
    ]);

    $depletedGrnItem = GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $grn->id,
        'product_id' => $this->product->id,
        'selling_price' => 100.00,
        'is_promotional' => false,
        'line_no' => 2,
    ]);

    $activeBatch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'selling_price' => 100.00,
        'is_promotional' => false,
        'status' => 'active',
    ]);

    $smId1 = createStockMovement($this->product->id, $activeBatch->id, $this->warehouse->id, $this->uom->id, $this->user->id);
    $smId2 = createStockMovement($this->product->id, $activeBatch->id, $this->warehouse->id, $this->uom->id, $this->user->id);

    DB::table('stock_valuation_layers')->insert([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $activeBatch->id,
        'stock_movement_id' => $smId1,
        'grn_item_id' => $activeGrnItem->id,
        'receipt_date' => now()->toDateString(),
        'quantity_received' => 50,
        'quantity_remaining' => 20,
        'unit_cost' => 80.00,
        'is_depleted' => false,
        'is_promotional' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('stock_valuation_layers')->insert([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $activeBatch->id,
        'stock_movement_id' => $smId2,
        'grn_item_id' => $depletedGrnItem->id,
        'receipt_date' => now()->toDateString(),
        'quantity_received' => 50,
        'quantity_remaining' => 0,
        'unit_cost' => 80.00,
        'is_depleted' => true,
        'is_promotional' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->put(route('products.update', $this->product), [
        'product_code' => $this->product->product_code,
        'product_name' => $this->product->product_name,
        'uom_id' => $this->uom->id,
        'valuation_method' => 'FIFO',
        'unit_sell_price' => 175.00,
    ])->assertRedirect(route('products.index'));

    expect((float) $activeGrnItem->fresh()->selling_price)->toBe(175.00);
    expect((float) $depletedGrnItem->fresh()->selling_price)->toBe(100.00);
});

it('does not change unit_cost when selling price cascades', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'selling_price' => 100.00,
        'unit_cost' => 70.00,
        'is_promotional' => false,
        'status' => 'active',
    ]);

    $smId = createStockMovement($this->product->id, $batch->id, $this->warehouse->id, $this->uom->id, $this->user->id);

    DB::table('stock_valuation_layers')->insert([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'stock_movement_id' => $smId,
        'receipt_date' => now()->toDateString(),
        'quantity_received' => 50,
        'quantity_remaining' => 30,
        'unit_cost' => 70.00,
        'is_depleted' => false,
        'is_promotional' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->put(route('products.update', $this->product), [
        'product_code' => $this->product->product_code,
        'product_name' => $this->product->product_name,
        'uom_id' => $this->uom->id,
        'valuation_method' => 'FIFO',
        'unit_sell_price' => 300.00,
    ])->assertRedirect(route('products.index'));

    $fresh = $batch->fresh();
    expect((float) $fresh->selling_price)->toBe(300.00);
    expect((float) $fresh->unit_cost)->toBe(70.00);
});
