<?php

use App\Models\Customer;
use App\Models\CustomerEmployeeAccount;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate([
        'name' => 'report-audit-customer-account-statement',
        'guard_name' => 'web',
    ]);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-audit-customer-account-statement');
    $this->actingAs($this->user);
});

function createStatementAccount(object $testContext, array $overrides = []): CustomerEmployeeAccount
{
    $supplier = $overrides['supplier'] ?? Supplier::factory()->create([
        'supplier_name' => $overrides['supplier_name'] ?? fake()->unique()->company(),
        'disabled' => false,
    ]);

    if ($testContext->user->supplier_id === null) {
        $testContext->user->forceFill(['supplier_id' => $supplier->id])->save();
    }

    $employee = $overrides['employee'] ?? Employee::factory()->create([
        'supplier_id' => $supplier->id,
        'name' => $overrides['employee_name'] ?? 'Salesman One',
        'designation' => 'Salesman',
        'is_active' => true,
    ]);

    $customer = $overrides['customer'] ?? Customer::factory()->create([
        'customer_name' => $overrides['customer_name'] ?? 'Alpha Customer',
        'customer_code' => $overrides['customer_code'] ?? fake()->unique()->bothify('CUST-####'),
        'business_name' => $overrides['business_name'] ?? 'Alpha Mart',
        'phone' => $overrides['phone'] ?? '03001234567',
        'is_active' => true,
    ]);

    return CustomerEmployeeAccount::create([
        'account_number' => $overrides['account_number'] ?? CustomerEmployeeAccount::generateAccountNumber(),
        'customer_id' => $customer->id,
        'employee_id' => $employee->id,
        'opened_date' => $overrides['opened_date'] ?? now()->toDateString(),
        'status' => $overrides['status'] ?? 'active',
        'created_by' => $testContext->user->id,
    ]);
}

function createStatementTransaction(object $testContext, CustomerEmployeeAccount $account, array $overrides = []): CustomerEmployeeAccountTransaction
{
    return CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => $overrides['transaction_date'] ?? now()->toDateString(),
        'transaction_type' => $overrides['transaction_type'] ?? 'credit_sale',
        'reference_number' => $overrides['reference_number'] ?? null,
        'invoice_number' => $overrides['invoice_number'] ?? null,
        'description' => $overrides['description'] ?? 'Statement transaction',
        'debit' => $overrides['debit'] ?? 0,
        'credit' => $overrides['credit'] ?? 0,
        'payment_method' => $overrides['payment_method'] ?? null,
        'created_by' => $testContext->user->id,
    ]);
}

it('requires the customer account statement permission', function () {
    $this->user->revokePermissionTo('report-audit-customer-account-statement');

    $this->get(route('reports.customer-account-statement.index'))->assertForbidden();
});

it('loads the index and shows searched customer accounts', function () {
    $matchingAccount = createStatementAccount($this, [
        'customer_name' => 'Moon Mart',
        'customer_code' => 'MOON-001',
        'account_number' => 'ACC-MOON-001',
    ]);
    $otherAccount = createStatementAccount($this, [
        'customer_name' => 'Sun Mart',
        'customer_code' => 'SUN-001',
        'account_number' => 'ACC-SUN-001',
    ]);

    createStatementTransaction($this, $matchingAccount, [
        'description' => 'Moon credit sale',
        'debit' => 1500,
    ]);
    createStatementTransaction($this, $otherAccount, [
        'description' => 'Sun credit sale',
        'debit' => 2500,
    ]);

    $response = $this->get(route('reports.customer-account-statement.index', [
        'filter' => ['search' => 'Moon'],
    ]));

    $response->assertSuccessful();
    $response->assertSee('Moon Mart');
    $response->assertSee('ACC-MOON-001');
    $response->assertSee('1,500.00');
    $response->assertDontSee('Sun Mart');
});

it('shows all accounts for a searched customer', function () {
    $customer = Customer::factory()->create([
        'customer_name' => 'Shared Customer',
        'customer_code' => 'SHARED-001',
    ]);
    $supplier = Supplier::factory()->create([
        'supplier_name' => 'Shared Supplier',
        'disabled' => false,
    ]);

    $firstAccount = createStatementAccount($this, [
        'supplier' => $supplier,
        'customer' => $customer,
        'employee_name' => 'First Salesman',
        'account_number' => 'ACC-SHARED-001',
    ]);
    $secondAccount = createStatementAccount($this, [
        'supplier' => $supplier,
        'customer' => $customer,
        'employee_name' => 'Second Salesman',
        'account_number' => 'ACC-SHARED-002',
    ]);

    createStatementTransaction($this, $firstAccount, ['debit' => 1000]);
    createStatementTransaction($this, $secondAccount, ['debit' => 2000]);

    $response = $this->get(route('reports.customer-account-statement.index', [
        'filter' => ['customer_id' => $customer->id],
    ]));

    $response->assertSuccessful();
    $response->assertSee('ACC-SHARED-001');
    $response->assertSee('ACC-SHARED-002');
    $response->assertSee('First Salesman');
    $response->assertSee('Second Salesman');
});

it('renders an account statement with transactions', function () {
    $account = createStatementAccount($this, [
        'customer_name' => 'Statement Customer',
        'account_number' => 'ACC-STMT-001',
    ]);

    createStatementTransaction($this, $account, [
        'transaction_date' => '2026-05-10',
        'transaction_type' => 'credit_sale',
        'reference_number' => 'REF-100',
        'invoice_number' => 'INV-100',
        'description' => 'Invoice sale',
        'debit' => 3000,
    ]);
    createStatementTransaction($this, $account, [
        'transaction_date' => '2026-05-12',
        'transaction_type' => 'recovery_cash',
        'reference_number' => 'REC-100',
        'description' => 'Cash recovery',
        'credit' => 1200,
        'payment_method' => 'cash',
    ]);

    $response = $this->get(route('reports.customer-account-statement.show', $account));

    $response->assertSuccessful();
    $response->assertSee('Statement Customer');
    $response->assertSee('ACC-STMT-001');
    $response->assertSee('Invoice sale');
    $response->assertSee('Cash recovery');
    $response->assertSee('1,800.00');
});

it('calculates date filtered opening running and closing balances', function () {
    $account = createStatementAccount($this, [
        'account_number' => 'ACC-BAL-001',
    ]);

    createStatementTransaction($this, $account, [
        'transaction_date' => '2026-05-01',
        'transaction_type' => 'opening_balance',
        'description' => 'Opening',
        'debit' => 1000,
    ]);
    createStatementTransaction($this, $account, [
        'transaction_date' => '2026-05-10',
        'description' => 'Credit sale',
        'debit' => 500,
    ]);
    createStatementTransaction($this, $account, [
        'transaction_date' => '2026-05-15',
        'transaction_type' => 'recovery_cash',
        'description' => 'Recovery',
        'credit' => 300,
    ]);
    createStatementTransaction($this, $account, [
        'transaction_date' => '2026-05-25',
        'description' => 'Out of range sale',
        'debit' => 700,
    ]);

    $response = $this->get(route('reports.customer-account-statement.show', [
        'customerEmployeeAccount' => $account,
        'filter' => [
            'date_from' => '2026-05-10',
            'date_to' => '2026-05-20',
        ],
    ]));

    $response->assertSuccessful();
    $response->assertSee('Opening Balance');
    $response->assertSee('1,000.00');
    $response->assertSee('Credit sale');
    $response->assertSee('Recovery');
    $response->assertSee('1,200.00');
    $response->assertDontSee('Out of range sale');

    expect($response->viewData('summary'))->toMatchArray([
        'opening_balance' => 1000.0,
        'total_debits' => 500.0,
        'total_credits' => 300.0,
        'closing_balance' => 1200.0,
    ]);
});

it('blocks scoped users from filtering or opening another suppliers account', function () {
    $ownSupplier = Supplier::factory()->create(['supplier_name' => 'Own Supplier']);
    $otherSupplier = Supplier::factory()->create(['supplier_name' => 'Other Supplier']);
    $ownAccount = createStatementAccount($this, [
        'supplier' => $ownSupplier,
        'customer_name' => 'Own Customer',
        'account_number' => 'ACC-OWN-001',
    ]);
    $otherAccount = createStatementAccount($this, [
        'supplier' => $otherSupplier,
        'customer_name' => 'Other Customer',
        'account_number' => 'ACC-OTHER-001',
    ]);

    createStatementTransaction($this, $ownAccount, ['debit' => 1000]);
    createStatementTransaction($this, $otherAccount, ['debit' => 2000]);

    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    $this->get(route('reports.customer-account-statement.index', [
        'filter' => ['supplier_id' => $otherSupplier->id],
    ]))->assertForbidden();

    $this->get(route('reports.customer-account-statement.show', $otherAccount))->assertForbidden();

    $response = $this->get(route('reports.customer-account-statement.index'));

    $response->assertSuccessful();
    $response->assertSee('Own Customer');
    $response->assertDontSee('Other Customer');
});
