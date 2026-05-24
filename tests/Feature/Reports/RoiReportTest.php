<?php

use App\Models\GoodsIssue;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementItem;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('report-sales-roi');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-sales-roi');
    $this->actingAs($this->user);
});

it('does not load roi rows before a supplier is selected', function () {
    $supplier = Supplier::factory()->create([
        'supplier_name' => 'Nestle Pakistan',
        'disabled' => false,
    ]);

    $product = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'product_name' => 'Nestle Default Product',
        'is_active' => true,
    ]);

    createRoiSettlement($supplier, $product);

    $response = $this->get(route('reports.roi.index'));

    $response->assertSuccessful()
        ->assertSee('Select a supplier and apply filters to load report.');

    expect($response->viewData('hasReportData'))->toBeFalse()
        ->and($response->viewData('matrixData')['products'])->toHaveCount(0)
        ->and($response->viewData('filters'))->not->toHaveKey('supplier_id');
});

it('loads roi rows for the selected nestle supplier', function () {
    $supplier = Supplier::factory()->create([
        'supplier_name' => 'Nestle Pakistan',
        'disabled' => false,
    ]);

    $product = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'product_name' => 'Nestle Selected Product',
        'is_active' => true,
    ]);

    createRoiSettlement($supplier, $product);

    $response = $this->get(route('reports.roi.index', [
        'filter' => [
            'supplier_id' => $supplier->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
        ],
    ]));

    $response->assertSuccessful()
        ->assertSee('Nestle Selected Product');

    expect($response->viewData('hasReportData'))->toBeTrue()
        ->and($response->viewData('matrixData')['products'])->toHaveCount(1);
});

it('only loads roi rows for the selected supplier', function () {
    $nestle = Supplier::factory()->create([
        'supplier_name' => 'Nestle Pakistan',
        'disabled' => false,
    ]);
    $otherSupplier = Supplier::factory()->create([
        'supplier_name' => 'Other Company',
        'disabled' => false,
    ]);

    $nestleProduct = Product::factory()->create([
        'supplier_id' => $nestle->id,
        'product_name' => 'Nestle Hidden Row',
        'is_active' => true,
    ]);
    $otherProduct = Product::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'product_name' => 'Other Visible Row',
        'is_active' => true,
    ]);

    createRoiSettlement($nestle, $nestleProduct);
    createRoiSettlement($otherSupplier, $otherProduct);

    $response = $this->get(route('reports.roi.index', [
        'filter' => [
            'supplier_id' => $otherSupplier->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
        ],
    ]));

    $productNames = $response->viewData('matrixData')['products']->pluck('product_name');

    $response->assertSuccessful();
    expect($productNames)->toContain('Other Visible Row')
        ->and($productNames)->not->toContain('Nestle Hidden Row');
});

it('shows product cost price as cp while keeping ip and tp settlement averages', function () {
    $supplier = Supplier::factory()->create(['disabled' => false]);
    $product = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'product_name' => 'Price Formula Product',
        'cost_price' => 123.45,
        'is_active' => true,
    ]);

    createRoiSettlement(
        supplier: $supplier,
        product: $product,
        quantitySold: 5,
        totalSalesValue: 500,
        totalCogs: 200,
    );

    $response = $this->get(route('reports.roi.index', [
        'filter' => [
            'supplier_id' => $supplier->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
        ],
    ]));

    $row = $response->viewData('matrixData')['products']->first();

    $response->assertSuccessful()
        ->assertSee('CP')
        ->assertSee('123.45');

    expect((float) $row['ip'])->toBe(40.0)
        ->and((float) $row['cp'])->toBe(123.45)
        ->and((float) $row['tp'])->toBe(100.0);
});

function createRoiSettlement(
    Supplier $supplier,
    Product $product,
    int $quantitySold = 10,
    int $totalSalesValue = 1000,
    int $totalCogs = 600,
): SalesSettlement {
    $settlement = SalesSettlement::factory()->create([
        'goods_issue_id' => GoodsIssue::factory()->create(['supplier_id' => $supplier->id])->id,
        'supplier_id' => $supplier->id,
        'settlement_date' => '2026-05-10',
        'status' => 'posted',
    ]);

    SalesSettlementItem::factory()->create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $product->id,
        'quantity_sold' => $quantitySold,
        'unit_selling_price' => $totalSalesValue / $quantitySold,
        'total_sales_value' => $totalSalesValue,
        'unit_cost' => $totalCogs / $quantitySold,
        'total_cogs' => $totalCogs,
    ]);

    return $settlement;
}
