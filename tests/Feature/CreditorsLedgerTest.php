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

/**
 * @return array{0: Supplier, 1: Employee, 2: Customer}
 */
function creditAccount(User $user, string $supplierName, string $salesmanName, string $customerName, float $debit, int $daysAgo, ?Customer $customer = null): array
{
    $supplier = Supplier::firstWhere('supplier_name', $supplierName) ?? Supplier::factory()->create(['supplier_name' => $supplierName]);
    $employee = Employee::factory()->create(['supplier_id' => $supplier->id, 'name' => $salesmanName]);
    $customer ??= Customer::factory()->create(['customer_name' => $customerName]);
    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-'.fake()->unique()->numerify('######'),
        'customer_id' => $customer->id,
        'employee_id' => $employee->id,
        'opened_date' => now()->subDays($daysAgo),
        'status' => 'active',
        'created_by' => $user->id,
    ]);
    CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => now()->subDays($daysAgo)->toDateString(),
        'transaction_type' => 'credit_sale',
        'description' => 'Credit sale',
        'debit' => $debit,
        'credit' => 0,
        'created_by' => $user->id,
    ]);

    return [$supplier, $employee, $customer];
}

test('aging report ages each salesman account and links to that salesman ledger', function () {
    [, $raja, $bakers] = creditAccount($this->user, 'Nestle', 'Raja Safeer', 'Baba Bakers', 50000, 75);
    [, $ali] = creditAccount($this->user, 'Nestle', 'Ali Khan', 'Baba Bakers', 20000, 10, $bakers);
    creditAccount($this->user, 'Engro', 'Engro Man', 'Engro Shop', 9000, 120);
    $this->user->forceFill(['is_super_admin' => 'Yes'])->save();

    $response = $this->actingAs($this->user)->get(route('reports.creditors-ledger.aging-report'));

    $response->assertSuccessful()
        ->assertSee(e(route('reports.creditors-ledger.customer-ledger', ['customer' => $bakers->id, 'filter' => ['employee_id' => $raja->id]])), false)
        ->assertSee(e(route('reports.creditors-ledger.customer-ledger', ['customer' => $bakers->id, 'filter' => ['employee_id' => $ali->id]])), false);
    expect($response->viewData('totals')['current']['amount'])->toBe(20000.0)
        ->and($response->viewData('totals')['61_90']['amount'])->toBe(50000.0)
        ->and($response->viewData('totals')['over_90']['amount'])->toBe(9000.0);

    $overdue = $this->actingAs($this->user)->get(route('reports.creditors-ledger.aging-report', ['filter' => ['bucket' => '60_plus']]));
    expect($overdue->viewData('accounts')->pluck('salesman')->all())->toBe(['Raja Safeer', 'Engro Man']);
});

test('aging report and salesman creditors are scoped to the users supplier', function () {
    [$nestle] = creditAccount($this->user, 'Nestle', 'Raja Safeer', 'Baba Bakers', 50000, 75);
    [$engro] = creditAccount($this->user, 'Engro', 'Engro Man', 'Engro Shop', 9000, 120);
    $this->user->forceFill(['supplier_id' => $nestle->id])->save();

    $this->actingAs($this->user)->get(route('reports.creditors-ledger.aging-report'))
        ->assertSuccessful()
        ->assertSee('Baba Bakers')
        ->assertDontSee('Engro Shop');

    $salesmen = $this->actingAs($this->user)->get(route('reports.creditors-ledger.salesman-creditors'));
    $salesmen->assertSuccessful()->assertSee('Raja Safeer')->assertDontSee('Engro Man');
    expect($salesmen->viewData('salesmen')->first())
        ->balance->toBe(50000.0)
        ->overdue->toBe(50000.0)
        ->customers->toBe(1);

    $this->actingAs($this->user)
        ->get(route('reports.creditors-ledger.aging-report', ['filter' => ['supplier_id' => $engro->id]]))
        ->assertForbidden();
});

test('customer ledger filtered by salesman names the salesman', function () {
    [, $raja, $bakers] = creditAccount($this->user, 'Nestle', 'Raja Safeer', 'Baba Bakers', 50000, 5);
    creditAccount($this->user, 'Nestle', 'Ali Khan', 'Baba Bakers', 20000, 5, $bakers);

    $this->actingAs($this->user)
        ->get(route('reports.creditors-ledger.customer-ledger', ['customer' => $bakers->id, 'filter' => ['employee_id' => $raja->id]]))
        ->assertSuccessful()
        ->assertSeeInOrder(['Salesman:', 'Raja Safeer'])
        ->assertSee('All salesmen');
});

/** Adds a dated credit sale (debit) or recovery (credit) to an account, or a settlement-revert reversal of another row. */
function ledgerLine(User $user, int $accountId, int $daysAgo, float $debit, float $credit, ?int $reverses = null): int
{
    return CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $accountId,
        'transaction_date' => now()->subDays($daysAgo)->toDateString(),
        'transaction_type' => $reverses ? 'adjustment' : ($debit > 0 ? 'credit_sale' : 'recovery'),
        'reverses_transaction_id' => $reverses,
        'description' => 'Line',
        'debit' => $debit,
        'credit' => $credit,
        'created_by' => $user->id,
    ])->id;
}

test('aging counts new credit taken after the old balance was cleared as new, not as overdue', function () {
    // Murree Traders II: two old sales paid off in full, then fresh credit 2 days ago.
    [, $salesman, $customer] = creditAccount($this->user, 'Nestle', 'Mujahid Shah', 'Murree Traders II', 25442, 144);
    $accountId = CustomerEmployeeAccount::where('customer_id', $customer->id)->value('id');
    ledgerLine($this->user, $accountId, 142, 11165, 0);
    ledgerLine($this->user, $accountId, 140, 0, 25442);
    ledgerLine($this->user, $accountId, 123, 0, 11165);
    ledgerLine($this->user, $accountId, 2, 253600, 0);
    $this->user->forceFill(['is_super_admin' => 'Yes'])->save();

    $response = $this->actingAs($this->user)->get(route('reports.creditors-ledger.aging-report'));

    $row = $response->viewData('accounts')->first();
    expect($row->balance)->toBe(253600.0)
        ->and($row->days)->toBe(2)
        ->and($row->amounts)->toBe(['current' => 253600.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0])
        ->and($response->viewData('overdueCount'))->toBe(0);
});

test('aging splits a part-paid balance by the age of each unpaid sale, oldest paid first', function () {
    [, , $customer] = creditAccount($this->user, 'Nestle', 'Raja Safeer', 'Baba Bakers', 1000, 100);
    $accountId = CustomerEmployeeAccount::where('customer_id', $customer->id)->value('id');
    ledgerLine($this->user, $accountId, 45, 500, 0);
    ledgerLine($this->user, $accountId, 10, 300, 0);
    ledgerLine($this->user, $accountId, 5, 0, 600);   // clears 600 of the 1,000 sale from 100 days ago
    $this->user->forceFill(['is_super_admin' => 'Yes'])->save();

    $response = $this->actingAs($this->user)->get(route('reports.creditors-ledger.aging-report'));

    $row = $response->viewData('accounts')->first();
    expect($row->balance)->toBe(1200.0)
        ->and($row->days)->toBe(100)
        ->and($row->amounts)->toBe(['current' => 300.0, '31_60' => 500.0, '61_90' => 0.0, 'over_90' => 400.0])
        ->and($response->viewData('totals')['over_90']['amount'])->toBe(400.0);

    $salesmen = $this->actingAs($this->user)->get(route('reports.creditors-ledger.salesman-creditors'));
    expect($salesmen->viewData('salesmen')->first()->overdue)->toBe(400.0);
});

test('aging keeps an old sale overdue when the recovery that paid it is reversed, instead of reading the reversal as fresh credit', function () {
    [, , $customer] = creditAccount($this->user, 'Nestle', 'Raja Safeer', 'Baba Bakers', 1000, 100);
    $accountId = CustomerEmployeeAccount::where('customer_id', $customer->id)->value('id');
    $recovery = ledgerLine($this->user, $accountId, 50, 0, 1000);
    ledgerLine($this->user, $accountId, 10, 300, 0);
    ledgerLine($this->user, $accountId, 0, 1000, 0, reverses: $recovery);
    $this->user->forceFill(['is_super_admin' => 'Yes'])->save();

    $row = $this->actingAs($this->user)->get(route('reports.creditors-ledger.aging-report'))->viewData('accounts')->first();

    expect($row->balance)->toBe(1300.0)
        ->and($row->days)->toBe(100)
        ->and($row->amounts)->toBe(['current' => 300.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 1000.0]);
});

test('aging drops a reversed credit sale instead of letting its reversal pay off an older sale', function () {
    [, , $customer] = creditAccount($this->user, 'Nestle', 'Raja Safeer', 'Baba Bakers', 1000, 100);
    $accountId = CustomerEmployeeAccount::where('customer_id', $customer->id)->value('id');
    $sale = ledgerLine($this->user, $accountId, 10, 400, 0);
    ledgerLine($this->user, $accountId, 0, 0, 400, reverses: $sale);
    $this->user->forceFill(['is_super_admin' => 'Yes'])->save();

    $row = $this->actingAs($this->user)->get(route('reports.creditors-ledger.aging-report'))->viewData('accounts')->first();

    expect($row->balance)->toBe(1000.0)
        ->and($row->days)->toBe(100)
        ->and($row->amounts)->toBe(['current' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 1000.0]);
});
