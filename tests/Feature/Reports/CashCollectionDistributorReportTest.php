<?php

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementBankSlip;
use App\Models\SalesSettlementBankTransfer;
use App\Models\SalesSettlementCashDenomination;
use App\Models\SalesSettlementCheque;
use App\Models\SalesSettlementRecovery;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('report-audit-cash-collection-distributor');
    Permission::findOrCreate('report-audit-cash-collection-distributor-all-companies');

    $this->today = now()->format('Y-m-d');
    $this->nestle = Supplier::factory()->create(['supplier_name' => 'Nestle Pakistan', 'short_name' => 'Nestle']);
    $this->engro = Supplier::factory()->create(['supplier_name' => 'Engro Corporation', 'short_name' => 'Engro']);

    $this->settle = function (Supplier $supplier, int $cash, array $extra = []) {
        $employee = Employee::factory()->create(['supplier_id' => $supplier->id, 'designation' => 'Salesman']);
        $settlement = SalesSettlement::factory()->create([
            'employee_id' => $employee->id,
            'supplier_id' => $supplier->id,
            'settlement_date' => $this->today,
            'cash_collected' => $cash,
        ]);
        SalesSettlementCashDenomination::create(['sales_settlement_id' => $settlement->id, 'denom_5000' => intdiv($cash, 5000), 'total_amount' => $cash]);

        return $settlement;
    };

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo(['report-audit-cash-collection-distributor', 'report-audit-cash-collection-distributor-all-companies']);
});

it('is empty until a company is applied', function () {
    ($this->settle)($this->nestle, 10000);

    $this->actingAs($this->admin)->get(route('reports.cash-collection-distributor.index'))
        ->assertOk()
        ->assertSee('No company selected')
        ->assertViewHas('tables', []);
});

it('needs the report permission', function () {
    $this->actingAs(User::factory()->create())->get(route('reports.cash-collection-distributor.index'))->assertForbidden();
});

it('keeps the same amounts for all companies and a single company', function () {
    ($this->settle)($this->nestle, 10000);
    ($this->settle)($this->engro, 20000);

    $all = $this->actingAs($this->admin)->get(route('reports.cash-collection-distributor.index', ['supplier_id' => 'all']));
    $all->assertOk()->assertSee('Nestle Pakistan')->assertSee('Engro Corporation')->assertSee('30,000.00');

    $one = $this->actingAs($this->admin)->get(route('reports.cash-collection-distributor.index', ['supplier_id' => $this->engro->id]));
    $one->assertOk()->assertSee('20,000.00')->assertSee('Salesman-wise Detail');
    expect(collect($one->viewData('tables')['cash']['rows'])->pluck(1)->all())->toBe(['Engro Corporation']);
    expect($all->viewData('tables')['cash']['rows'][0][2])->toBe($one->viewData('tables')['cash']['rows'][0][2]);
});

it('counts cheques cleared on the day as cheque clearing and in the cash total', function () {
    $settlement = ($this->settle)($this->nestle, 10000);
    SalesSettlementCheque::create([
        'sales_settlement_id' => $settlement->id, 'cheque_number' => '001', 'amount' => 7500, 'bank_name' => 'HBL',
        'status' => 'cleared', 'cleared_date' => $this->today,
    ]);

    $tables = $this->actingAs($this->admin)
        ->get(route('reports.cash-collection-distributor.index', ['supplier_id' => $this->nestle->id]))
        ->viewData('tables');

    expect($tables['cash']['rows'][0][3])->toBe('7,500.00')
        ->and($tables['cash']['rows'][0][4])->toBe('17,500.00')
        ->and($tables['denominations']['total']['cells'][0])->toBe('17,500.00');
});

it('lists bank slips with distribution, salesman and account', function () {
    $settlement = ($this->settle)($this->nestle, 5000);
    $bank = BankAccount::factory()->create(['bank_name' => 'Meezan', 'account_number' => '0123456789']);
    SalesSettlementBankSlip::create([
        'sales_settlement_id' => $settlement->id, 'employee_id' => $settlement->employee_id, 'bank_account_id' => $bank->id,
        'amount' => 4000, 'reference_number' => 'SLIP-9',
    ]);

    $this->actingAs($this->admin)->get(route('reports.cash-collection-distributor.index', ['supplier_id' => 'all']))
        ->assertSee('Meezan')->assertSee('0123456789')->assertSee('4,000.00');
});

it('puts each bank account of a salesman in its own column', function () {
    $settlement = ($this->settle)($this->nestle, 5000);
    $first = BankAccount::factory()->create(['bank_name' => 'Meezan', 'account_number' => '111']);
    $second = BankAccount::factory()->create(['bank_name' => 'HBL', 'account_number' => '222']);
    foreach ([[$first, 1000], [$second, 2500], [$first, 500]] as [$bank, $amount]) {
        SalesSettlementBankSlip::create(['sales_settlement_id' => $settlement->id, 'employee_id' => $settlement->employee_id, 'bank_account_id' => $bank->id, 'amount' => $amount]);
    }

    $slips = $this->actingAs($this->admin)->get(route('reports.cash-collection-distributor.index', ['supplier_id' => 'all']))->viewData('tables')['slips'];

    expect($slips['cols'])->toHaveCount(6)
        ->and($slips['rows'])->toHaveCount(1)
        ->and(array_slice($slips['rows'][0], 3))->toBe(['2,500.00', '1,500.00', '4,000.00']);
});

it('holds users without the all-companies permission to their own company', function () {
    $user = User::factory()->create(['supplier_id' => $this->nestle->id]);
    $user->givePermissionTo('report-audit-cash-collection-distributor');

    $this->actingAs($user)->get(route('reports.cash-collection-distributor.index', ['supplier_id' => $this->nestle->id]))->assertOk();
    $this->actingAs($user)->get(route('reports.cash-collection-distributor.index', ['supplier_id' => $this->engro->id]))->assertForbidden();
    $this->actingAs($user)->get(route('reports.cash-collection-distributor.index', ['supplier_id' => 'all']))->assertForbidden();
});

it('downloads a pdf and renders the print sheet', function () {
    ($this->settle)($this->nestle, 10000);

    $this->actingAs($this->admin)->get(route('reports.cash-collection-distributor.export.pdf'))->assertStatus(422);

    $pdf = $this->actingAs($this->admin)->get(route('reports.cash-collection-distributor.export.pdf', ['supplier_id' => 'all', 'orientation' => 'landscape']));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toBe('application/pdf');

    $this->actingAs($this->admin)->get(route('reports.cash-collection-distributor.index', ['supplier_id' => 'all', 'layout' => 'print']))
        ->assertOk()->assertSee('Prepared By');
});

it('opens the how-it-works guide for report users only', function () {
    $this->actingAs($this->admin)->get(route('reports.cash-collection-distributor.help'))
        ->assertOk()
        ->assertSee('How the Cash Collection Distributor report works')
        ->assertSee('Expense vouchers (maker and checker)');

    $this->actingAs(User::factory()->create())->get(route('reports.cash-collection-distributor.help'))->assertForbidden();
});

it('counts bank transfers and bank recoveries as online from customer, and cash recoveries only in the notes', function () {
    $settlement = ($this->settle)($this->nestle, 10000);
    $customer = Customer::factory()->create();
    SalesSettlementBankTransfer::factory()->create(['sales_settlement_id' => $settlement->id, 'customer_id' => $customer->id, 'amount' => 3000]);
    foreach ([['bank_transfer', 2000], ['cash', 500]] as [$method, $amount]) {
        SalesSettlementRecovery::create([
            'sales_settlement_id' => $settlement->id, 'customer_id' => $customer->id, 'employee_id' => $settlement->employee_id,
            'payment_method' => $method, 'amount' => $amount,
        ]);
    }

    $tables = $this->actingAs($this->admin)->get(route('reports.cash-collection-distributor.index', ['supplier_id' => 'all']))->viewData('tables');

    expect($tables['bank']['rows'][0][3])->toBe('5,000.00')
        ->and($tables['cash']['rows'][0][2])->toBe('10,000.00')
        ->and($tables['cash']['rows'][0][4])->toBe('15,000.00');
});
