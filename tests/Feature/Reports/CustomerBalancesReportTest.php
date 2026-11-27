<?php

use App\Models\Customer;
use App\Models\CustomerEmployeeAccount;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'report-audit-customer-balances', 'guard_name' => 'web']);

    $this->user = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->user->givePermissionTo('report-audit-customer-balances');
    $this->actingAs($this->user);

    $this->nestle = Supplier::factory()->create(['disabled' => false]);
    $this->engro = Supplier::factory()->create(['disabled' => false]);
    $this->nestleSalesman = Employee::factory()->create(['supplier_id' => $this->nestle->id, 'designation' => 'Salesman']);
    $this->engroSalesman = Employee::factory()->create(['supplier_id' => $this->engro->id, 'designation' => 'Salesman']);
});

/**
 * Entries on one customer's account with one salesman: [date, type, debit, credit].
 *
 * @param  array<int, array{0: string, 1: string, 2: float, 3: float}>  $entries
 */
function balanceEntries(Customer $customer, Employee $salesman, array $entries, int $userId): void
{
    $account = CustomerEmployeeAccount::firstOrCreate(
        ['customer_id' => $customer->id, 'employee_id' => $salesman->id],
        ['account_number' => 'ACC-BAL-'.uniqid(), 'opened_date' => '2026-11-01', 'status' => 'active', 'created_by' => $userId],
    );

    foreach ($entries as [$date, $type, $debit, $credit]) {
        CustomerEmployeeAccountTransaction::create([
            'customer_employee_account_id' => $account->id, 'transaction_date' => $date, 'transaction_type' => $type,
            'description' => $type, 'debit' => $debit, 'credit' => $credit, 'created_by' => $userId,
        ]);
    }
}

it('adds up every salesman account of a customer and shows who owes money', function () {
    $shop = Customer::factory()->create(['customer_name' => 'Owing Shop']);
    $advance = Customer::factory()->create(['customer_name' => 'Advance Shop']);
    $clear = Customer::factory()->create(['customer_name' => 'Clear Shop']);

    balanceEntries($shop, $this->nestleSalesman, [['2026-11-02', 'credit_sale', 5000, 0], ['2026-11-10', 'recovery', 0, 2000]], $this->user->id);
    balanceEntries($shop, $this->engroSalesman, [['2026-11-05', 'credit_sale', 1000, 0]], $this->user->id);
    balanceEntries($advance, $this->nestleSalesman, [['2026-11-03', 'recovery', 0, 700]], $this->user->id);
    balanceEntries($clear, $this->nestleSalesman, [['2026-11-03', 'credit_sale', 300, 0], ['2026-11-04', 'recovery', 0, 300]], $this->user->id);

    $response = $this->get(route('reports.customer-balances.index'))
        ->assertOk()
        ->assertSee('Owing Shop')
        ->assertDontSee('Advance Shop')
        ->assertDontSee('Clear Shop');

    $row = $response->viewData('customers')->getCollection()->firstWhere('customer_name', 'Owing Shop');

    expect((float) $row->balance)->toBe(4000.0)
        ->and((int) $row->accounts)->toBe(2)
        ->and((string) $row->last_recovery)->toStartWith('2026-11-10')
        ->and($response->viewData('totals'))->toBe(['customers' => 1, 'owed' => 4000.0, 'advance' => 0.0, 'net' => 4000.0]);

    $this->get(route('reports.customer-balances.index', ['filter' => ['status' => 'advance']]))
        ->assertSee('Advance Shop')
        ->assertDontSee('Owing Shop');
});

it('counts only one company and only entries up to the chosen date', function () {
    $shop = Customer::factory()->create(['customer_name' => 'Two Company Shop']);
    balanceEntries($shop, $this->nestleSalesman, [['2026-11-02', 'credit_sale', 5000, 0], ['2026-11-20', 'credit_sale', 900, 0]], $this->user->id);
    balanceEntries($shop, $this->engroSalesman, [['2026-11-05', 'credit_sale', 1000, 0]], $this->user->id);

    $row = $this->get(route('reports.customer-balances.index', ['filter' => ['supplier_id' => $this->nestle->id, 'as_of' => '2026-11-10']]))
        ->assertOk()
        ->viewData('customers')->getCollection()->first();

    expect((float) $row->balance)->toBe(5000.0);
});

it('keeps a company user to their own company and needs the permission', function () {
    $companyUser = User::factory()->create(['supplier_id' => $this->nestle->id]);
    $companyUser->givePermissionTo('report-audit-customer-balances');
    $shop = Customer::factory()->create(['customer_name' => 'Engro Only Shop']);
    balanceEntries($shop, $this->engroSalesman, [['2026-11-05', 'credit_sale', 1000, 0]], $this->user->id);

    $this->actingAs($companyUser)->get(route('reports.customer-balances.index'))->assertOk()->assertDontSee('Engro Only Shop');
    $this->actingAs($companyUser)->get(route('reports.customer-balances.index', ['filter' => ['supplier_id' => $this->engro->id]]))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('reports.customer-balances.index'))->assertForbidden();
});
