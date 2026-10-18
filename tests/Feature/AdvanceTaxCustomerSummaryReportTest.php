<?php

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementAdvanceTax;
use App\Models\SalesSettlementAdvanceTaxIncome;
use App\Models\SalesSettlementExpense;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'report-audit-advance-tax-customer-summary', 'guard_name' => 'web']);

    $this->nestle = Supplier::factory()->create(['supplier_name' => 'Nestle Pakistan', 'disabled' => false]);
    $this->other = Supplier::factory()->create(['supplier_name' => 'Other Foods', 'disabled' => false]);

    $this->admin = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->admin->givePermissionTo('report-audit-advance-tax-customer-summary');
    $this->companyUser = User::factory()->create(['is_super_admin' => 'No', 'supplier_id' => $this->nestle->id]);
    $this->companyUser->givePermissionTo('report-audit-advance-tax-customer-summary');

    $this->salesman = Employee::factory()->create(['supplier_id' => $this->nestle->id, 'designation' => 'Salesman']);
    $this->advanceTaxAccount = ChartOfAccount::factory()->create(['account_code' => '1161']);

    $this->customerA = Customer::factory()->create([
        'customer_code' => 'C-1001',
        'customer_name' => 'Ali General Store',
        'business_name' => 'Ali Mart',
        'owner_cnic' => '82101-1234567-1',
        'ntn' => '1234567-8',
        'address' => 'Main Bazar',
        'sub_locality' => 'Chattar',
        'city' => 'Muzaffarabad',
        'notes' => 'Wholesale buyer',
    ]);
    $this->customerB = Customer::factory()->create(['customer_code' => 'C-1002', 'customer_name' => 'Bilal Traders']);

    $this->settle = fn (int $supplierId, string $date, string $status = 'posted') => SalesSettlement::factory()->create([
        'supplier_id' => $supplierId,
        'employee_id' => $supplierId === $this->nestle->id ? $this->salesman->id : Employee::factory()->create(['supplier_id' => $supplierId])->id,
        'settlement_date' => $date,
        'status' => $status,
    ]);

    $this->entry = fn (SalesSettlement $settlement, Customer $customer, float $sale, float $tax, string $model = SalesSettlementAdvanceTax::class) => $model::create([
        'sales_settlement_id' => $settlement->id,
        'customer_id' => $customer->id,
        'sale_amount' => $sale,
        'tax_rate' => 0.25,
        'tax_amount' => $tax,
        'invoice_number' => 'AT-'.fake()->unique()->numerify('########'),
    ]);

    $this->benefit = fn (SalesSettlement $settlement, float $amount) => SalesSettlementExpense::create([
        'sales_settlement_id' => $settlement->id,
        'expense_account_id' => $this->advanceTaxAccount->id,
        'amount' => $amount,
        'expense_date' => $settlement->settlement_date,
    ]);

    $this->url = fn (array $filter = []) => route('reports.advance-tax-customer-summary.index', [
        'filter' => array_merge(['supplier_id' => $this->nestle->id, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31'], $filter),
    ]);
});

it('requires the report permission', function () {
    $this->actingAs(User::factory()->create())->get(route('reports.advance-tax-customer-summary.index'))->assertForbidden();
});

it('asks for a company before showing anything', function () {
    $this->actingAs($this->admin)->get(route('reports.advance-tax-customer-summary.index'))
        ->assertOk()->assertSee('Please select a company');
});

it('adds the advance tax up per customer across settlements and works out the three tax columns', function () {
    $first = ($this->settle)($this->nestle->id, '2026-10-03');
    $second = ($this->settle)($this->nestle->id, '2026-10-17');
    ($this->entry)($first, $this->customerA, 100000, 250);
    ($this->entry)($second, $this->customerA, 60000, 150);
    ($this->entry)($second, $this->customerB, 40000, 100);

    $response = $this->actingAs($this->admin)->get(($this->url)());
    $rows = $response->viewData('rows');

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['customer']->customer_code)->toBe('C-1001')
        ->and($rows[0]['entries'])->toBe(2)
        ->and($rows[0]['sale'])->toBe(160000.0)
        ->and($rows[0]['advance_tax'])->toBe(4000.0)
        ->and($rows[0]['benefits'])->toBe(400.0)
        ->and($rows[0]['received'])->toBe(3600.0)
        ->and($rows[1]['advance_tax'])->toBe(1000.0)
        ->and($rows[1]['received'])->toBe(900.0)
        ->and($response->viewData('totalSale'))->toBe(200000.0)
        ->and($response->viewData('totalAdvanceTax'))->toBe(5000.0)
        ->and($response->viewData('totalBenefits'))->toBe(500.0)
        ->and($response->viewData('totalReceived'))->toBe(4500.0);

    $response->assertSee('C-1001')->assertSee('Ali Mart')->assertSee('Received From Customer', false)
        ->assertDontSee('82101-1234567-1')->assertDontSee('Main Bazar');
});

it('reads entries of suppliers that store advance tax as income as well', function () {
    $settlement = ($this->settle)($this->nestle->id, '2026-10-05');
    ($this->entry)($settlement, $this->customerA, 80000, 200);
    ($this->entry)($settlement, $this->customerA, 20000, 50, SalesSettlementAdvanceTaxIncome::class);

    $rows = $this->actingAs($this->admin)->get(($this->url)())->viewData('rows');

    expect($rows)->toHaveCount(1)->and($rows[0]['benefits'])->toBe(250.0)->and($rows[0]['entries'])->toBe(2);
});

it('leaves out drafts, other dates and other companies', function () {
    ($this->entry)(($this->settle)($this->nestle->id, '2026-10-05'), $this->customerA, 100000, 250);
    ($this->entry)(($this->settle)($this->nestle->id, '2026-10-06', 'draft'), $this->customerA, 900000, 2250);
    ($this->entry)(($this->settle)($this->nestle->id, '2026-09-30'), $this->customerA, 900000, 2250);
    ($this->entry)(($this->settle)($this->other->id, '2026-10-07'), $this->customerB, 900000, 2250);

    $response = $this->actingAs($this->admin)->get(($this->url)());

    expect($response->viewData('rows'))->toHaveCount(1)
        ->and($response->viewData('totalBenefits'))->toBe(250.0)
        ->and($response->viewData('totalSale'))->toBe(100000.0);
});

it('cross-checks the customer-wise total with the 1161 amount posted on the settlements', function () {
    $first = ($this->settle)($this->nestle->id, '2026-10-03');
    $second = ($this->settle)($this->nestle->id, '2026-10-17');
    ($this->entry)($first, $this->customerA, 100000, 250);
    ($this->entry)($second, $this->customerB, 40000, 100);
    ($this->benefit)($first, 250);
    ($this->benefit)($second, 100);

    $response = $this->actingAs($this->admin)->get(($this->url)());

    expect($response->viewData('totalBenefits'))->toBe(350.0)
        ->and($response->viewData('benefitsPosted'))->toBe(350.0)
        ->and($response->viewData('difference'))->toBe(0.0);

    ($this->benefit)($second, 25);

    $response = $this->actingAs($this->admin)->get(($this->url)());
    expect($response->viewData('difference'))->toBe(-25.0);
});

it('lets a company user see only their own company', function () {
    ($this->entry)(($this->settle)($this->nestle->id, '2026-10-05'), $this->customerA, 100000, 250);

    $this->actingAs($this->companyUser)
        ->get(route('reports.advance-tax-customer-summary.index', ['filter' => ['start_date' => '2026-10-01', 'end_date' => '2026-10-31']]))
        ->assertOk()->assertSee('C-1001')->assertSee('Ali Mart');

    $this->actingAs($this->companyUser)->get(($this->url)(['supplier_id' => $this->other->id]))->assertForbidden();
});
