<?php

use App\Models\CurrentStockByBatch;
use App\Models\DailyInventorySnapshot;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\Warehouse;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->travelTo('2026-09-21 23:45:00');

    $this->warehouse = Warehouse::factory()->create(['disabled' => false]);
    $this->supplier = Supplier::factory()->create(['disabled' => false]);
    $this->product = Product::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'unit_cost' => 100,
    ]);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $this->batch->id,
        'quantity_on_hand' => 50,
        'unit_cost' => 100,
        'selling_price' => 125,
        'total_value' => 5000,
        'is_promotional' => false,
        'priority_order' => 99,
        'status' => 'active',
        'last_updated' => now(),
    ]);
});

it('records the current position under today when no date is given', function () {
    $this->artisan('inventory:snapshot')->assertSuccessful();

    assertDatabaseHas('daily_inventory_snapshots', [
        'date' => '2026-09-21',
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => 50,
    ]);
});

it('refuses a past date and points at the rebuild command', function () {
    DailyInventorySnapshot::create([
        'date' => '2026-06-15',
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => 800,
        'average_cost' => 100,
        'total_value' => 80000,
    ]);

    $this->artisan('inventory:snapshot', ['date' => '2026-06-15'])
        ->expectsOutputToContain('inventory:snapshots:rebuild 2026-06-15 2026-06-15')
        ->assertFailed();

    // The stored history for that date is left exactly as it was.
    assertDatabaseHas('daily_inventory_snapshots', [
        'date' => '2026-06-15',
        'quantity_on_hand' => 800,
    ]);
    assertDatabaseCount('daily_inventory_snapshots', 1);
});

it('labels the current position with a past date when forced', function () {
    $this->artisan('inventory:snapshot', ['date' => '2026-06-15', '--force' => true])
        ->assertSuccessful();

    assertDatabaseHas('daily_inventory_snapshots', [
        'date' => '2026-06-15',
        'product_id' => $this->product->id,
        'quantity_on_hand' => 50,
    ]);
});

it('rejects a date that is not in Y-m-d format', function () {
    $this->artisan('inventory:snapshot', ['date' => '15-06-2026'])->assertFailed();

    assertDatabaseCount('daily_inventory_snapshots', 0);
});
