<?php

use App\Models\BankAccount;
use App\Models\CashPayoutDenomination;
use App\Models\ChequeClearing;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\ExpenseVoucher;
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
        $settlement = SalesSettlement::factory()->create($extra + [
            'employee_id' => $employee->id,
            'supplier_id' => $supplier->id,
            'settlement_date' => $this->today,
            'cash_collected' => $cash,
            'status' => 'posted',
        ]);
        SalesSettlementCashDenomination::create(['sales_settlement_id' => $settlement->id, 'denom_5000' => intdiv($cash, 5000), 'total_amount' => $cash]);

        return $settlement;
    };

    $this->cheque = fn (SalesSettlement $settlement, int $amount, array $extra = []) => SalesSettlementCheque::create($extra + [
        'sales_settlement_id' => $settlement->id, 'cheque_number' => (string) fake()->unique()->numberBetween(1000, 99999),
        'amount' => $amount, 'bank_name' => 'HBL', 'status' => 'cleared', 'cleared_date' => $this->today, 'cleared_via' => 'cash',
    ]);

    /** An approved cash voucher with the notes the approver counted (null = old voucher with no count). */
    $this->voucher = function (?Supplier $supplier, int $amount, ?array $notes, array $extra = []) {
        $voucher = ExpenseVoucher::factory()->create($extra + [
            'supplier_id' => $supplier?->id,
            'amount' => $amount,
            'payment_type' => ExpenseVoucher::TYPE_CASH,
            'payment_method' => ExpenseVoucher::METHOD_CASH,
            'status' => ExpenseVoucher::STATUS_APPROVED,
            'voucher_date' => $this->today,
        ]);
        if ($notes !== null) {
            CashPayoutDenomination::create(['payable_type' => $voucher->getMorphClass(), 'payable_id' => $voucher->id] + $notes);
        }

        return $voucher;
    };

    $this->sheet = fn (int|string $supplierId, ?string $date = null) => $this->actingAs($this->admin)
        ->get(route('reports.cash-collection-distributor.index', ['supplier_id' => $supplierId, 'date' => $date ?? $this->today]))
        ->assertOk()
        ->viewData('tables');

    /** The first row of a table whose label column holds $label. */
    $this->row = fn (array $table, string $label, int $labelColumn = 0) => collect($table['rows'])->first(fn ($row) => $row[$labelColumn] === $label);

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
    expect(collect($one->viewData('tables')['collection']['rows'])->pluck(1)->all())->toBe(['Engro Corporation']);
    expect(($this->row)($all->viewData('tables')['collection'], 'Engro Corporation', 1)[2])->toBe($one->viewData('tables')['collection']['rows'][0][2]);
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

    // The cheque was received in today's settlement and cleared today: received counts once in the total, clearing brings the cash.
    expect($tables['collection']['rows'][0][3])->toBe('7,500.00')
        ->and($tables['collection']['rows'][0][6])->toBe('7,500.00')
        ->and($tables['collection']['rows'][0][8])->toBe('17,500.00')
        ->and($tables['denominations']['total']['cells'][5])->toBe('17,500.00');
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

    expect($tables['collection']['rows'][0][5])->toBe('5,000.00')
        ->and($tables['collection']['rows'][0][2])->toBe('10,000.00')
        ->and($tables['collection']['rows'][0][8])->toBe('15,000.00');
});

it('nets the notes paid out by cash vouchers so notes equal cash in hand', function () {
    ($this->settle)($this->nestle, 10000);
    ($this->settle)($this->engro, 20000);
    ($this->voucher)($this->nestle, 6000, ['denom_5000' => 1, 'denom_500' => 2]);
    ($this->voucher)($this->engro, 1500, null);

    $nestle = ($this->sheet)($this->nestle->id);
    expect(($this->row)($nestle['denominations'], '5,000'))->toBe(['5,000', 2, '10,000.00', 1, '5,000.00', 1, '5,000.00'])
        ->and(($this->row)($nestle['denominations'], '500'))->toBe(['500', '-', '-', 2, '1,000.00', -2, '-1,000.00'])
        ->and(($this->row)($nestle['denominations'], 'Paid out, notes not counted')[4])->toBe('-')
        ->and($nestle['denominations']['total']['cells'])->toBe(['', '10,000.00', '', '6,000.00', '', '4,000.00'])
        ->and(($this->row)($nestle['summary'], 'Cash in hand')[1])->toBe('4,000.00');

    // An old voucher with no count still balances through the "not counted" line.
    $engro = ($this->sheet)($this->engro->id);
    expect(($this->row)($engro['denominations'], 'Paid out, notes not counted')[4])->toBe('1,500.00')
        ->and($engro['denominations']['total']['cells'][5])->toBe('18,500.00')
        ->and(($this->row)($engro['summary'], 'Cash in hand')[1])->toBe('18,500.00');

    $all = ($this->sheet)('all');
    expect($all['denominations']['total']['cells'][5])->toBe('22,500.00')
        ->and(($this->row)($all['summary'], 'Cash in hand')[1])->toBe('22,500.00');
});

it('balances notes for one company after a batch clearing', function () {
    $nestleSettlement = ($this->settle)($this->nestle, 10000);
    $engroSettlement = ($this->settle)($this->engro, 20000);
    $nestleBatch = ChequeClearing::factory()->create(['supplier_id' => $this->nestle->id, 'denom_5000' => 1, 'denom_1000' => 2, 'denom_500' => 1]);
    $engroBatch = ChequeClearing::factory()->create(['supplier_id' => $this->engro->id, 'denom_1000' => 3]);
    ($this->cheque)($nestleSettlement, 4000, ['cheque_clearing_id' => $nestleBatch->id]);
    ($this->cheque)($nestleSettlement, 3500, ['cheque_clearing_id' => $nestleBatch->id]);
    ($this->cheque)($engroSettlement, 3000, ['cheque_clearing_id' => $engroBatch->id]);

    $nestle = ($this->sheet)($this->nestle->id);
    expect(($this->row)($nestle['denominations'], '1,000')[1])->toBe(2)
        ->and(($this->row)($nestle['denominations'], 'Cash not counted')[2])->toBe('-')
        ->and($nestle['denominations']['total']['cells'][5])->toBe('17,500.00')
        ->and(($this->row)($nestle['summary'], 'Cash in hand')[1])->toBe('17,500.00');

    $engro = ($this->sheet)($this->engro->id);
    expect(($this->row)($engro['denominations'], '1,000')[1])->toBe(3)
        ->and(($this->row)($engro['denominations'], 'Cash not counted')[2])->toBe('-')
        ->and($engro['denominations']['total']['cells'][5])->toBe('23,000.00');

    $all = ($this->sheet)('all');
    expect(($this->row)($all['denominations'], '1,000')[1])->toBe(5)
        ->and($all['denominations']['total']['cells'][5])->toBe('40,500.00')
        ->and(($this->row)($all['summary'], 'Cash in hand')[1])->toBe('40,500.00');
});

it('counts a batch shared by two salesmen as a group line when only one salesman is shown', function () {
    $first = ($this->settle)($this->nestle, 10000);
    $second = ($this->settle)($this->nestle, 5000);
    $batch = ChequeClearing::factory()->create(['supplier_id' => $this->nestle->id, 'denom_1000' => 3, 'denom_500' => 1]);
    ($this->cheque)($first, 2000, ['cheque_clearing_id' => $batch->id]);
    ($this->cheque)($second, 1500, ['cheque_clearing_id' => $batch->id]);

    $tables = $this->actingAs($this->admin)
        ->get(route('reports.cash-collection-distributor.index', ['supplier_id' => $this->nestle->id, 'date' => $this->today, 'employee_ids' => [$first->employee_id]]))
        ->assertOk()
        ->viewData('tables');

    expect(($this->row)($tables['denominations'], '1,000')[1])->toBe('-')
        ->and(($this->row)($tables['denominations'], 'Cheques cleared in a group (notes counted with other salesmen)')[2])->toBe('2,000.00')
        ->and(($this->row)($tables['denominations'], 'Cash not counted')[2])->toBe('-')
        ->and($tables['denominations']['total']['cells'][5])->toBe('12,000.00')
        ->and(($this->row)($tables['summary'], 'Cash in hand')[1])->toBe('12,000.00');

    $whole = ($this->sheet)($this->nestle->id);
    expect(($this->row)($whole['denominations'], '1,000')[1])->toBe(3)
        ->and(($this->row)($whole['denominations'], 'Cheques cleared in a group (notes counted with other salesmen)')[2])->toBe('-')
        ->and($whole['denominations']['total']['cells'][5])->toBe('18,500.00');
});

it('still balances old per-cheque notes', function () {
    $settlement = ($this->settle)($this->nestle, 10000);
    ($this->cheque)($settlement, 7500, ['cleared_denom_5000' => 1, 'cleared_denom_1000' => 2]);
    ($this->cheque)($settlement, 2000);

    $tables = ($this->sheet)($this->nestle->id);

    expect(($this->row)($tables['denominations'], '5,000')[1])->toBe(3)
        ->and(($this->row)($tables['denominations'], 'Cash not counted')[2])->toBe('2,500.00')
        ->and($tables['denominations']['total']['cells'][5])->toBe('19,500.00')
        ->and(($this->row)($tables['summary'], 'Cash in hand')[1])->toBe('19,500.00');
});

it('shows notes counted above the cash received as a negative line', function () {
    $settlement = ($this->settle)($this->nestle, 10000);
    ($this->cheque)($settlement, 1000, ['cleared_denom_5000' => 1]);

    $tables = ($this->sheet)($this->nestle->id);

    expect(($this->row)($tables['denominations'], 'Cash not counted')[2])->toBe('-4,000.00')
        ->and($tables['denominations']['total']['cells'][5])->toBe('11,000.00')
        ->and(($this->row)($tables['summary'], 'Cash in hand')[1])->toBe('11,000.00');
});

it('shows cheques cleared into the bank', function () {
    $settlement = ($this->settle)($this->nestle, 10000, ['settlement_date' => now()->subDays(3)->format('Y-m-d')]);
    ($this->settle)($this->nestle, 10000);
    ($this->cheque)($settlement, 7500, ['cleared_via' => 'bank']);

    $tables = ($this->sheet)($this->nestle->id);

    expect($tables['collection']['rows'][0][3])->toBe('-')
        ->and($tables['collection']['rows'][0][7])->toBe('7,500.00')
        ->and($tables['collection']['rows'][0][8])->toBe('10,000.00')
        ->and($tables['collection']['total']['cells'][5])->toBe('7,500.00')
        ->and(($this->row)($tables['summary'], 'Cash in hand')[1])->toBe('10,000.00');
});

it('ignores draft settlements', function () {
    ($this->settle)($this->nestle, 10000);
    ($this->settle)($this->nestle, 50000, ['status' => 'draft']);

    $tables = ($this->sheet)($this->nestle->id);

    expect($tables['collection']['rows'][0][2])->toBe('10,000.00')
        ->and($tables['denominations']['total']['cells'][5])->toBe('10,000.00')
        ->and($tables['salesmen']['rows'])->toHaveCount(1);
});

it('excludes bank transfer vouchers from cash expenses', function () {
    ($this->settle)($this->nestle, 10000);
    ($this->voucher)($this->nestle, 1000, ['denom_1000' => 1]);
    ($this->voucher)($this->nestle, 3000, ['denom_1000' => 3], ['payment_method' => ExpenseVoucher::METHOD_BANK_TRANSFER]);
    ($this->voucher)($this->nestle, 2000, null, ['payment_method' => ExpenseVoucher::METHOD_CHEQUE, 'payment_type' => ExpenseVoucher::TYPE_ADVANCE]);

    $tables = ($this->sheet)($this->nestle->id);

    expect($tables['expenses']['rows'])->toHaveCount(1)
        ->and($tables['expenses']['total']['cells'][0])->toBe('1,000.00')
        ->and(($this->row)($tables['denominations'], '1,000')[3])->toBe(1)
        ->and($tables['denominations']['total']['cells'][5])->toBe('9,000.00')
        ->and(($this->row)($tables['summary'], 'Cash in hand')[1])->toBe('9,000.00');
});

it('labels general Moon Traders expenses and shows them under all companies only', function () {
    ($this->settle)($this->nestle, 10000);
    ($this->voucher)(null, 700, ['denom_500' => 1, 'denom_100' => 2], ['party_name' => 'Office tea']);

    $all = ($this->sheet)('all');
    expect(($this->row)($all['expenses'], 'Office tea', 1)[2])->toBe('Moon Traders (general)')
        ->and($all['denominations']['total']['cells'][5])->toBe('9,300.00');

    $nestle = ($this->sheet)($this->nestle->id);
    expect($nestle['expenses']['rows'])->toBe([])
        ->and($nestle['denominations']['total']['cells'][5])->toBe('10,000.00');
});

it('shows a voucher reversed on a later day as cash returned that day and leaves the original day unchanged', function () {
    $yesterday = now()->subDay()->format('Y-m-d');
    ($this->settle)($this->nestle, 10000);
    ($this->settle)($this->nestle, 5000, ['settlement_date' => $yesterday]);
    $voucher = ($this->voucher)($this->nestle, 1000, ['denom_1000' => 1], ['voucher_date' => $yesterday]);
    $sameDay = ($this->voucher)($this->nestle, 400, ['denom_100' => 4]);

    $before = ($this->sheet)($this->nestle->id, $yesterday);

    $voucher->update(['status' => ExpenseVoucher::STATUS_REVERSED, 'reversed_at' => now()]);
    $sameDay->update(['status' => ExpenseVoucher::STATUS_REVERSED, 'reversed_at' => now()]);

    expect(($this->sheet)($this->nestle->id, $yesterday))->toBe($before)
        ->and(($this->row)($before['summary'], 'Cash in hand')[1])->toBe('4,000.00');

    $today = ($this->sheet)($this->nestle->id);
    expect($today['expenses']['rows'])->toHaveCount(1)
        ->and($today['expenses']['rows'][0][1])->toBe('Returned: '.$voucher->voucher_number)
        ->and($today['expenses']['rows'][0][3])->toBe('-1,000.00')
        ->and(($this->row)($today['denominations'], '1,000')[3])->toBe(-1)
        ->and(($this->row)($today['denominations'], '100')[3])->toBe('-')
        ->and($today['denominations']['total']['cells'][5])->toBe('11,000.00')
        ->and(($this->row)($today['summary'], 'Cash in hand')[1])->toBe('11,000.00');
});

it('total collection equals cash plus slips plus online plus cheques received', function () {
    $earlier = ($this->settle)($this->nestle, 1000, ['settlement_date' => now()->subDays(5)->format('Y-m-d')]);
    ($this->cheque)($earlier, 1500);
    $settlement = ($this->settle)($this->nestle, 10000);
    $bank = BankAccount::factory()->create();
    SalesSettlementBankSlip::create(['sales_settlement_id' => $settlement->id, 'employee_id' => $settlement->employee_id, 'bank_account_id' => $bank->id, 'amount' => 4000]);
    SalesSettlementBankTransfer::factory()->create(['sales_settlement_id' => $settlement->id, 'customer_id' => Customer::factory()->create()->id, 'amount' => 3000]);
    ($this->cheque)($settlement, 2000, ['status' => 'pending', 'cleared_date' => null, 'cleared_via' => null]);

    $row = ($this->sheet)($this->nestle->id)['collection']['rows'][0];

    expect(array_slice($row, 2))->toBe(['10,000.00', '1,500.00', '4,000.00', '3,000.00', '2,000.00', '-', '19,000.00']);
});

it('falls back to today for a date that is not a date', function () {
    ($this->settle)($this->nestle, 10000);

    $this->actingAs($this->admin)->get(route('reports.cash-collection-distributor.index', ['supplier_id' => 'all', 'date' => '2026-13-45']))
        ->assertOk()
        ->assertViewHas('date', $this->today)
        ->assertSee('The date was not valid');
});
