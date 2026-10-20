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

    $this->settle = fn (int $supplierId, string $date, string $status = 'posted', float $sale = 0) => SalesSettlement::factory()->create([
        'total_sales_amount' => $sale,
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

it('works each customer sale back from the 2% benefit and keeps the rest as non-NTN sale', function () {
    $first = ($this->settle)($this->nestle->id, '2026-10-03', 'posted', 1000000);
    $second = ($this->settle)($this->nestle->id, '2026-10-17', 'posted', 500000);
    ($this->entry)($first, $this->customerA, 0, 400);
    ($this->entry)($second, $this->customerA, 0, 600);
    ($this->entry)($second, $this->customerB, 0, 100);

    $response = $this->actingAs($this->admin)->get(($this->url)());
    $rows = $response->viewData('rows');

    // 1,000 benefit is 2% of 50,000 sale; 100 is 2% of 5,000.
    expect($rows)->toHaveCount(2)
        ->and($rows[0]['customer']->customer_code)->toBe('C-1001')
        ->and($rows[0]['sale'])->toBe(50000.0)
        ->and($rows[0]['advance_tax'])->toBe(1250.0)
        ->and($rows[0]['benefits'])->toBe(1000.0)
        ->and($rows[0]['received'])->toBe(250.0)
        ->and($rows[1]['sale'])->toBe(5000.0)
        ->and($response->viewData('ntnSale'))->toBe(55000.0)
        ->and($response->viewData('nonNtnSale'))->toBe(1445000.0)
        ->and($response->viewData('nonNtnAdvanceTax'))->toBe(36125.0)
        ->and($response->viewData('ntnSale') + $response->viewData('nonNtnSale'))->toBe($response->viewData('totalSale'))
        ->and($response->viewData('ntnReceived'))->toBe(275.0)
        ->and($response->viewData('totalReceived'))->toBe(36400.0);

    $response->assertSee('C-1001')->assertSee('Ali Mart, Main Bazar, Chattar, Muzaffarabad')
        ->assertDontSee('82101-1234567-1')->assertSee('1234567-8')
        ->assertSee('Sale to NON NTN customers')->assertSee('Total (2 customers)')
        ->assertSee('Tax to pay now')->assertDontSee('Wholesale buyer');
});

it('shows all the sale as non-NTN sale when no customer has an advance tax entry', function () {
    ($this->settle)($this->nestle->id, '2026-10-14', 'posted', 7645.74);
    ($this->settle)($this->nestle->id, '2026-10-15', 'posted', 0);
    ($this->settle)($this->nestle->id, '2026-10-16', 'draft', 5000);

    $response = $this->actingAs($this->admin)->get(($this->url)());

    expect($response->viewData('rows'))->toHaveCount(0)
        ->and($response->viewData('nonNtnSale'))->toBe(7645.74)
        ->and($response->viewData('totalSale'))->toBe(7645.74)
        ->and($response->viewData('totalReceived'))->toBe(round(7645.74 * 0.025, 2));
});

it('reads entries of suppliers that store advance tax as income as well', function () {
    $settlement = ($this->settle)($this->nestle->id, '2026-10-05', 'posted', 80000);
    ($this->entry)($settlement, $this->customerA, 0, 200);
    ($this->entry)($settlement, $this->customerA, 0, 50, SalesSettlementAdvanceTaxIncome::class);

    $rows = $this->actingAs($this->admin)->get(($this->url)())->viewData('rows');

    expect($rows)->toHaveCount(1)->and($rows[0]['benefits'])->toBe(250.0)->and($rows[0]['sale'])->toBe(12500.0);
});

it('leaves out drafts, other dates and other companies', function () {
    ($this->entry)(($this->settle)($this->nestle->id, '2026-10-05', 'posted', 100000), $this->customerA, 0, 250);
    ($this->entry)(($this->settle)($this->nestle->id, '2026-10-06', 'draft', 900000), $this->customerA, 0, 2250);
    ($this->entry)(($this->settle)($this->nestle->id, '2026-09-30', 'posted', 900000), $this->customerA, 0, 2250);
    ($this->entry)(($this->settle)($this->other->id, '2026-10-07', 'posted', 900000), $this->customerB, 0, 2250);

    $response = $this->actingAs($this->admin)->get(($this->url)());

    expect($response->viewData('rows'))->toHaveCount(1)
        ->and($response->viewData('totalBenefits'))->toBe(250.0)
        ->and($response->viewData('totalSale'))->toBe(100000.0);
});

it('ties the customer-wise benefits to the 1161 amount posted on the settlements', function () {
    $first = ($this->settle)($this->nestle->id, '2026-10-03');
    $second = ($this->settle)($this->nestle->id, '2026-10-17');
    ($this->entry)($first, $this->customerA, 0, 250);
    ($this->entry)($second, $this->customerB, 0, 100);
    ($this->benefit)($first, 250);
    ($this->benefit)($second, 100);

    $response = $this->actingAs($this->admin)->get(($this->url)());

    expect($response->viewData('totalBenefits'))->toBe((float) SalesSettlementExpense::query()->sum('amount'))
        ->and($response->viewData('totalBenefits'))->toBe(350.0);

    $response->assertDontSee('added up customer-wise')->assertDontSee('Difference');
});

it('does not show the CNIC in the customer column', function () {
    ($this->entry)(($this->settle)($this->nestle->id, '2026-10-05', 'posted', 100000), $this->customerA, 0, 250);

    $this->actingAs($this->admin)->get(($this->url)())
        ->assertDontSee('CNIC')->assertDontSee('82101-1234567-1')->assertSee('C-1001')->assertSee('Ali Mart');
});

it('downloads an A4 PDF in portrait and in landscape', function () {
    ($this->entry)(($this->settle)($this->nestle->id, '2026-10-05', 'posted', 100000), $this->customerA, 0, 250);

    foreach (['portrait', 'landscape'] as $orientation) {
        $response = $this->actingAs($this->admin)->get(route('reports.advance-tax-customer-summary.export.pdf', [
            'filter' => ['supplier_id' => $this->nestle->id, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31'],
            'orientation' => $orientation,
        ]));

        $response->assertOk()->assertDownload();
        expect($response->baseResponse->getContent())->toStartWith('%PDF');
    }
});

it('needs a company, the permission and the company scope for the PDF too', function () {
    $this->actingAs($this->admin)->get(route('reports.advance-tax-customer-summary.export.pdf'))->assertStatus(422);
    $this->actingAs(User::factory()->create())->get(route('reports.advance-tax-customer-summary.export.pdf'))->assertForbidden();
    $this->actingAs($this->companyUser)
        ->get(route('reports.advance-tax-customer-summary.export.pdf', ['filter' => ['supplier_id' => $this->other->id]]))
        ->assertForbidden();
});

it('lets a company user see only their own company', function () {
    ($this->entry)(($this->settle)($this->nestle->id, '2026-10-05'), $this->customerA, 100000, 250);

    $this->actingAs($this->companyUser)
        ->get(route('reports.advance-tax-customer-summary.index', ['filter' => ['start_date' => '2026-10-01', 'end_date' => '2026-10-31']]))
        ->assertOk()->assertSee('C-1001')->assertSee('Ali Mart');

    $this->actingAs($this->companyUser)->get(($this->url)(['supplier_id' => $this->other->id]))->assertForbidden();
});
