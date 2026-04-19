<?php

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementAmrLiquid;
use App\Models\SalesSettlementAmrPowder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'report-sales-fmr-amr-comparison']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-sales-fmr-amr-comparison');

    // Create accounting periods for the test years
    AccountingPeriod::factory()->create([
        'name' => 'FY 2025',
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
        'status' => 'open',
    ]);

    AccountingPeriod::factory()->create([
        'name' => 'FY 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'status' => 'open',
    ]);

    $this->currency = Currency::factory()->create();
    $this->accountType = AccountType::factory()->create([
        'type_name' => 'Expense',
        'report_group' => 'BalanceSheet',
    ]);

    // Seed Chart of Accounts
    ChartOfAccount::factory()->create(['account_code' => '4210', 'account_name' => 'FMR Liquid', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    ChartOfAccount::factory()->create(['account_code' => '4220', 'account_name' => 'FMR Powder', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    ChartOfAccount::factory()->create(['account_code' => '5262', 'account_name' => 'AMR Liquid', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    ChartOfAccount::factory()->create(['account_code' => '5252', 'account_name' => 'AMR Powder', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    ChartOfAccount::factory()->create(['account_code' => '1110', 'account_name' => 'Cash', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
});

test('fmr amr comparison report page loads for authenticated user with empty state', function () {
    $this->actingAs($this->user)
        ->get(route('reports.fmr-amr-comparison.index'))
        ->assertSuccessful()
        ->assertSee('Please select a supplier and date range');
});

test('fmr amr comparison report loads with date filters', function () {
    $this->actingAs($this->user)
        ->get(route('reports.fmr-amr-comparison.index', [
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]))
        ->assertSuccessful();
});

test('fmr amr comparison report requires authentication', function () {
    $this->get(route('reports.fmr-amr-comparison.index'))
        ->assertRedirect(route('login'));
});

test('fmr amr comparison shows fmr from grn items directly', function () {
    $this->actingAs($this->user);

    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $liquidProduct = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'is_powder' => false,
    ]);

    $powderProduct = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'is_powder' => true,
    ]);

    // Create a posted GRN
    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'receipt_date' => '2025-06-15',
        'status' => 'posted',
    ]);

    // Create GRN items with FMR allowance
    GoodsReceiptNoteItem::factory()->create([
        'goods_receipt_note_id' => $grn->id,
        'product_id' => $liquidProduct->id,
        'fmr_allowance' => 1000,
    ]);

    GoodsReceiptNoteItem::factory()->create([
        'goods_receipt_note_id' => $grn->id,
        'product_id' => $powderProduct->id,
        'fmr_allowance' => 500,
    ]);

    // Create a posted sales settlement with AMR data
    $settlement = SalesSettlement::factory()->create([
        'settlement_date' => '2025-06-20',
        'status' => 'posted',
        'warehouse_id' => $warehouse->id,
        'vehicle_id' => Vehicle::factory(),
        'employee_id' => Employee::factory(),
    ]);

    SalesSettlementAmrLiquid::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $liquidProduct->id,
        'quantity' => 10,
        'amount' => 700,
    ]);

    SalesSettlementAmrPowder::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $powderProduct->id,
        'quantity' => 10,
        'amount' => 300,
    ]);

    $response = $this->get(route('reports.fmr-amr-comparison.index', [
        'supplier_id' => $supplier->id,
        'start_date' => '2025-06-01',
        'end_date' => '2025-06-30',
    ]));

    $response->assertSuccessful();

    $reportData = $response->viewData('reportData');
    $grandTotals = $response->viewData('grandTotals');

    expect((float) $grandTotals->fmr_liquid_total)->toBe(1000.0)
        ->and((float) $grandTotals->fmr_powder_total)->toBe(500.0)
        ->and((float) $grandTotals->amr_liquid_total)->toBe(700.0)
        ->and((float) $grandTotals->amr_powder_total)->toBe(300.0)
        ->and((float) $grandTotals->difference)->toBe(500.0);
});

test('fmr amr comparison shows all dates in range including empty ones', function () {
    $this->actingAs($this->user);

    $response = $this->get(route('reports.fmr-amr-comparison.index', [
        'start_date' => '2025-06-01',
        'end_date' => '2025-06-03',
    ]));

    $response->assertSuccessful();

    $reportData = $response->viewData('reportData');

    expect($reportData)->toHaveCount(3)
        ->and($reportData[0]->date)->toBe('01-Jun-2025')
        ->and($reportData[1]->date)->toBe('02-Jun-2025')
        ->and($reportData[2]->date)->toBe('03-Jun-2025')
        ->and($reportData[0]->is_empty)->toBeTrue()
        ->and($reportData[1]->is_empty)->toBeTrue()
        ->and($reportData[2]->is_empty)->toBeTrue();
});

test('fmr amr comparison report handles supplier filter correctly', function () {
    $this->actingAs($this->user);

    $supplier = Supplier::factory()->create();

    $response = $this->get(route('reports.fmr-amr-comparison.index', [
        'source' => 'all',
        'supplier_id' => $supplier->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
    ]));

    $response->assertSuccessful();
});
