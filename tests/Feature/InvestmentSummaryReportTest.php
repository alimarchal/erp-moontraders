<?php

use App\Models\Customer;
use App\Models\CustomerEmployeeAccount;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\ExpenseDetail;
use App\Models\InvestmentOpeningBalance;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementAmrLiquid;
use App\Models\SalesSettlementAmrPowder;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'report-audit-investment-summary', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-audit-investment-summary');
    $this->actingAs($this->user);

    $this->supplier = Supplier::factory()->create([
        'supplier_name' => 'Nestlé Pakistan',
        'disabled' => false,
    ]);
    $this->user->forceFill(['supplier_id' => $this->supplier->id])->save();
});

it('loads the investment summary report page', function () {
    $response = $this->get(route('reports.investment-summary.index', [
        'supplier_id' => $this->supplier->id,
    ]));

    $response->assertOk();
});

it('does not default to nestle for users who can view all suppliers', function () {
    $this->user->forceFill([
        'supplier_id' => null,
        'is_super_admin' => 'Yes',
    ])->save();

    $response = $this->get(route('reports.investment-summary.index'));

    $response->assertOk();
    expect($response->viewData('supplierId'))->toBeNull();
    expect($response->viewData('hasSupplierSelection'))->toBeFalse();
    expect($response->viewData('selectedSupplier'))->toBeNull();
    expect($response->viewData('salesmanCreditData'))->toHaveCount(0);
    expect($response->viewData('stockAmount'))->toBe(0.0);
});

it('shows designation options before an all suppliers user applies a supplier filter', function () {
    Employee::factory()->create([
        'supplier_id' => $this->supplier->id,
        'designation' => 'Salesman',
    ]);

    $otherSupplier = Supplier::factory()->create(['disabled' => false]);

    Employee::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'designation' => 'Driver',
    ]);

    $this->user->forceFill([
        'supplier_id' => null,
        'is_super_admin' => 'Yes',
    ])->save();

    $response = $this->get(route('reports.investment-summary.index'));

    $response->assertOk();

    expect($response->viewData('designation'))->toBe('Salesman');
    expect($response->viewData('designations')->all())->toContain('Salesman', 'Driver');
});

it('blocks filtering investment summary by another supplier for scoped users', function () {
    $otherSupplier = Supplier::factory()->create(['disabled' => false]);

    $this->get(route('reports.investment-summary.index', [
        'supplier_id' => $otherSupplier->id,
    ]))->assertForbidden();
});

it('includes opening balance transactions on the same day in salesman credit', function () {
    $employee = Employee::factory()->create([
        'supplier_id' => $this->supplier->id,
        'designation' => 'Salesman',
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create(['is_active' => true]);

    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-TEST001',
        'customer_id' => $customer->id,
        'employee_id' => $employee->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);

    $today = now()->format('Y-m-d');

    CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => $today,
        'transaction_type' => 'opening_balance',
        'reference_number' => 'OCB-M-TEST001',
        'description' => 'Test opening balance',
        'debit' => 13000.00,
        'credit' => 0,
        'created_by' => $this->user->id,
    ]);

    $response = $this->get(route('reports.investment-summary.index', [
        'date' => $today,
        'supplier_id' => $this->supplier->id,
        'designation' => 'Salesman',
    ]));

    $response->assertOk();
    $response->assertSee('13,000.00');
});

it('does not double count opening balance on the next day', function () {
    $employee = Employee::factory()->create([
        'supplier_id' => $this->supplier->id,
        'designation' => 'Salesman',
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create(['is_active' => true]);

    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-TEST002',
        'customer_id' => $customer->id,
        'employee_id' => $employee->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);

    $today = now()->format('Y-m-d');
    $tomorrow = now()->addDay()->format('Y-m-d');

    CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => $today,
        'transaction_type' => 'opening_balance',
        'reference_number' => 'OCB-M-TEST002',
        'description' => 'Test opening balance',
        'debit' => 14000.00,
        'credit' => 0,
        'created_by' => $this->user->id,
    ]);

    // Same day: should appear in opening_credit
    $responseSameDay = $this->get(route('reports.investment-summary.index', [
        'date' => $today,
        'supplier_id' => $this->supplier->id,
        'designation' => 'Salesman',
    ]));
    $responseSameDay->assertOk();

    // Next day: should still appear (in opening_credit via date < tomorrow)
    $responseNextDay = $this->get(route('reports.investment-summary.index', [
        'date' => $tomorrow,
        'supplier_id' => $this->supplier->id,
        'designation' => 'Salesman',
    ]));
    $responseNextDay->assertOk();

    // Both should show 14,000.00 — not doubled
    $responseSameDay->assertSee('14,000.00');
    $responseNextDay->assertSee('14,000.00');
});

it('only includes expenses up to selected investment summary date', function () {
    ExpenseDetail::factory()->posted()->create([
        'supplier_id' => $this->supplier->id,
        'category' => 'stationary',
        'transaction_date' => '2026-05-25',
        'amount' => 1000,
        'debit' => 1000,
        'credit' => 0,
    ]);

    ExpenseDetail::factory()->posted()->create([
        'supplier_id' => $this->supplier->id,
        'category' => 'stationary',
        'transaction_date' => '2026-05-30',
        'amount' => 500,
        'debit' => 500,
        'credit' => 0,
    ]);

    $response = $this->get(route('reports.investment-summary.index', [
        'date' => '2026-05-25',
        'supplier_id' => $this->supplier->id,
        'designation' => 'Salesman',
    ]));

    $response->assertOk();

    expect($response->viewData('expenseCategoryTotals')['stationary'])->toBe(1000.0);
    expect($response->viewData('totalExpensesMonth'))->toBe(1000.0);
});

it('calculates month-to-date powder and liquid expiry up to selected date and excludes entries disposed by selected date', function () {
    $employee = Employee::factory()->create([
        'supplier_id' => $this->supplier->id,
        'designation' => 'Salesman',
        'is_active' => true,
    ]);

    $powderProduct = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'is_powder' => true,
        'is_active' => true,
    ]);

    $liquidProduct = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'is_powder' => false,
        'is_active' => true,
    ]);

    $aprilPosted = SalesSettlement::factory()->create([
        'supplier_id' => $this->supplier->id,
        'employee_id' => $employee->id,
        'settlement_date' => '2026-04-28',
        'status' => 'posted',
    ]);

    $mayPostedInRange = SalesSettlement::factory()->create([
        'supplier_id' => $this->supplier->id,
        'employee_id' => $employee->id,
        'settlement_date' => '2026-05-20',
        'status' => 'posted',
    ]);

    $mayDraftInRange = SalesSettlement::factory()->create([
        'supplier_id' => $this->supplier->id,
        'employee_id' => $employee->id,
        'settlement_date' => '2026-05-22',
        'status' => 'draft',
    ]);

    $mayPostedOutOfRange = SalesSettlement::factory()->create([
        'supplier_id' => $this->supplier->id,
        'employee_id' => $employee->id,
        'settlement_date' => '2026-05-30',
        'status' => 'posted',
    ]);

    SalesSettlementAmrPowder::create([
        'sales_settlement_id' => $aprilPosted->id,
        'product_id' => $powderProduct->id,
        'quantity' => 1,
        'amount' => 500,
        'is_disposed' => false,
    ]);

    SalesSettlementAmrLiquid::create([
        'sales_settlement_id' => $aprilPosted->id,
        'product_id' => $liquidProduct->id,
        'quantity' => 1,
        'amount' => 700,
        'is_disposed' => false,
    ]);

    SalesSettlementAmrPowder::create([
        'sales_settlement_id' => $mayPostedInRange->id,
        'product_id' => $powderProduct->id,
        'quantity' => 1,
        'amount' => 1000,
        'is_disposed' => true,
        'disposed_at' => '2026-05-21 10:00:00',
    ]);

    SalesSettlementAmrLiquid::create([
        'sales_settlement_id' => $mayPostedInRange->id,
        'product_id' => $liquidProduct->id,
        'quantity' => 1,
        'amount' => 2000,
        'is_disposed' => true,
        'disposed_at' => '2026-05-21 10:00:00',
    ]);

    SalesSettlementAmrPowder::create([
        'sales_settlement_id' => $mayPostedInRange->id,
        'product_id' => $powderProduct->id,
        'quantity' => 1,
        'amount' => 600,
        'is_disposed' => false,
    ]);

    SalesSettlementAmrLiquid::create([
        'sales_settlement_id' => $mayPostedInRange->id,
        'product_id' => $liquidProduct->id,
        'quantity' => 1,
        'amount' => 900,
        'is_disposed' => false,
    ]);

    SalesSettlementAmrPowder::create([
        'sales_settlement_id' => $mayDraftInRange->id,
        'product_id' => $powderProduct->id,
        'quantity' => 1,
        'amount' => 300,
        'is_disposed' => false,
    ]);

    SalesSettlementAmrLiquid::create([
        'sales_settlement_id' => $mayDraftInRange->id,
        'product_id' => $liquidProduct->id,
        'quantity' => 1,
        'amount' => 400,
        'is_disposed' => false,
    ]);

    SalesSettlementAmrPowder::create([
        'sales_settlement_id' => $mayPostedOutOfRange->id,
        'product_id' => $powderProduct->id,
        'quantity' => 1,
        'amount' => 800,
        'is_disposed' => false,
    ]);

    SalesSettlementAmrLiquid::create([
        'sales_settlement_id' => $mayPostedOutOfRange->id,
        'product_id' => $liquidProduct->id,
        'quantity' => 1,
        'amount' => 900,
        'is_disposed' => false,
    ]);

    $response = $this->get(route('reports.investment-summary.index', [
        'date' => '2026-05-26',
        'supplier_id' => $this->supplier->id,
        'designation' => 'Salesman',
    ]));

    $response->assertOk();

    expect($response->viewData('powderExpiry'))->toBe(600.0);
    expect($response->viewData('liquidExpiry'))->toBe(900.0);
});

it('uses recalculated last month main investment instead of stale opening balance snapshot value', function () {
    InvestmentOpeningBalance::create([
        'supplier_id' => $this->supplier->id,
        'date' => '2026-05-26',
        'description' => 'LAST_MONTH_MAIN_INVESTMENT',
        'amount' => 49633.84,
    ]);

    $response = $this->get(route('reports.investment-summary.index', [
        'date' => '2026-05-26',
        'supplier_id' => $this->supplier->id,
        'designation' => 'Salesman',
    ]));

    $response->assertOk();

    expect($response->viewData('lastMonthMainInvestment'))->toBe(49633.84);
    expect($response->viewData('increaseInInvestmentMonth'))->toBe(-49633.84);
});
