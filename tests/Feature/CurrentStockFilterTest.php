<?php

use App\Models\Category;
use App\Models\CurrentStock;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'report-view-inventory']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-view-inventory');
    $this->actingAs($this->user);

    $this->category = Category::create(['name' => 'Beverages', 'slug' => 'beverages']);
    $this->supplier = Supplier::factory()->create(['supplier_name' => 'Nestle']);
    $this->uom = Uom::factory()->create();
    $this->warehouse = Warehouse::factory()->create(['warehouse_name' => 'Main']);
    $this->warehouseB = Warehouse::factory()->create(['warehouse_name' => 'Branch']);

    $this->productA = Product::factory()->create([
        'product_name' => 'Alpha Juice',
        'product_code' => 'AJ-001',
        'barcode' => '1234567890123',
        'category_id' => $this->category->id,
        'supplier_id' => $this->supplier->id,
        'uom_id' => $this->uom->id,
    ]);

    $this->productB = Product::factory()->create([
        'product_name' => 'Beta Milk',
        'product_code' => 'BM-002',
        'category_id' => Category::create(['name' => 'Dairy', 'slug' => 'dairy'])->id,
        'supplier_id' => Supplier::factory()->create(['supplier_name' => 'Engro'])->id,
        'uom_id' => $this->uom->id,
    ]);

    $this->stockA = CurrentStock::create([
        'product_id' => $this->productA->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => 150,
        'quantity_reserved' => 10,
        'average_cost' => 50.00,
        'total_value' => 7500.00,
        'total_batches' => 3,
        'promotional_batches' => 1,
        'priority_batches' => 0,
    ]);
    DB::table('current_stock')->where('id', $this->stockA->id)->update(['quantity_available' => 140]);

    $this->stockB = CurrentStock::create([
        'product_id' => $this->productB->id,
        'warehouse_id' => $this->warehouseB->id,
        'quantity_on_hand' => 5,
        'quantity_reserved' => 5,
        'average_cost' => 200.00,
        'total_value' => 1000.00,
        'total_batches' => 1,
        'promotional_batches' => 0,
        'priority_batches' => 1,
    ]);
    DB::table('current_stock')->where('id', $this->stockB->id)->update(['quantity_available' => 0]);
});

it('displays current stock index page', function () {
    $response = $this->get(route('inventory.current-stock.index'));
    $response->assertSuccessful();

    $stocks = $response->viewData('stocks');
    expect($stocks->pluck('product_id')->toArray())
        ->toContain($this->productA->id)
        ->toContain($this->productB->id);
});

it('filters by product', function () {
    $response = $this->get(route('inventory.current-stock.index', ['filter[product_id]' => $this->productA->id]));
    $response->assertSuccessful();

    $stocks = $response->viewData('stocks');
    expect($stocks)->toHaveCount(1);
    expect($stocks->first()->product_id)->toBe($this->productA->id);
});

it('filters by warehouse', function () {
    $response = $this->get(route('inventory.current-stock.index', ['filter[warehouse_id]' => $this->warehouse->id]));
    $response->assertSuccessful();

    $stocks = $response->viewData('stocks');
    expect($stocks)->toHaveCount(1);
    expect($stocks->first()->warehouse_id)->toBe($this->warehouse->id);
});

it('filters by category', function () {
    $response = $this->get(route('inventory.current-stock.index', ['filter[category_id]' => $this->category->id]));
    $response->assertSuccessful();

    $stocks = $response->viewData('stocks');
    expect($stocks)->toHaveCount(1);
    expect($stocks->first()->product_id)->toBe($this->productA->id);
});

it('filters by supplier', function () {
    $response = $this->get(route('inventory.current-stock.index', ['filter[supplier_id]' => $this->supplier->id]));
    $response->assertSuccessful();

    $stocks = $response->viewData('stocks');
    expect($stocks)->toHaveCount(1);
    expect($stocks->first()->product_id)->toBe($this->productA->id);
});

it('filters promotional only', function () {
    $response = $this->get(route('inventory.current-stock.index', ['filter[has_promotional]' => '1']));
    $response->assertSuccessful();

    $stocks = $response->viewData('stocks');
    expect($stocks)->toHaveCount(1);
    expect($stocks->first()->product_id)->toBe($this->productA->id);
});

it('filters priority batches', function () {
    $response = $this->get(route('inventory.current-stock.index', ['filter[has_priority]' => '1']));
    $response->assertSuccessful();

    $stocks = $response->viewData('stocks');
    expect($stocks)->toHaveCount(1);
    expect($stocks->first()->product_id)->toBe($this->productB->id);
});

it('filters low stock level', function () {
    $response = $this->get(route('inventory.current-stock.index', ['filter[stock_level]' => 'low']));
    $response->assertSuccessful();

    $stocks = $response->viewData('stocks');
    expect($stocks)->toHaveCount(1);
    expect($stocks->first()->product_id)->toBe($this->productB->id);
});

it('filters high stock level', function () {
    $response = $this->get(route('inventory.current-stock.index', ['filter[stock_level]' => 'high']));
    $response->assertSuccessful();

    $stocks = $response->viewData('stocks');
    expect($stocks)->toHaveCount(1);
    expect($stocks->first()->product_id)->toBe($this->productA->id);
});

it('filters zero available stock', function () {
    $response = $this->get(route('inventory.current-stock.index', ['filter[stock_level]' => 'zero_available']));
    $response->assertSuccessful();

    $stocks = $response->viewData('stocks');
    expect($stocks)->toHaveCount(1);
    expect($stocks->first()->product_id)->toBe($this->productB->id);
});

it('searches by product name', function () {
    $response = $this->get(route('inventory.current-stock.index', ['filter[search]' => 'Alpha']));
    $response->assertSuccessful();

    $stocks = $response->viewData('stocks');
    expect($stocks)->toHaveCount(1);
    expect($stocks->first()->product_id)->toBe($this->productA->id);
});

it('searches by product code', function () {
    $response = $this->get(route('inventory.current-stock.index', ['filter[search]' => 'AJ-001']));
    $response->assertSuccessful();

    $stocks = $response->viewData('stocks');
    expect($stocks)->toHaveCount(1);
    expect($stocks->first()->product_id)->toBe($this->productA->id);
});

it('searches by barcode', function () {
    $response = $this->get(route('inventory.current-stock.index', ['filter[search]' => '1234567890123']));
    $response->assertSuccessful();

    $stocks = $response->viewData('stocks');
    expect($stocks)->toHaveCount(1);
    expect($stocks->first()->product_id)->toBe($this->productA->id);
});

it('sorts by quantity on hand descending', function () {
    $response = $this->get(route('inventory.current-stock.index', ['sort' => '-quantity_on_hand']));
    $response->assertSuccessful();

    $stocks = $response->viewData('stocks');
    expect($stocks->first()->product_id)->toBe($this->productA->id);
    expect($stocks->last()->product_id)->toBe($this->productB->id);
});

it('sorts by quantity on hand ascending', function () {
    $response = $this->get(route('inventory.current-stock.index', ['sort' => 'quantity_on_hand']));
    $response->assertSuccessful();

    $stocks = $response->viewData('stocks');
    expect($stocks->first()->product_id)->toBe($this->productB->id);
    expect($stocks->last()->product_id)->toBe($this->productA->id);
});

it('sorts by total value descending', function () {
    $response = $this->get(route('inventory.current-stock.index', ['sort' => '-total_value']));
    $response->assertSuccessful();

    $stocks = $response->viewData('stocks');
    expect($stocks->first()->product_id)->toBe($this->productA->id);
    expect($stocks->last()->product_id)->toBe($this->productB->id);
});

it('sorts by total value ascending', function () {
    $response = $this->get(route('inventory.current-stock.index', ['sort' => 'total_value']));
    $response->assertSuccessful();

    $stocks = $response->viewData('stocks');
    expect($stocks->first()->product_id)->toBe($this->productB->id);
    expect($stocks->last()->product_id)->toBe($this->productA->id);
});

it('combines filters and sorting', function () {
    $response = $this->get(route('inventory.current-stock.index', [
        'filter[warehouse_id]' => $this->warehouse->id,
        'sort' => '-quantity_on_hand',
    ]));
    $response->assertSuccessful();

    $stocks = $response->viewData('stocks');
    expect($stocks)->toHaveCount(1);
    expect($stocks->first()->product_id)->toBe($this->productA->id);
});

it('passes categories and suppliers to view', function () {
    $response = $this->get(route('inventory.current-stock.index'));
    $response->assertSuccessful();
    $response->assertViewHas('categories');
    $response->assertViewHas('suppliers');
    $response->assertViewHas('products');
    $response->assertViewHas('warehouses');
});
