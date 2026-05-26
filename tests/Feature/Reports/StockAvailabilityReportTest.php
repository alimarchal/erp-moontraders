<?php

use App\Models\Customer;
use App\Models\CustomerEmployeeAccount;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::create(['name' => 'report-audit-stock-availability']);
});

it('redirects unauthenticated users', function () {
    $this->get(route('reports.stock-availability.index'))
        ->assertRedirect(route('login'));
});

it('denies access without permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.stock-availability.index'))
        ->assertForbidden();
});

it('loads stock availability report with permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('report-audit-stock-availability');

    $this->actingAs($user)
        ->get(route('reports.stock-availability.index'))
        ->assertOk()
        ->assertViewIs('reports.stock-availability.index');
});

it('loads report with supplier filter', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('report-audit-stock-availability');
    $supplier = Supplier::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.stock-availability.index', ['supplier_id' => $supplier->id]))
        ->assertOk()
        ->assertViewHas('supplierId', (string) $supplier->id);
});

it('loads report with historical date', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('report-audit-stock-availability');
    $pastDate = now()->subDays(30)->format('Y-m-d');

    $this->actingAs($user)
        ->get(route('reports.stock-availability.index', ['as_of_date' => $pastDate]))
        ->assertOk()
        ->assertViewHas('isCurrentStock', false);
});

it('passes suppliers, warehouses, and categories to view', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('report-audit-stock-availability');

    $this->actingAs($user)
        ->get(route('reports.stock-availability.index'))
        ->assertOk()
        ->assertViewHas('suppliers')
        ->assertViewHas('warehouses')
        ->assertViewHas('categories');
});

it('shows supplier wise salesman credit closing balance', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('report-audit-stock-availability');

    $supplier = Supplier::factory()->create(['supplier_name' => 'Moon Traders']);
    $otherSupplier = Supplier::factory()->create(['supplier_name' => 'Other Supplier']);

    $salesmanA = Employee::factory()->create(['supplier_id' => $supplier->id]);
    $salesmanB = Employee::factory()->create(['supplier_id' => $supplier->id]);
    $otherSalesman = Employee::factory()->create(['supplier_id' => $otherSupplier->id]);

    $customerA = Customer::factory()->create();
    $customerB = Customer::factory()->create();
    $customerC = Customer::factory()->create();

    $accountA = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-000001',
        'customer_id' => $customerA->id,
        'employee_id' => $salesmanA->id,
        'opened_date' => now()->subMonth(),
        'status' => 'active',
        'created_by' => $user->id,
    ]);
    $accountB = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-000002',
        'customer_id' => $customerB->id,
        'employee_id' => $salesmanB->id,
        'opened_date' => now()->subMonth(),
        'status' => 'active',
        'created_by' => $user->id,
    ]);
    $otherAccount = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-000003',
        'customer_id' => $customerC->id,
        'employee_id' => $otherSalesman->id,
        'opened_date' => now()->subMonth(),
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $accountA->id,
        'transaction_date' => now()->subDay(),
        'transaction_type' => 'credit_sale',
        'description' => 'Credit sale',
        'debit' => 1000,
        'credit' => 0,
        'created_by' => $user->id,
    ]);
    CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $accountA->id,
        'transaction_date' => now(),
        'transaction_type' => 'recovery_cash',
        'description' => 'Recovery',
        'debit' => 0,
        'credit' => 250,
        'created_by' => $user->id,
    ]);
    CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $accountB->id,
        'transaction_date' => now(),
        'transaction_type' => 'credit_sale',
        'description' => 'Credit sale',
        'debit' => 500,
        'credit' => 0,
        'created_by' => $user->id,
    ]);
    CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $accountB->id,
        'transaction_date' => now()->addDay(),
        'transaction_type' => 'credit_sale',
        'description' => 'Future credit sale',
        'debit' => 900,
        'credit' => 0,
        'created_by' => $user->id,
    ]);
    CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $otherAccount->id,
        'transaction_date' => now(),
        'transaction_type' => 'credit_sale',
        'description' => 'Other supplier credit sale',
        'debit' => 300,
        'credit' => 0,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('reports.stock-availability.index', [
            'supplier_id' => $supplier->id,
            'as_of_date' => now()->format('Y-m-d'),
        ]))
        ->assertOk()
        ->assertSeeText('Credit Balance Salesman')
        ->assertSeeText('1,250.00')
        ->assertViewHas('grandTotalSalesmanCreditBalance', 1250.0)
        ->assertViewHas('stockData', function ($stockData) use ($supplier) {
            $row = $stockData->firstWhere('supplier_id', $supplier->id);

            return $row && $row->credit_balance_salesman === 1250.0;
        });
});
