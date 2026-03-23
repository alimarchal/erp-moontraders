<?php

use App\Models\Customer;
use App\Models\CustomerEmployeeAccount;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'opening-customer-balance-list', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'opening-customer-balance-create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'opening-customer-balance-edit', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'opening-customer-balance-delete', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'opening-customer-balance-post', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(
        'opening-customer-balance-list',
        'opening-customer-balance-create',
        'opening-customer-balance-edit',
        'opening-customer-balance-delete',
        'opening-customer-balance-post',
    );
    $this->actingAs($this->user);

    $this->supplier = Supplier::factory()->create([
        'disabled' => false,
    ]);

    $this->warehouse = Warehouse::factory()->create([
        'id' => 1,
        'disabled' => false,
    ]);

    $this->employee1 = Employee::factory()->create([
        'employee_code' => 'EMP-0001',
        'name' => 'Salesman One',
        'supplier_id' => $this->supplier->id,
        'is_active' => true,
        'designation' => 'Salesman',
    ]);

    $this->employee2 = Employee::factory()->create([
        'employee_code' => 'EMP-0002',
        'name' => 'Delivery Man Two',
        'supplier_id' => $this->supplier->id,
        'is_active' => true,
        'designation' => 'Delivery Man',
    ]);
});

// ── CRUD Tests ──────────────────────────────────────────────────────────────

it('displays the index page with opening balances', function () {
    $customer = Customer::factory()->create();
    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-TEST01',
        'customer_id' => $customer->id,
        'employee_id' => $this->employee1->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => '2026-01-15',
        'transaction_type' => 'opening_balance',
        'debit' => 5000,
        'credit' => 0,
        'description' => 'Opening balance test',
        'created_by' => $this->user->id,
    ]);

    $response = $this->get(route('opening-customer-balances.index'));

    $response->assertOk();
    $response->assertSee($customer->customer_name);
    $response->assertSee('5,000.00');
});

it('displays the create form', function () {
    $response = $this->get(route('opening-customer-balances.create'));

    $response->assertOk();
    $response->assertSee('Create Opening Customer Balance');
    $response->assertSee($this->employee1->name);
});

it('stores a manual opening balance', function () {
    $customer = Customer::factory()->create(['is_active' => true]);

    $response = $this->post(route('opening-customer-balances.store-manual'), [
        'employee_id' => $this->employee1->id,
        'customer_id' => $customer->id,
        'balance_date' => '2026-03-15',
        'opening_balance' => 7500.50,
    ]);

    $response->assertRedirect(route('opening-customer-balances.index'));
    $response->assertSessionHas('success');

    $account = CustomerEmployeeAccount::where('customer_id', $customer->id)
        ->where('employee_id', $this->employee1->id)
        ->first();

    expect($account)->not->toBeNull();

    $txn = $account->transactions()->where('transaction_type', 'opening_balance')->first();
    expect((float) $txn->debit)->toBe(7500.50);
    expect($txn->reference_number)->toStartWith('OCB-M-');
});

it('prevents duplicate manual opening balance for same customer-employee pair', function () {
    $customer = Customer::factory()->create(['is_active' => true]);

    $this->post(route('opening-customer-balances.store-manual'), [
        'employee_id' => $this->employee1->id,
        'customer_id' => $customer->id,
        'balance_date' => '2026-03-15',
        'opening_balance' => 5000,
    ]);

    $response = $this->post(route('opening-customer-balances.store-manual'), [
        'employee_id' => $this->employee1->id,
        'customer_id' => $customer->id,
        'balance_date' => '2026-03-16',
        'opening_balance' => 3000,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');

    expect(CustomerEmployeeAccountTransaction::where('transaction_type', 'opening_balance')->count())->toBe(1);
});

it('validates manual store required fields', function () {
    $this->post(route('opening-customer-balances.store-manual'), [])
        ->assertSessionHasErrors(['employee_id', 'customer_id', 'balance_date', 'opening_balance']);
});

it('shows the detail page for an opening balance', function () {
    $customer = Customer::factory()->create();
    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-SHOW01',
        'customer_id' => $customer->id,
        'employee_id' => $this->employee1->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    $txn = CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => '2026-02-10',
        'transaction_type' => 'opening_balance',
        'debit' => 8000,
        'credit' => 0,
        'description' => 'Test opening balance',
        'created_by' => $this->user->id,
    ]);

    $response = $this->get(route('opening-customer-balances.show', $txn));

    $response->assertOk();
    $response->assertSee($customer->customer_name);
    $response->assertSee('8,000.00');
});

it('displays edit form for an opening balance', function () {
    $customer = Customer::factory()->create();
    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-EDIT01',
        'customer_id' => $customer->id,
        'employee_id' => $this->employee1->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    $txn = CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => '2026-02-10',
        'transaction_type' => 'opening_balance',
        'debit' => 6000,
        'credit' => 0,
        'description' => 'Editable balance',
        'created_by' => $this->user->id,
    ]);

    $response = $this->get(route('opening-customer-balances.edit', $txn));

    $response->assertOk();
    $response->assertSee('Edit Opening Balance');
    $response->assertSee('6000');
});

it('updates an opening balance', function () {
    $customer = Customer::factory()->create();
    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-UPD01',
        'customer_id' => $customer->id,
        'employee_id' => $this->employee1->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    $txn = CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => '2026-02-10',
        'transaction_type' => 'opening_balance',
        'debit' => 6000,
        'credit' => 0,
        'description' => 'Old description',
        'created_by' => $this->user->id,
    ]);

    $response = $this->put(route('opening-customer-balances.update', $txn), [
        'balance_date' => '2026-04-01',
        'opening_balance' => 9500.75,
        'description' => 'Updated description',
    ]);

    $response->assertRedirect(route('opening-customer-balances.index'));
    $response->assertSessionHas('success');

    $txn->refresh();
    expect((float) $txn->debit)->toBe(9500.75);
    expect($txn->description)->toBe('Updated description');
    expect($txn->transaction_date->format('Y-m-d'))->toBe('2026-04-01');
});

it('deletes an opening balance', function () {
    $customer = Customer::factory()->create();
    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-DEL01',
        'customer_id' => $customer->id,
        'employee_id' => $this->employee1->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    $txn = CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => '2026-02-10',
        'transaction_type' => 'opening_balance',
        'debit' => 4000,
        'credit' => 0,
        'description' => 'To be deleted',
        'created_by' => $this->user->id,
    ]);

    $response = $this->delete(route('opening-customer-balances.destroy', $txn));

    $response->assertRedirect(route('opening-customer-balances.index'));
    $response->assertSessionHas('success');

    expect(CustomerEmployeeAccountTransaction::withTrashed()->find($txn->id)->trashed())->toBeTrue();
});

it('denies index access without list permission', function () {
    $userWithout = User::factory()->create();

    $this->actingAs($userWithout)
        ->get(route('opening-customer-balances.index'))
        ->assertForbidden();
});

it('denies edit access without edit permission', function () {
    $userWithout = User::factory()->create();
    $userWithout->givePermissionTo('opening-customer-balance-list');

    $customer = Customer::factory()->create();
    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-PERM01',
        'customer_id' => $customer->id,
        'employee_id' => $this->employee1->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    $txn = CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => '2026-02-10',
        'transaction_type' => 'opening_balance',
        'debit' => 3000,
        'credit' => 0,
        'description' => 'Permission test',
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($userWithout)
        ->get(route('opening-customer-balances.edit', $txn))
        ->assertForbidden();
});

it('denies delete without delete permission', function () {
    $userWithout = User::factory()->create();
    $userWithout->givePermissionTo('opening-customer-balance-list');

    $customer = Customer::factory()->create();
    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-PERM02',
        'customer_id' => $customer->id,
        'employee_id' => $this->employee1->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    $txn = CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => '2026-02-10',
        'transaction_type' => 'opening_balance',
        'debit' => 3000,
        'credit' => 0,
        'description' => 'Delete permission test',
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($userWithout)
        ->delete(route('opening-customer-balances.destroy', $txn))
        ->assertForbidden();
});

it('prevents editing non-opening-balance transactions', function () {
    $customer = Customer::factory()->create();
    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-TYPE01',
        'customer_id' => $customer->id,
        'employee_id' => $this->employee1->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    $txn = CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => '2026-02-10',
        'transaction_type' => 'credit_sale',
        'debit' => 2000,
        'credit' => 0,
        'description' => 'Not an opening balance',
        'created_by' => $this->user->id,
    ]);

    $response = $this->get(route('opening-customer-balances.edit', $txn));

    $response->assertRedirect(route('opening-customer-balances.index'));
    $response->assertSessionHas('error');
});

it('filters index by supplier', function () {
    $otherSupplier = Supplier::factory()->create(['disabled' => false]);
    $otherEmployee = Employee::factory()->create([
        'employee_code' => 'EMP-OTHER',
        'name' => 'Other Salesman',
        'supplier_id' => $otherSupplier->id,
        'is_active' => true,
        'designation' => 'Salesman',
    ]);

    $customer1 = Customer::factory()->create();
    $customer2 = Customer::factory()->create();

    $account1 = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-FLT01',
        'customer_id' => $customer1->id,
        'employee_id' => $this->employee1->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account1->id,
        'transaction_date' => '2026-01-15',
        'transaction_type' => 'opening_balance',
        'debit' => 5000,
        'credit' => 0,
        'description' => 'Balance for supplier 1',
        'created_by' => $this->user->id,
    ]);

    $account2 = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-FLT02',
        'customer_id' => $customer2->id,
        'employee_id' => $otherEmployee->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account2->id,
        'transaction_date' => '2026-01-15',
        'transaction_type' => 'opening_balance',
        'debit' => 3000,
        'credit' => 0,
        'description' => 'Balance for other supplier',
        'created_by' => $this->user->id,
    ]);

    $response = $this->get(route('opening-customer-balances.index', ['filter' => ['supplier_id' => $this->supplier->id]]));

    $response->assertOk();
    $response->assertSee('5,000.00');
    $response->assertDontSee('3,000.00');
});

// --- Posted transaction protection tests ---

it('blocks editing a posted transaction via direct URL', function () {
    $customer = Customer::factory()->create();
    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-POST01',
        'customer_id' => $customer->id,
        'employee_id' => $this->employee1->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    $transaction = CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => '2026-01-15',
        'transaction_type' => 'opening_balance',
        'debit' => 5000,
        'credit' => 0,
        'description' => 'Posted balance',
        'created_by' => $this->user->id,
        'posted_at' => now(),
        'posted_by' => $this->user->id,
    ]);

    $response = $this->get(route('opening-customer-balances.edit', $transaction));

    $response->assertRedirect(route('opening-customer-balances.show', $transaction));
    $response->assertSessionHas('error', 'Posted transactions cannot be edited.');
});

it('blocks updating a posted transaction via direct URL', function () {
    $customer = Customer::factory()->create();
    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-POST02',
        'customer_id' => $customer->id,
        'employee_id' => $this->employee1->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    $transaction = CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => '2026-01-15',
        'transaction_type' => 'opening_balance',
        'debit' => 5000,
        'credit' => 0,
        'description' => 'Posted balance',
        'created_by' => $this->user->id,
        'posted_at' => now(),
        'posted_by' => $this->user->id,
    ]);

    $response = $this->put(route('opening-customer-balances.update', $transaction), [
        'balance_date' => '2026-02-01',
        'opening_balance' => 9999,
    ]);

    $response->assertRedirect(route('opening-customer-balances.show', $transaction));
    $response->assertSessionHas('error', 'Posted transactions cannot be updated.');
});

it('blocks deleting a posted transaction via direct URL', function () {
    $customer = Customer::factory()->create();
    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-POST03',
        'customer_id' => $customer->id,
        'employee_id' => $this->employee1->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    $transaction = CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => '2026-01-15',
        'transaction_type' => 'opening_balance',
        'debit' => 5000,
        'credit' => 0,
        'description' => 'Posted balance',
        'created_by' => $this->user->id,
        'posted_at' => now(),
        'posted_by' => $this->user->id,
    ]);

    $response = $this->delete(route('opening-customer-balances.destroy', $transaction));

    $response->assertRedirect(route('opening-customer-balances.show', $transaction));
    $response->assertSessionHas('error', 'Posted transactions cannot be deleted.');
});

it('hides edit and delete buttons for posted transactions on index', function () {
    $customer = Customer::factory()->create();
    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-POST04',
        'customer_id' => $customer->id,
        'employee_id' => $this->employee1->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => '2026-01-15',
        'transaction_type' => 'opening_balance',
        'debit' => 5000,
        'credit' => 0,
        'description' => 'Posted balance',
        'created_by' => $this->user->id,
        'posted_at' => now(),
        'posted_by' => $this->user->id,
    ]);

    $response = $this->get(route('opening-customer-balances.index'));

    $response->assertOk();
    $response->assertSee('Posted');
    $response->assertDontSee('title="Edit"');
    $response->assertDontSee('title="Delete"');
});

it('posts an opening balance to GL successfully', function () {
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

    $customer = Customer::factory()->create();
    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-GL01',
        'customer_id' => $customer->id,
        'employee_id' => $this->employee1->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    $transaction = CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => now()->toDateString(),
        'transaction_type' => 'opening_balance',
        'debit' => 5000,
        'credit' => 0,
        'description' => 'Test GL posting',
        'created_by' => $this->user->id,
    ]);

    $response = $this->post(route('opening-customer-balances.post', $transaction));

    $response->assertRedirect(route('opening-customer-balances.show', $transaction));
    $response->assertSessionHas('success');

    $transaction->refresh();
    expect($transaction->isPosted())->toBeTrue();
    expect($transaction->journal_entry_id)->not->toBeNull();
    expect($transaction->posted_by)->toBe($this->user->id);
});

it('blocks posting an already posted transaction', function () {
    $customer = Customer::factory()->create();
    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-POST05',
        'customer_id' => $customer->id,
        'employee_id' => $this->employee1->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    $transaction = CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => '2026-01-15',
        'transaction_type' => 'opening_balance',
        'debit' => 5000,
        'credit' => 0,
        'description' => 'Already posted',
        'created_by' => $this->user->id,
        'posted_at' => now(),
        'posted_by' => $this->user->id,
    ]);

    $response = $this->post(route('opening-customer-balances.post', $transaction));

    $response->assertSessionHas('error', 'This opening balance has already been posted to GL.');
});
