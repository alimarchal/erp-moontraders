<?php

use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function productWiseSale(string $productName, ?Supplier $supplier, float $sales, float $cogs): void
{
    $product = Product::factory()->create(['product_name' => $productName, 'supplier_id' => $supplier?->id]);
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

    $this->get(route('reports.daily-sales.product-wise', ['columns' => 'avg_price,returned']))
        ->assertViewHas('visibleColumns', ['avg_price', 'returned']);
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

test('pdf contains only the visible columns and the filtered rows', function () {
    $html = fn (array $query) => view('reports.daily-sales.pdf.product-wise', $this->get(route('reports.daily-sales.product-wise', $query))->viewData('productSales') instanceof Collection
        ? [
            'productSales' => $this->get(route('reports.daily-sales.product-wise', $query))->viewData('productSales'),
            'totals' => $this->get(route('reports.daily-sales.product-wise', $query))->viewData('totals'),
            'visibleColumns' => $this->get(route('reports.daily-sales.product-wise', $query))->viewData('visibleColumns'),
            'startDate' => now()->toDateString(), 'endDate' => now()->toDateString(), 'filterLines' => [],
        ] : [])->render();

    $default = $html(['supplier_id' => $this->supplierA->id]);
    expect($default)->toContain('Alpha Biscuit')->not->toContain('Beta Juice')
        ->toContain('Net Sales')->toContain('Net Profit')->toContain('NP %')
        ->not->toContain('Avg Price')->not->toContain('Rtn Qty')->not->toContain('Short Qty');

    $custom = $html(['columns' => 'avg_price,shortage']);
    expect($custom)->toContain('Avg Price')->toContain('Short Qty')->not->toContain('Net Sales')->not->toContain('Net Profit');
});

test('previous month comparison uses the same range one month back', function () {
    $product = Product::where('product_name', 'Alpha Biscuit')->first();
    $old = SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => now()->subMonthNoOverflow(), 'total_sales_amount' => 800]);
    SalesSettlementItem::factory()->create(['sales_settlement_id' => $old->id, 'product_id' => $product->id, 'goods_issue_item_id' => null, 'total_sales_value' => 800]);

    $row = $this->get(route('reports.daily-sales.product-wise', ['columns' => 'prev_sales,change,change_pct,category,branch']))
        ->assertSuccessful()->viewData('productSales')->firstWhere('product_name', 'Alpha Biscuit');

    expect($row->previous_sales)->toBe(800.0)->and($row->sales_change)->toBe(200.0)->and(round($row->sales_change_pct, 2))->toBe(25.0);
});

test('category branch and comparison columns are hidden by default', function () {
    $this->get(route('reports.daily-sales.product-wise'))
        ->assertViewHas('visibleColumns', fn ($c) => array_intersect($c, ['category', 'branch', 'avg_price', 'prev_sales', 'change', 'change_pct']) === []);
});
