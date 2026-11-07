<?php

use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function productWiseSale(string $productName, ?Supplier $supplier, float $sales, float $cogs): void
{
    $product = Product::factory()->create(['product_name' => $productName]);
    $settlement = SalesSettlement::factory()->create([
        'status' => 'posted',
        'supplier_id' => $supplier?->id,
        'total_sales_amount' => $sales,
        'expenses_claimed' => 0,
    ]);
    SalesSettlementItem::factory()->create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $product->id,
        'goods_issue_item_id' => null,
        'quantity_sold' => 10,
        'quantity_returned' => 2,
        'unit_selling_price' => $sales / 10,
        'total_sales_value' => $sales,
        'total_cogs' => $cogs,
    ]);
}

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'report-sales-daily-sales', 'guard_name' => 'web']);
    $this->actingAs(User::factory()->create()->givePermissionTo('report-sales-daily-sales'));
    $this->supplierA = Supplier::factory()->create();
    $this->supplierB = Supplier::factory()->create();
    productWiseSale('Alpha Biscuit', $this->supplierA, 1000, 800);
    productWiseSale('Beta Juice', $this->supplierB, 500, 400);
});

test('the report lists posted products and filters by supplier', function () {
    $names = fn ($response) => $response->viewData('productSales')->pluck('product_name')->all();

    expect($names($this->get(route('reports.daily-sales.product-wise'))))->toBe(['Alpha Biscuit', 'Beta Juice']);
    expect($names($this->get(route('reports.daily-sales.product-wise', ['supplier_id' => $this->supplierA->id]))))->toBe(['Alpha Biscuit']);
});

test('product name search narrows the rows', function () {
    $rows = $this->get(route('reports.daily-sales.product-wise', ['search' => 'juice']))->viewData('productSales');

    expect($rows->pluck('product_name')->all())->toBe(['Beta Juice']);
});

test('net columns are visible by default and return and short columns are not', function () {
    $this->get(route('reports.daily-sales.product-wise'))
        ->assertViewHas('visibleColumns', ['net_sales', 'net_profit', 'np_percent']);

    $this->get(route('reports.daily-sales.product-wise', ['columns' => 'returned,shortage']))
        ->assertViewHas('visibleColumns', ['returned', 'shortage']);
});

test('pdf downloads in both orientations and respects filters', function () {
    foreach (['portrait', 'landscape'] as $orientation) {
        $response = $this->get(route('reports.daily-sales.product-wise.pdf', ['orientation' => $orientation, 'supplier_id' => $this->supplierA->id]));
        $response->assertSuccessful()->assertHeader('content-type', 'application/pdf');
    }
});

test('excel downloads', function () {
    $this->get(route('reports.daily-sales.product-wise.excel', ['columns' => 'net_sales']))
        ->assertSuccessful()
        ->assertDownload();
});

test('the report needs the daily sales permission', function () {
    $this->actingAs(User::factory()->create());
    $this->get(route('reports.daily-sales.product-wise'))->assertForbidden();
});
