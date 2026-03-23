<?php

use App\Models\Customer;
use App\Models\CustomerEmployeeAccount;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'report-audit-opening-customer-balance', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'opening-customer-balance-list', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'opening-customer-balance-create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'opening-customer-balance-edit', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'opening-customer-balance-delete', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'opening-customer-balance-post', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(
        'report-audit-opening-customer-balance',
        'opening-customer-balance-list',
        'opening-customer-balance-create',
        'opening-customer-balance-edit',
        'opening-customer-balance-delete',
        'opening-customer-balance-post',
    );
    $this->actingAs($this->user);

    $this->supplier = Supplier::factory()->create(['disabled' => false]);

    $this->employee = Employee::factory()->create([
        'employee_code' => 'EMP-R001',
        'name' => 'Report Salesman',
        'supplier_id' => $this->supplier->id,
        'is_active' => true,
        'designation' => 'Salesman',
    ]);

    $this->customer = Customer::factory()->create([
        'customer_code' => 'CUST-R001',
        'customer_name' => 'Report Customer',
        'is_active' => true,
    ]);
});

function createReportTransaction(object $testContext, array $overrides = []): CustomerEmployeeAccountTransaction
{
    $account = CustomerEmployeeAccount::firstOrCreate([
        'customer_id' => $overrides['customer_id'] ?? $testContext->customer->id,
        'employee_id' => $overrides['employee_id'] ?? $testContext->employee->id,
    ], [
        'account_number' => 'ACC-RPT-'.uniqid(),
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $testContext->user->id,
    ]);

    return CustomerEmployeeAccountTransaction::create(array_merge([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => '2026-01-15',
        'transaction_type' => 'opening_balance',
        'description' => 'Test opening balance',
        'debit' => 5000,
        'credit' => 0,
        'created_by' => $testContext->user->id,
    ], $overrides));
}

// --- Index / Display Tests ---

it('displays the report index page', function () {
    $this->get(route('reports.opening-customer-balance.index'))
        ->assertOk()
        ->assertSee('Opening Customer Balance Report');
});

it('shows empty state when no filters applied', function () {
    createReportTransaction($this);

    $this->get(route('reports.opening-customer-balance.index'))
        ->assertOk()
        ->assertSee('Please select filters to view data')
        ->assertDontSee('5,000.00');
});

it('displays transactions in the report when filtered', function () {
    createReportTransaction($this);

    $this->get(route('reports.opening-customer-balance.index', ['supplier_id' => $this->supplier->id]))
        ->assertOk()
        ->assertSee('5,000.00')
        ->assertSee('Report Customer');
});

it('filters by supplier', function () {
    $otherSupplier = Supplier::factory()->create(['disabled' => false]);
    $otherEmployee = Employee::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'is_active' => true,
    ]);

    createReportTransaction($this, ['debit' => 5000]);
    createReportTransaction($this, [
        'employee_id' => $otherEmployee->id,
        'debit' => 3000,
    ]);

    $this->get(route('reports.opening-customer-balance.index', ['supplier_id' => $this->supplier->id]))
        ->assertOk()
        ->assertSee('5,000.00')
        ->assertDontSee('3,000.00');
});

it('filters by posted status', function () {
    createReportTransaction($this, [
        'debit' => 5000,
        'posted_at' => now(),
        'posted_by' => $this->user->id,
    ]);

    $otherCustomer = Customer::factory()->create(['is_active' => true]);
    createReportTransaction($this, [
        'customer_id' => $otherCustomer->id,
        'debit' => 3000,
    ]);

    $this->get(route('reports.opening-customer-balance.index', ['posted_status' => 'posted']))
        ->assertOk()
        ->assertSee('5,000.00')
        ->assertDontSee('3,000.00');
});

// --- Inline Add Tests ---

it('creates entry via inline add', function () {
    $this->post(route('reports.opening-customer-balance.store'), [
        'employee_id' => $this->employee->id,
        'customer_id' => $this->customer->id,
        'balance_date' => '2026-01-15',
        'opening_balance' => 7500,
        'description' => 'Inline created',
    ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('customer_employee_account_transactions', [
        'transaction_type' => 'opening_balance',
        'debit' => 7500,
    ]);
});

it('blocks duplicate opening balance via inline add', function () {
    createReportTransaction($this);

    $this->post(route('reports.opening-customer-balance.store'), [
        'employee_id' => $this->employee->id,
        'customer_id' => $this->customer->id,
        'balance_date' => '2026-02-01',
        'opening_balance' => 3000,
    ])
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('validates required fields on inline add', function () {
    $this->post(route('reports.opening-customer-balance.store'), [])
        ->assertSessionHasErrors(['employee_id', 'customer_id', 'balance_date', 'opening_balance']);
});

// --- Inline Edit Tests ---

it('updates entry via inline edit modal', function () {
    $transaction = createReportTransaction($this, ['debit' => 5000]);

    $this->put(route('reports.opening-customer-balance.update', $transaction), [
        'balance_date' => '2026-02-01',
        'opening_balance' => 8000,
        'description' => 'Updated via modal',
    ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $transaction->refresh();
    expect((float) $transaction->debit)->toBe(8000.0);
    expect($transaction->transaction_date->format('Y-m-d'))->toBe('2026-02-01');
});

it('blocks editing posted entry via inline edit', function () {
    $transaction = createReportTransaction($this, [
        'posted_at' => now(),
        'posted_by' => $this->user->id,
    ]);

    $this->put(route('reports.opening-customer-balance.update', $transaction), [
        'balance_date' => '2026-02-01',
        'opening_balance' => 9999,
    ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Posted transactions cannot be edited.');
});

// --- Inline Delete Tests ---

it('deletes entry via inline delete', function () {
    $transaction = createReportTransaction($this);

    $this->delete(route('reports.opening-customer-balance.destroy', $transaction))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertSoftDeleted('customer_employee_account_transactions', ['id' => $transaction->id]);
});

it('blocks deleting posted entry', function () {
    $transaction = createReportTransaction($this, [
        'posted_at' => now(),
        'posted_by' => $this->user->id,
    ]);

    $this->delete(route('reports.opening-customer-balance.destroy', $transaction))
        ->assertRedirect()
        ->assertSessionHas('error', 'Posted transactions cannot be deleted.');
});

// --- Permission Tests ---

it('blocks unauthenticated access to report', function () {
    auth()->logout();

    $this->get(route('reports.opening-customer-balance.index'))
        ->assertRedirect(route('login'));
});

it('blocks access without report permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('reports.opening-customer-balance.index'))
        ->assertForbidden();
});

it('blocks inline create without create permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('report-audit-opening-customer-balance');
    $this->actingAs($user);

    $this->post(route('reports.opening-customer-balance.store'), [
        'employee_id' => $this->employee->id,
        'customer_id' => $this->customer->id,
        'balance_date' => '2026-01-15',
        'opening_balance' => 5000,
    ])
        ->assertForbidden();
});

// --- Post Tests ---

it('posts an opening balance to GL via report', function () {
    $currency = \App\Models\Currency::factory()->base()->create();
    \App\Models\AccountingPeriod::create([
        'name' => 'Test Period',
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->endOfYear()->toDateString(),
        'status' => \App\Models\AccountingPeriod::STATUS_OPEN,
    ]);
    $assetType = \App\Models\AccountType::create([
        'type_name' => 'Asset',
        'report_group' => 'BalanceSheet',
        'description' => 'Asset accounts',
    ]);
    $equityType = \App\Models\AccountType::create([
        'type_name' => 'Equity',
        'report_group' => 'BalanceSheet',
        'description' => 'Equity accounts',
    ]);
    \App\Models\ChartOfAccount::create([
        'account_code' => '1100',
        'account_name' => 'Debtors',
        'account_type_id' => $assetType->id,
        'currency_id' => $currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);
    \App\Models\ChartOfAccount::create([
        'account_code' => '3100',
        'account_name' => 'Opening Balance Equity',
        'account_type_id' => $equityType->id,
        'currency_id' => $currency->id,
        'normal_balance' => 'credit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $transaction = createReportTransaction($this, ['debit' => 5000]);

    $this->post(route('reports.opening-customer-balance.post', $transaction))
        ->assertRedirect()
        ->assertSessionHas('success');

    $transaction->refresh();
    expect($transaction->isPosted())->toBeTrue();
    expect($transaction->journal_entry_id)->not->toBeNull();
});

it('blocks posting already posted entry via report', function () {
    $transaction = createReportTransaction($this, [
        'posted_at' => now(),
        'posted_by' => $this->user->id,
    ]);

    $this->post(route('reports.opening-customer-balance.post', $transaction))
        ->assertRedirect()
        ->assertSessionHas('error', 'This opening balance has already been posted to GL.');
});

it('blocks post without post permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('report-audit-opening-customer-balance');
    $this->actingAs($user);

    $transaction = createReportTransaction($this);

    $this->post(route('reports.opening-customer-balance.post', $transaction))
        ->assertForbidden();
});
