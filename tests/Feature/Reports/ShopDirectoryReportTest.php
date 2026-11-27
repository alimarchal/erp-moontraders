<?php

use App\Models\Customer;
use App\Models\CustomerEmployeeAccount;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'report-sales-shop-list', 'guard_name' => 'web']);

    $this->user = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->user->givePermissionTo('report-sales-shop-list');
    $this->actingAs($this->user);

    $this->nestle = Supplier::factory()->create();
    $this->engro = Supplier::factory()->create();
    $this->nestleSalesman = Employee::factory()->create(['supplier_id' => $this->nestle->id, 'name' => 'Nestle Rider']);
    $this->engroSalesman = Employee::factory()->create(['supplier_id' => $this->engro->id, 'name' => 'Engro Rider']);

    $this->owing = Customer::factory()->create(['customer_name' => 'Owing Shop', 'customer_code' => 'S-001', 'owner_cnic' => '', 'it_status' => true]);
    $this->quiet = Customer::factory()->create(['customer_name' => 'Quiet Shop', 'customer_code' => 'S-002', 'owner_cnic' => '12345-1234567-1', 'it_status' => false]);
});

/**
 * @param  array<int, array{0: string, 1: string, 2: float, 3: float}>  $entries  [date, type, debit, credit]
 */
function shopEntries(Customer $customer, Employee $salesman, array $entries, int $userId): CustomerEmployeeAccount
{
    $account = CustomerEmployeeAccount::firstOrCreate(
        ['customer_id' => $customer->id, 'employee_id' => $salesman->id],
        ['account_number' => 'ACC-SHOP-'.uniqid(), 'opened_date' => '2026-01-01', 'status' => 'active', 'created_by' => $userId],
    );

    foreach ($entries as [$date, $type, $debit, $credit]) {
        CustomerEmployeeAccountTransaction::create([
            'customer_employee_account_id' => $account->id, 'transaction_date' => $date, 'transaction_type' => $type,
            'description' => $type, 'debit' => $debit, 'credit' => $credit, 'created_by' => $userId,
        ]);
    }

    return $account;
}

it('shows each shop with its balance, salesmen and last sale, and filters by them', function () {
    shopEntries($this->owing, $this->nestleSalesman, [[now()->subDays(3)->toDateString(), 'credit_sale', 5000, 0], [now()->subDay()->toDateString(), 'recovery', 0, 1000]], $this->user->id);
    shopEntries($this->owing, $this->engroSalesman, [[now()->subDays(2)->toDateString(), 'credit_sale', 500, 0]], $this->user->id);

    $row = $this->get(route('reports.shop-list.index'))->assertOk()->assertSeeInOrder(['>Code', '>Shop'], false)->assertSee('S-001')->viewData('customers')->getCollection()->firstWhere('customer_code', 'S-001');
    expect((float) $row->balance)->toBe(4500.0)->and((int) $row->accounts)->toBe(2);

    $this->get(route('reports.shop-list.index', ['filter' => ['balance' => 'owes']]))->assertSee('Owing Shop')->assertDontSee('Quiet Shop');
    $this->get(route('reports.shop-list.index', ['filter' => ['balance' => 'none']]))->assertSee('Quiet Shop')->assertDontSee('Owing Shop');
    $this->get(route('reports.shop-list.index', ['filter' => ['no_sale' => '30']]))->assertSee('Quiet Shop')->assertDontSee('Owing Shop');
    $this->get(route('reports.shop-list.index', ['filter' => ['missing' => 'owner_cnic']]))->assertSee('Owing Shop')->assertDontSee('Quiet Shop');
    $this->get(route('reports.shop-list.index', ['filter' => ['customer_name' => 'Quiet', 'it_status' => '0']]))->assertSee('Quiet Shop')->assertDontSee('Owing Shop');
    expect($this->get(route('reports.shop-list.index', ['per_page' => '50']))->viewData('customers')->perPage())->toBe(50);
});

it('counts only one company when a supplier is picked, and keeps a company user to their own company', function () {
    shopEntries($this->owing, $this->nestleSalesman, [['2026-11-02', 'credit_sale', 5000, 0]], $this->user->id);
    shopEntries($this->owing, $this->engroSalesman, [['2026-11-02', 'credit_sale', 700, 0]], $this->user->id);

    $row = $this->get(route('reports.shop-list.index', ['filter' => ['supplier_id' => $this->engro->id]]))->viewData('customers')->getCollection()->first();
    expect((float) $row->balance)->toBe(700.0);

    $companyUser = User::factory()->create(['supplier_id' => $this->nestle->id]);
    $companyUser->givePermissionTo('report-sales-shop-list');

    $own = $this->actingAs($companyUser)->get(route('reports.shop-list.index'))->assertOk()->viewData('customers')->getCollection();
    expect($own->pluck('customer_name')->all())->toBe(['Owing Shop'])->and((float) $own->first()->balance)->toBe(5000.0);
    $this->actingAs($companyUser)->get(route('reports.shop-list.index', ['filter' => ['supplier_id' => $this->engro->id]]))->assertForbidden();
});

it('opens a quick look of a shop with each salesman account', function () {
    shopEntries($this->owing, $this->nestleSalesman, [['2026-11-02', 'credit_sale', 5000, 0]], $this->user->id);

    $this->get(route('reports.shop-list.show', $this->owing))
        ->assertOk()
        ->assertSee('Owing Shop')
        ->assertSee('Nestle Rider')
        ->assertSee('5,000');
});

it('downloads the shops as an A4 PDF with the chosen columns', function () {
    foreach (['portrait', 'landscape'] as $orientation) {
        $this->get(route('reports.shop-list.index', ['format' => 'pdf', 'orientation' => $orientation, 'columns' => 'phone,ntn,balance']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
});

it('needs the shop list permission', function () {
    $this->actingAs(User::factory()->create())->get(route('reports.shop-list.index'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('reports.shop-list.show', $this->owing))->assertForbidden();
});
