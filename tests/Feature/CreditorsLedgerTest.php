<?php

use App\Models\Customer;
use App\Models\CustomerEmployeeAccount;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\CustomerSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'report-audit-creditors-ledger']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-audit-creditors-ledger');
    $this->seed(CustomerSeeder::class);
});

test('creditors ledger index page loads for authenticated user', function () {
    $this->actingAs($this->user)
        ->get(route('reports.creditors-ledger.index'))
        ->assertSuccessful();
});

test('creditors ledger filters and results are scoped to the authenticated users supplier', function () {
    $ownSupplier = Supplier::factory()->create(['supplier_name' => 'Kausar Oil']);
    $otherSupplier = Supplier::factory()->create(['supplier_name' => 'Nestle Pakistan']);
    $ownEmployee = Employee::factory()->create(['supplier_id' => $ownSupplier->id, 'name' => 'Own Salesman']);
    $otherEmployee = Employee::factory()->create(['supplier_id' => $otherSupplier->id, 'name' => 'Other Salesman']);
    $ownCustomer = Customer::factory()->create(['customer_name' => 'Own Customer']);
    $otherCustomer = Customer::factory()->create(['customer_name' => 'Other Customer']);

    $ownAccount = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-OWN',
        'customer_id' => $ownCustomer->id,
        'employee_id' => $ownEmployee->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    $otherAccount = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-OTHER',
        'customer_id' => $otherCustomer->id,
        'employee_id' => $otherEmployee->id,
        'opened_date' => now(),
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);

    CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $ownAccount->id,
        'transaction_date' => now()->toDateString(),
        'transaction_type' => 'credit_sale',
        'description' => 'Own credit sale',
        'debit' => 1000,
        'credit' => 0,
        'created_by' => $this->user->id,
    ]);
    CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $otherAccount->id,
        'transaction_date' => now()->toDateString(),
        'transaction_type' => 'credit_sale',
        'description' => 'Other credit sale',
        'debit' => 2000,
        'credit' => 0,
        'created_by' => $this->user->id,
    ]);

    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    $response = $this->actingAs($this->user)->get(route('reports.creditors-ledger.index'));

    $response->assertSuccessful();
    $response->assertSee('Own Customer');
    $response->assertDontSee('Other Customer');
    expect($response->viewData('supplierIdFilter'))->toBe($ownSupplier->id);
    expect($response->viewData('suppliers'))->toHaveCount(1);
    expect($response->viewData('suppliers')->first()->id)->toBe($ownSupplier->id);
    expect($response->viewData('employees'))->toHaveCount(1);
    expect($response->viewData('employees')->first()->id)->toBe($ownEmployee->id);
});

test('creditors ledger blocks filtering by another supplier for scoped users', function () {
    $ownSupplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();

    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    $this->actingAs($this->user)
        ->get(route('reports.creditors-ledger.index', ['filter' => ['supplier_id' => $otherSupplier->id]]))
        ->assertForbidden();
});

test('creditors ledger customer ledger page loads for authenticated user', function () {
    $customer = Customer::first();

    $this->actingAs($this->user)
        ->get(route('reports.creditors-ledger.customer-ledger', $customer))
        ->assertSuccessful();
});

test('creditors ledger customer credit sales page loads for authenticated user', function () {
    $customer = Customer::first();

    $this->actingAs($this->user)
        ->get(route('reports.creditors-ledger.customer-credit-sales', $customer))
        ->assertSuccessful();
});

test('creditors ledger requires authentication', function () {
    $this->get(route('reports.creditors-ledger.index'))
        ->assertRedirect(route('login'));
});
