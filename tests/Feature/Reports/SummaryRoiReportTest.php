<?php

use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\GoodsIssue;
use App\Models\Product;
use App\Models\ProfitCategory;
use App\Models\ProfitCategoryDetail;
use App\Models\RevenueCategory;
use App\Models\RevenueDetail;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementExcessAmount;
use App\Models\SalesSettlementExpense;
use App\Models\SalesSettlementItem;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('report-sales-summary-roi');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-sales-summary-roi');
});

test('summary roi report does not inject fixed incentive and expiry rows for engro supplier', function () {
    $engroSupplier = Supplier::factory()->create([
        'supplier_name' => 'Engro Foods',
        'short_name' => 'Engro',
        'disabled' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.summary-roi.index', [
            'filter' => [
                'supplier_id' => $engroSupplier->id,
            ],
        ]));

    $response->assertSuccessful()
        ->assertDontSee('Incentive Claimed')
        ->assertDontSee('Expiry Claimed')
        ->assertDontSee('208,652.00')
        ->assertDontSee('260,000.00');

    expect($response->viewData())->not->toHaveKey('incentiveClaimed');
    expect($response->viewData())->not->toHaveKey('expiryClaimed');
});

test('summary roi report shows incentive and expiry when present in posted revenue categories', function () {
    $otherSupplier = Supplier::factory()->create([
        'supplier_name' => 'Nestle Pakistan',
        'short_name' => 'Nestle',
        'disabled' => false,
    ]);

    $incentiveCategory = RevenueCategory::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'name' => 'Incentive Claimed',
        'slug' => 'incentive-claimed',
    ]);
    $expiryCategory = RevenueCategory::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'name' => 'Expiry Claimed',
        'slug' => 'expiry-claimed',
    ]);

    RevenueDetail::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'revenue_category_id' => $incentiveCategory->id,
        'transaction_date' => '2026-05-10',
        'amount' => 1200,
        'posted_at' => '2026-05-10 10:00:00',
        'posted_by' => $this->user->id,
    ]);
    RevenueDetail::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'revenue_category_id' => $expiryCategory->id,
        'transaction_date' => '2026-05-11',
        'amount' => 800,
        'posted_at' => '2026-05-11 10:00:00',
        'posted_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.summary-roi.index', [
            'filter' => [
                'supplier_id' => $otherSupplier->id,
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
                'status' => 'posted',
            ],
        ]));

    $response->assertSuccessful()
        ->assertSee('Incentive Claimed')
        ->assertSee('Expiry Claimed')
        ->assertSee('1,200.00')
        ->assertSee('800.00');

    expect($response->viewData('postedRevenueTotal'))->toBe(2000.0);
});

test('summary roi report adjusts short amount by excess amount and hides 4250 for engro', function () {
    $engroSupplier = Supplier::factory()->create([
        'supplier_name' => 'Engro Foods',
        'short_name' => 'Engro',
        'disabled' => false,
    ]);

    $settlement = SalesSettlement::factory()->create([
        'supplier_id' => $engroSupplier->id,
        'status' => 'posted',
        'settlement_date' => now()->toDateString(),
    ]);

    $shortAmountAccount = ChartOfAccount::factory()->create([
        'account_code' => '5293',
        'account_name' => 'Short Amount',
    ]);

    SalesSettlementExpense::create([
        'sales_settlement_id' => $settlement->id,
        'expense_account_id' => $shortAmountAccount->id,
        'amount' => 1000.00,
        'expense_date' => now()->toDateString(),
        'description' => 'Short amount',
    ]);

    SalesSettlementExcessAmount::create([
        'sales_settlement_id' => $settlement->id,
        'amount' => 200.00,
        'description' => 'Cash excess',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.summary-roi.index', [
            'filter' => [
                'supplier_id' => $engroSupplier->id,
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'status' => 'posted',
            ],
        ]));

    $response->assertSuccessful();

    $breakdown = $response['expenseBreakdown'];
    $shortAmount = $breakdown->firstWhere('account_code', '5293');

    expect($shortAmount)->not->toBeNull('Short Amount should be in breakdown');
    expect((float) $shortAmount->total_amount)->toBe(-800.00);
    expect($shortAmount->account_name)->toContain('Excess Amount (4250)');
    expect($shortAmount->account_name)->toContain('Short Amount (5293)');
    expect($shortAmount->account_name)->toContain('200.00');
    expect($shortAmount->account_name)->toContain('1,000.00');
    expect($breakdown->contains(fn ($row) => $row->account_code === '4250'))->toBeFalse();
});

test('summary roi report includes posted revenue categories after rate increase profit', function () {
    $supplier = Supplier::factory()->create([
        'supplier_name' => 'Nestle Pakistan',
        'short_name' => 'Nestle',
        'disabled' => false,
    ]);
    $otherSupplier = Supplier::factory()->create([
        'supplier_name' => 'Other Supplier',
        'short_name' => 'Other',
        'disabled' => false,
    ]);

    $postedCategory = RevenueCategory::factory()->create([
        'supplier_id' => $supplier->id,
        'name' => 'Display Income',
        'slug' => 'display-income',
    ]);
    $unpostedCategory = RevenueCategory::factory()->create([
        'supplier_id' => $supplier->id,
        'name' => 'Unposted Income',
        'slug' => 'unposted-income',
    ]);
    $otherSupplierCategory = RevenueCategory::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'name' => 'Other Supplier Revenue',
        'slug' => 'other-supplier-revenue',
    ]);

    RevenueDetail::factory()->create([
        'supplier_id' => null,
        'revenue_category_id' => $postedCategory->id,
        'transaction_date' => '2026-05-10',
        'amount' => 1500,
        'posted_at' => '2026-05-10 10:00:00',
        'posted_by' => $this->user->id,
    ]);
    RevenueDetail::factory()->create([
        'supplier_id' => $supplier->id,
        'revenue_category_id' => $unpostedCategory->id,
        'transaction_date' => '2026-05-11',
        'amount' => 700,
        'posted_at' => null,
    ]);
    RevenueDetail::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'revenue_category_id' => $otherSupplierCategory->id,
        'transaction_date' => '2026-05-12',
        'amount' => 900,
        'posted_at' => '2026-05-12 10:00:00',
    ]);
    RevenueDetail::factory()->create([
        'supplier_id' => $supplier->id,
        'revenue_category_id' => $postedCategory->id,
        'transaction_date' => '2026-06-01',
        'amount' => 300,
        'posted_at' => '2026-06-01 10:00:00',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.summary-roi.index', [
            'filter' => [
                'supplier_id' => $supplier->id,
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
                'status' => 'posted',
            ],
        ]));

    $response->assertSuccessful()
        ->assertSee('Rate Increase Profit')
        ->assertSee('Display Income')
        ->assertSee('1,500.00')
        ->assertSee('Other Revenue')
        ->assertDontSee('Unposted Income')
        ->assertDontSee('Other Supplier Revenue');

    expect($response['postedRevenueTotal'])->toBe(1500.0);
    expect($response['grossInflow'])->toBe(1500.0);
    expect($response['grandRevenue'])->toBe(1500.0);
    expect($response['postedRevenueRows'])->toHaveCount(1);
    expect($response['postedRevenueRows']->first()['category_name'])->toBe('Display Income');
    expect(substr_count($response->getContent(), '1,500.00'))->toBeGreaterThanOrEqual(2);
});

test('summary roi report deducts posted profit category rows after profit before taxation', function () {
    $supplier = Supplier::factory()->create([
        'supplier_name' => 'Nestle Pakistan',
        'short_name' => 'Nestle',
        'disabled' => false,
    ]);
    $otherSupplier = Supplier::factory()->create([
        'supplier_name' => 'Other Supplier',
        'short_name' => 'Other',
        'disabled' => false,
    ]);

    $taxation = ProfitCategory::factory()->create([
        'supplier_id' => $supplier->id,
        'name' => 'Taxation',
        'slug' => 'taxation',
    ]);
    $withholdingTax = ProfitCategory::factory()->create([
        'supplier_id' => $supplier->id,
        'name' => 'With Holding Tax H25',
        'slug' => 'with-holding-tax-h25',
    ]);
    $otherSupplierCategory = ProfitCategory::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'name' => 'Other Supplier Tax',
        'slug' => 'other-supplier-tax',
    ]);

    ProfitCategoryDetail::factory()->create([
        'supplier_id' => $supplier->id,
        'profit_category_id' => $taxation->id,
        'transaction_date' => '2026-05-10',
        'amount' => 1000,
        'posted_at' => '2026-05-10 10:00:00',
        'posted_by' => $this->user->id,
    ]);
    ProfitCategoryDetail::factory()->create([
        'supplier_id' => $supplier->id,
        'profit_category_id' => $withholdingTax->id,
        'transaction_date' => '2026-05-11',
        'amount' => 500,
        'posted_at' => '2026-05-11 10:00:00',
        'posted_by' => $this->user->id,
    ]);
    ProfitCategoryDetail::factory()->create([
        'supplier_id' => $supplier->id,
        'profit_category_id' => $taxation->id,
        'transaction_date' => '2026-05-12',
        'amount' => 700,
        'posted_at' => null,
    ]);
    ProfitCategoryDetail::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'profit_category_id' => $otherSupplierCategory->id,
        'transaction_date' => '2026-05-12',
        'amount' => 900,
        'posted_at' => '2026-05-12 10:00:00',
    ]);
    ProfitCategoryDetail::factory()->create([
        'supplier_id' => $supplier->id,
        'profit_category_id' => $taxation->id,
        'transaction_date' => '2026-06-01',
        'amount' => 300,
        'posted_at' => '2026-06-01 10:00:00',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.summary-roi.index', [
            'filter' => [
                'supplier_id' => $supplier->id,
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
                'status' => 'posted',
            ],
        ]));

    $response->assertSuccessful()
        ->assertSee('Profit before Taxation')
        ->assertSee('Taxation')
        ->assertSee('With Holding Tax H25')
        ->assertSee('1,000.00')
        ->assertSee('500.00')
        ->assertSee('Profit after Taxation')
        ->assertDontSee('Other Supplier Tax')
        ->assertDontSee('900.00');

    expect($response['profitCategoryRows'])->toHaveCount(2);
    expect($response['profitCategoryTotal'])->toBe(1500.0);
    expect($response->getContent())->toContain('>-1,500.00<');
});

test('summary roi report profit from sale follows average filter mode', function () {
    $supplier = Supplier::factory()->create([
        'supplier_name' => 'Nestle Pakistan',
        'short_name' => 'Nestle',
        'disabled' => false,
    ]);

    $category = Category::query()->create([
        'name' => 'Milk Powder',
    ]);

    $product = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'category_id' => $category->id,
        'product_name' => 'Average Filter Product',
        'cost_price' => 100,
        'unit_sell_price' => 200,
        'is_active' => true,
    ]);

    $settlement = SalesSettlement::factory()->create([
        'goods_issue_id' => GoodsIssue::factory()->create(['supplier_id' => $supplier->id])->id,
        'supplier_id' => $supplier->id,
        'settlement_date' => '2026-05-10',
        'status' => 'posted',
    ]);

    SalesSettlementItem::factory()->create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $product->id,
        'quantity_sold' => 10,
        'unit_selling_price' => 150,
        'total_sales_value' => 1500,
        'unit_cost' => 90,
        'total_cogs' => 900,
    ]);

    $baseFilter = [
        'supplier_id' => $supplier->id,
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-31',
        'status' => 'posted',
    ];

    $defaultModeResponse = $this->actingAs($this->user)
        ->get(route('reports.summary-roi.index', [
            'filter' => [
                ...$baseFilter,
                'average' => '0',
            ],
        ]));

    $averageModeResponse = $this->actingAs($this->user)
        ->get(route('reports.summary-roi.index', [
            'filter' => [
                ...$baseFilter,
                'average' => '1',
            ],
        ]));

    $defaultModeResponse->assertSuccessful()
        ->assertSee('Average: No');

    $averageModeResponse->assertSuccessful()
        ->assertSee('Average: Yes');

    expect((float) $defaultModeResponse->viewData('grandTotals')['gross_profit'])->toBe(1000.0)
        ->and((float) $averageModeResponse->viewData('grandTotals')['gross_profit'])->toBe(600.0);
});
