<?php

use App\Models\Employee;
use App\Models\EmployeeSalaryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $perms = [
        'employee-salary-transaction-list',
        'employee-salary-transaction-create',
        'employee-salary-transaction-edit',
        'employee-salary-transaction-delete',
        'employee-salary-transaction-post',
    ];

    foreach ($perms as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo($perms);
    $this->actingAs($this->user);
});

// ── Index ──────────────────────────────────────────────────────────

test('index page can be rendered', function () {
    $this->get(route('employee-salary-transactions.index'))
        ->assertSuccessful()
        ->assertViewIs('employee-salary-transactions.index')
        ->assertViewHas('items');
});

test('index displays transactions', function () {
    $employee = Employee::factory()->create();
    EmployeeSalaryTransaction::factory()->create([
        'employee_id' => $employee->id,
        'reference_number' => 'SAL-TEST-001',
    ]);

    $this->get(route('employee-salary-transactions.index'))
        ->assertSuccessful()
        ->assertSee('SAL-TEST-001');
});

test('index can filter by employee', function () {
    $employee1 = Employee::factory()->create();
    $employee2 = Employee::factory()->create();

    EmployeeSalaryTransaction::factory()->create(['employee_id' => $employee1->id]);
    EmployeeSalaryTransaction::factory()->create(['employee_id' => $employee2->id]);

    $response = $this->get(route('employee-salary-transactions.index', ['filter' => ['employee_id' => $employee1->id]]));

    $response->assertSuccessful();
    expect($response->viewData('items'))->toHaveCount(1);
});

test('index can filter by transaction type', function () {
    $employee = Employee::factory()->create();

    EmployeeSalaryTransaction::factory()->salary()->create(['employee_id' => $employee->id]);
    EmployeeSalaryTransaction::factory()->advance()->create(['employee_id' => $employee->id]);

    $response = $this->get(route('employee-salary-transactions.index', ['filter' => ['transaction_type' => 'Salary']]));

    $response->assertSuccessful();
    expect($response->viewData('items'))->toHaveCount(1);
});

test('index can filter by status', function () {
    $employee = Employee::factory()->create();

    EmployeeSalaryTransaction::factory()->pending()->create(['employee_id' => $employee->id]);
    EmployeeSalaryTransaction::factory()->paid()->create(['employee_id' => $employee->id]);

    $response = $this->get(route('employee-salary-transactions.index', ['filter' => ['status' => 'Pending']]));

    $response->assertSuccessful();
    expect($response->viewData('items'))->toHaveCount(1);
});

// ── Create ─────────────────────────────────────────────────────────

test('create page can be rendered', function () {
    $this->get(route('employee-salary-transactions.create'))
        ->assertSuccessful()
        ->assertViewIs('employee-salary-transactions.create')
        ->assertViewHas('employees')
        ->assertViewHas('suppliers')
        ->assertViewHas('transactionTypeOptions')
        ->assertViewHas('statusOptions')
        ->assertViewHas('paymentMethodOptions')
        ->assertViewHas('chartOfAccounts')
        ->assertViewHas('bankAccounts');
});

// ── Store ──────────────────────────────────────────────────────────

test('transaction can be created', function () {
    $employee = Employee::factory()->create();

    $data = [
        'employee_id' => $employee->id,
        'transaction_date' => now()->format('Y-m-d'),
        'reference_number' => 'SAL-TEST-01',
        'transaction_type' => 'Salary',
        'description' => 'January 2026 Salary',
        'salary_month' => 'January 2026',
        'debit' => 50000.00,
        'credit' => 0,
        'status' => 'Pending',
    ];

    $response = $this->post(route('employee-salary-transactions.store'), $data);

    $response->assertRedirect(route('employee-salary-transactions.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('employee_salary_transactions', [
        'employee_id' => $employee->id,
        'reference_number' => 'SAL-TEST-01',
        'transaction_type' => 'Salary',
        'debit' => 50000.00,
        'status' => 'Pending',
    ]);
});

test('store auto-populates supplier from employee', function () {
    $employee = Employee::factory()->create();

    $data = [
        'employee_id' => $employee->id,
        'transaction_date' => now()->format('Y-m-d'),
        'transaction_type' => 'Salary',
        'debit' => 30000,
        'credit' => 0,
        'status' => 'Pending',
    ];

    $this->post(route('employee-salary-transactions.store'), $data);

    $this->assertDatabaseHas('employee_salary_transactions', [
        'employee_id' => $employee->id,
        'supplier_id' => $employee->supplier_id,
    ]);
});

test('transaction is created with Pending status and no GL posting', function () {
    $employee = Employee::factory()->create();

    $data = [
        'employee_id' => $employee->id,
        'transaction_date' => now()->format('Y-m-d'),
        'transaction_type' => 'Advance',
        'debit' => 10000,
        'credit' => 0,
        'status' => 'Pending',
    ];

    $this->post(route('employee-salary-transactions.store'), $data);

    $this->assertDatabaseHas('employee_salary_transactions', [
        'employee_id' => $employee->id,
        'status' => 'Pending',
        'journal_entry_id' => null,
    ]);
});

test('store requires employee_id', function () {
    $this->post(route('employee-salary-transactions.store'), [
        'transaction_date' => now()->format('Y-m-d'),
        'transaction_type' => 'Salary',
        'debit' => 1000,
        'credit' => 0,
        'status' => 'Pending',
    ])->assertSessionHasErrors('employee_id');
});

test('store requires transaction_date', function () {
    $employee = Employee::factory()->create();

    $this->post(route('employee-salary-transactions.store'), [
        'employee_id' => $employee->id,
        'transaction_type' => 'Salary',
        'debit' => 1000,
        'credit' => 0,
        'status' => 'Pending',
    ])->assertSessionHasErrors('transaction_date');
});

test('store requires valid transaction_type', function () {
    $employee = Employee::factory()->create();

    $this->post(route('employee-salary-transactions.store'), [
        'employee_id' => $employee->id,
        'transaction_date' => now()->format('Y-m-d'),
        'transaction_type' => 'InvalidType',
        'debit' => 1000,
        'credit' => 0,
        'status' => 'Pending',
    ])->assertSessionHasErrors('transaction_type');
});

test('store requires valid status', function () {
    $employee = Employee::factory()->create();

    $this->post(route('employee-salary-transactions.store'), [
        'employee_id' => $employee->id,
        'transaction_date' => now()->format('Y-m-d'),
        'transaction_type' => 'Salary',
        'debit' => 1000,
        'credit' => 0,
        'status' => 'InvalidStatus',
    ])->assertSessionHasErrors('status');
});

test('store validates cheque fields when payment method is cheque', function () {
    $employee = Employee::factory()->create();

    $this->post(route('employee-salary-transactions.store'), [
        'employee_id' => $employee->id,
        'transaction_date' => now()->format('Y-m-d'),
        'transaction_type' => 'SalaryPayment',
        'debit' => 0,
        'credit' => 5000,
        'status' => 'Pending',
        'payment_method' => 'cheque',
    ])->assertSessionHasErrors(['cheque_number', 'cheque_date']);
});

test('store validates period_end must be after period_start', function () {
    $employee = Employee::factory()->create();

    $this->post(route('employee-salary-transactions.store'), [
        'employee_id' => $employee->id,
        'transaction_date' => now()->format('Y-m-d'),
        'transaction_type' => 'Salary',
        'debit' => 50000,
        'credit' => 0,
        'status' => 'Pending',
        'period_start' => '2026-06-01',
        'period_end' => '2026-05-01',
    ])->assertSessionHasErrors('period_end');
});

// ── Show ───────────────────────────────────────────────────────────

test('show page can be rendered', function () {
    $transaction = EmployeeSalaryTransaction::factory()->create();

    $this->get(route('employee-salary-transactions.show', $transaction))
        ->assertSuccessful()
        ->assertViewIs('employee-salary-transactions.show')
        ->assertViewHas('transaction');
});

// ── Edit ───────────────────────────────────────────────────────────

test('edit page can be rendered', function () {
    $transaction = EmployeeSalaryTransaction::factory()->create();

    $this->get(route('employee-salary-transactions.edit', $transaction))
        ->assertSuccessful()
        ->assertViewIs('employee-salary-transactions.edit')
        ->assertViewHas('transaction')
        ->assertViewHas('employees')
        ->assertViewHas('suppliers');
});

// ── Update ─────────────────────────────────────────────────────────

test('transaction can be updated', function () {
    $employee = Employee::factory()->create();
    $transaction = EmployeeSalaryTransaction::factory()->pending()->create([
        'employee_id' => $employee->id,
        'debit' => 30000,
    ]);

    $data = [
        'employee_id' => $employee->id,
        'transaction_date' => now()->format('Y-m-d'),
        'transaction_type' => 'Salary',
        'debit' => 50000,
        'credit' => 0,
        'status' => 'Pending',
    ];

    $response = $this->put(route('employee-salary-transactions.update', $transaction), $data);

    $response->assertRedirect(route('employee-salary-transactions.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('employee_salary_transactions', [
        'id' => $transaction->id,
        'debit' => 50000,
    ]);
});

test('paid transaction cannot be updated', function () {
    $employee = Employee::factory()->create();
    $transaction = EmployeeSalaryTransaction::factory()->paid()->create([
        'employee_id' => $employee->id,
    ]);

    $data = [
        'employee_id' => $employee->id,
        'transaction_date' => now()->format('Y-m-d'),
        'transaction_type' => 'Salary',
        'debit' => 99999,
        'credit' => 0,
        'status' => 'Pending',
    ];

    $response = $this->put(route('employee-salary-transactions.update', $transaction), $data);

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

// ── Destroy ────────────────────────────────────────────────────────

test('pending transaction can be deleted', function () {
    $transaction = EmployeeSalaryTransaction::factory()->pending()->create();

    $this->delete(route('employee-salary-transactions.destroy', $transaction))
        ->assertRedirect(route('employee-salary-transactions.index'))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('employee_salary_transactions', ['id' => $transaction->id]);
});

test('paid transaction cannot be deleted', function () {
    $transaction = EmployeeSalaryTransaction::factory()->paid()->create();

    $this->delete(route('employee-salary-transactions.destroy', $transaction))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('employee_salary_transactions', ['id' => $transaction->id, 'deleted_at' => null]);
});

// ── Post ───────────────────────────────────────────────────────────

test('cannot post transaction without GL accounts', function () {
    $transaction = EmployeeSalaryTransaction::factory()->pending()->create([
        'debit_account_id' => null,
        'credit_account_id' => null,
    ]);

    $response = $this->post(route('employee-salary-transactions.post', $transaction));

    $response->assertRedirect();
    $response->assertSessionHas('error');

    $this->assertDatabaseHas('employee_salary_transactions', [
        'id' => $transaction->id,
        'status' => 'Pending',
    ]);
});

test('cannot post already paid transaction', function () {
    $transaction = EmployeeSalaryTransaction::factory()->paid()->create();

    $response = $this->post(route('employee-salary-transactions.post', $transaction));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

// ── Balance ────────────────────────────────────────────────────────

test('balance is debit minus credit', function () {
    $employee = Employee::factory()->create();
    $transaction = EmployeeSalaryTransaction::factory()->create([
        'employee_id' => $employee->id,
        'debit' => 50000,
        'credit' => 0,
    ]);

    expect($transaction->balance)->toBe(50000.0);

    $creditTransaction = EmployeeSalaryTransaction::factory()->create([
        'employee_id' => $employee->id,
        'debit' => 0,
        'credit' => 20000,
    ]);

    expect($creditTransaction->balance)->toBe(-20000.0);
});

// ── Permissions ────────────────────────────────────────────────────

test('unauthenticated users cannot access transactions', function () {
    auth()->logout();

    $this->get(route('employee-salary-transactions.index'))->assertRedirect(route('login'));
});

test('users without permission cannot access index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('employee-salary-transactions.index'))->assertForbidden();
});

test('users without permission cannot store', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('employee-salary-transactions.store'), [])->assertForbidden();
});

test('users without permission cannot update', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transaction = EmployeeSalaryTransaction::factory()->create();

    $this->put(route('employee-salary-transactions.update', $transaction), [])->assertForbidden();
});

test('users without permission cannot delete', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transaction = EmployeeSalaryTransaction::factory()->create();

    $this->delete(route('employee-salary-transactions.destroy', $transaction))->assertForbidden();
});

test('users without permission cannot post', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transaction = EmployeeSalaryTransaction::factory()->create();

    $this->post(route('employee-salary-transactions.post', $transaction))->assertForbidden();
});
