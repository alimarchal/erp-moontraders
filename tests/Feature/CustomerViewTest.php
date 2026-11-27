<?php

use App\Models\Customer;
use App\Models\CustomerEmployeeAccount;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['customer-list', 'report-audit-customer-account-statement'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['customer-list', 'report-audit-customer-account-statement']);
    $this->actingAs($this->user);

    // The stored credit_used is stale (0) while the account says the shop owes 4,000.
    $this->customer = Customer::factory()->create(['customer_name' => 'Ledger Shop', 'credit_limit' => 10000, 'credit_used' => 0]);
    $salesman = Employee::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'name' => 'Route Salesman']);
    $this->account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-VIEW-1', 'customer_id' => $this->customer->id, 'employee_id' => $salesman->id,
        'opened_date' => '2026-11-01', 'status' => 'active', 'created_by' => $this->user->id,
    ]);
    foreach ([[5000, 0], [0, 1000]] as [$debit, $credit]) {
        CustomerEmployeeAccountTransaction::create([
            'customer_employee_account_id' => $this->account->id, 'transaction_date' => '2026-11-10',
            'transaction_type' => $debit ? 'credit_sale' : 'recovery', 'description' => 'entry',
            'debit' => $debit, 'credit' => $credit, 'created_by' => $this->user->id,
        ]);
    }
});

it('works out credit used and available from the account balance and links each salesman statement', function () {
    $this->get(route('customers.show', $this->customer))
        ->assertOk()
        ->assertSeeInOrder(['Balance (Rs)', '4,000', 'Credit limit', '10,000', '40% used', 'Credit available', '6,000'])
        ->assertSee('Route Salesman')
        ->assertSee(route('reports.customer-account-statement.show', $this->account), false);
});

it('shows each customer balance on the list, linked to the statements', function () {
    $this->get(route('customers.index'))
        ->assertOk()
        ->assertSee('Ledger Shop')
        ->assertSee('4,000')
        ->assertSee(route('reports.customer-account-statement.index', ['filter' => ['customer_id' => $this->customer->id]]), false);
});
