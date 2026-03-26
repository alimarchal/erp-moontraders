<?php

use App\Models\Customer;
use App\Models\CustomerEmployeeAccount;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
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
});

it('loads the investment summary report page', function () {
    $response = $this->get(route('reports.investment-summary.index', [
        'supplier_id' => $this->supplier->id,
    ]));

    $response->assertOk();
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
