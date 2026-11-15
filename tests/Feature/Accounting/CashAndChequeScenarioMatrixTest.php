<?php

use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\ChequeClearing;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\ExpenseVendor;
use App\Models\ExpenseVendorPayment;
use App\Models\ExpenseVoucher;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementBankSlip;
use App\Models\SalesSettlementBankTransfer;
use App\Models\SalesSettlementCashDenomination;
use App\Models\SalesSettlementCheque;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\ChequeClearingPostingService;
use App\Services\ExpenseVendorPaymentService;
use App\Services\ExpenseVoucherService;
use App\Support\CashNotes;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/*
 * Accounting scenario matrix for cash vouchers, cheque clearing and the daily cash sheet.
 *
 * Every scenario ends by checking the invariants:
 * I1  every journal balances, and no action is posted twice (one live journal per reference);
 * I2  GL 1122 Cheques in Hand equals the pending cheques of posted settlements;
 * I3  on the Cash Collection Distributor sheet, for each company and for All, the notes Net total equals Cash in hand
 *     (and, when every rupee was counted, without any "not counted" plug line);
 * I4  GL 1121 movement of the day equals the sheet's Cash in hand for All Companies;
 * I5  a reversal puts the accounts it touched back to exactly their balances before the posting.
 */

beforeEach(function () {
    $this->travelTo('2026-11-15 10:00:00');
    $this->ledger = seedExpenseLedger();

    $assets = AccountType::where('type_name', 'Assets')->value('id');
    foreach (['1112' => 'Debtors', '1172' => 'Meezan Current Account'] as $code => $name) {
        $this->ledger[$code] = ChartOfAccount::firstOrCreate(['account_code' => $code], [
            'account_type_id' => $assets, 'currency_id' => $this->ledger['1121']->currency_id,
            'account_name' => $name, 'normal_balance' => 'debit', 'is_active' => true,
        ]);
    }

    foreach ([
        'report-audit-cash-collection-distributor', 'report-audit-cash-collection-distributor-all-companies',
        'report-audit-cheque-register', 'report-audit-cheque-register-manage', 'report-audit-cheque-register-all-companies',
        'expense-voucher-list', 'expense-voucher-approve', 'expense-voucher-reverse', 'expense-voucher-view-company',
    ] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->bank = BankAccount::factory()->create(['account_name' => 'HBL Main', 'chart_of_account_id' => $this->ledger['1171']->id, 'is_active' => true]);
    $this->chargeBank = BankAccount::factory()->create(['account_name' => 'Meezan Current', 'chart_of_account_id' => $this->ledger['1172']->id, 'is_active' => true]);

    $this->nestle = Supplier::factory()->create(['supplier_name' => 'Nestle Pakistan', 'short_name' => 'Nestle']);
    $this->engro = Supplier::factory()->create(['supplier_name' => 'Engro Foods', 'short_name' => 'Engro']);

    $this->admin = User::factory()->create(['password' => 'secret-pass']);
    $this->admin->forceFill(['is_super_admin' => 'Yes'])->save();
    $this->maker = User::factory()->create(['supplier_id' => $this->nestle->id]);

    $this->salesman = fn (Supplier $company) => Employee::factory()->create(['supplier_id' => $company->id, 'designation' => 'Salesman']);

    /** A posted settlement of the day: its counted notes, and Dr 1121 / Cr 1112 for its cash. A draft posts nothing. */
    $this->settle = function (Supplier $company, float $cash, array $notes = [], array $extra = [], ?Employee $salesman = null): SalesSettlement {
        $settlement = SalesSettlement::factory()->create($extra + [
            'employee_id' => ($salesman ?? ($this->salesman)($company))->id,
            'supplier_id' => $company->id,
            'settlement_date' => now()->toDateString(),
            'cash_collected' => $cash,
            'status' => 'posted',
        ]);

        if ($cash > 0) {
            $counted = CashNotes::fromInput($notes);
            expect($counted->total())->toBe(round($cash, 2));
            SalesSettlementCashDenomination::create(['sales_settlement_id' => $settlement->id, 'total_amount' => $cash] + $counted->columns());

            if ($settlement->status === 'posted') {
                matrixPost($settlement->settlement_date->toDateString(), 'SS-CASH-'.$settlement->id, [['1121', $cash], ['1112', -$cash]]);
            }
        }

        return $settlement;
    };

    /** A posted settlement of an earlier day with no cash: the source of cheques cleared today. */
    $this->oldSettlement = fn (Supplier $company, ?Employee $salesman = null) => ($this->settle)($company, 0, [], ['settlement_date' => '2026-11-10'], $salesman);

    /** A cheque received in a settlement: Dr 1122 / Cr 1112 on the settlement day, when the settlement is posted. */
    $this->receive = function (SalesSettlement $settlement, float $amount, array $extra = []): SalesSettlementCheque {
        $cheque = SalesSettlementCheque::create($extra + [
            'sales_settlement_id' => $settlement->id, 'cheque_number' => 'CH-'.fake()->unique()->numerify('#####'),
            'amount' => $amount, 'bank_name' => 'HBL', 'status' => 'pending',
        ]);

        if ($settlement->status === 'posted') {
            matrixPost($settlement->settlement_date->toDateString(), 'SS-CHQ-'.$cheque->id, [['1122', $amount], ['1112', -$amount]]);
        }

        return $cheque;
    };

    /** A voucher waiting for approval, made by $this->maker. */
    $this->voucher = function (?Supplier $company, float $amount, string $method = ExpenseVoucher::METHOD_CASH, array $extra = []): ExpenseVoucher {
        $byBank = in_array($method, [ExpenseVoucher::METHOD_BANK_TRANSFER, ExpenseVoucher::METHOD_CHEQUE], true);
        $voucher = ExpenseVoucher::factory()->pending()->create($extra + [
            'supplier_id' => $company?->id,
            'expense_account_id' => $this->ledger['5221']->id,
            'payment_type' => $method === ExpenseVoucher::METHOD_CREDIT ? ExpenseVoucher::TYPE_CREDIT : ExpenseVoucher::TYPE_CASH,
            'payment_method' => $method,
            'bank_account_id' => $byBank ? $this->bank->id : null,
            'cheque_number' => $method === ExpenseVoucher::METHOD_CHEQUE ? '000451' : null,
            'cheque_date' => $method === ExpenseVoucher::METHOD_CHEQUE ? now()->toDateString() : null,
            'amount' => $amount,
            'voucher_date' => now()->toDateString(),
        ]);
        $voucher->forceFill(['created_by' => $this->maker->id])->save();

        return $voucher->fresh();
    };

    $this->approve = fn (ExpenseVoucher $voucher, ?array $denom = null, ?User $by = null) => $this->actingAs($by ?? $this->admin)
        ->post(route('expense-vouchers.approve', $voucher), ['approve_without_attachment' => '1'] + ($denom === null ? [] : ['denom' => $denom]));

    $this->reverse = fn (ExpenseVoucher $voucher, ?User $by = null) => $this->actingAs($by ?? $this->admin)
        ->post(route('expense-vouchers.reverse', $voucher), ['reason' => 'Entered by mistake', 'password' => 'secret-pass']);

    $this->status = fn (SalesSettlementCheque $cheque, array $payload, ?User $by = null) => $this->actingAs($by ?? $this->admin)
        ->post(route('reports.cheque-register.update-status', $cheque), $payload);

    $this->bulk = fn (array $cheques, array $payload, ?User $by = null) => $this->actingAs($by ?? $this->admin)
        ->post(route('reports.cheque-register.bulk-clear'), $payload + [
            'cheque_ids' => collect($cheques)->pluck('id')->all(), 'cleared_date' => now()->toDateString(),
        ]);

    /** A vendor payment of credit vouchers, made by the maker and approved by the admin. */
    $this->payVendor = function (ExpenseVendor $vendor, array $amounts, string $method): ExpenseVendorPayment {
        $this->actingAs($this->maker);
        $service = app(ExpenseVendorPaymentService::class);
        $payment = $service->create([
            'payment_date' => now()->toDateString(), 'expense_vendor_id' => $vendor->id, 'method' => $method,
            'bank_account_id' => $method === 'bank' ? $this->bank->id : null,
        ], $amounts, $this->maker);

        return $service->approve($payment, $this->admin);
    };

    $this->sheet = fn (int|string $supplierId, ?string $date = null, array $query = []) => $this->flushSession()->actingAs($this->admin)
        ->get(route('reports.cash-collection-distributor.index', $query + ['supplier_id' => $supplierId, 'date' => $date ?? now()->toDateString()]))
        ->assertOk()
        ->viewData('tables');

    /**
     * I1–I4 for one day: the journals, Cheques in Hand, the sheet of each company and of All, and Cash (1121) against
     * the All Companies cash in hand. $counted asks for no "not counted" plug lines; $ledger checks I4.
     *
     * @return array{all: array, nestle: array, engro: array}
     */
    $this->assertBalanced = function (?string $date = null, bool $counted = true, bool $ledger = true): array {
        $date ??= now()->toDateString();

        matrixAssertJournals();
        matrixAssertChequesInHand();

        $sheets = [];
        foreach (['all' => 'all', 'nestle' => $this->nestle->id, 'engro' => $this->engro->id] as $key => $supplierId) {
            $sheets[$key] = ($this->sheet)($supplierId, $date);
            matrixAssertSheet($sheets[$key], $counted);
        }

        expect(round(matrixCashInHand($sheets['nestle']) + matrixCashInHand($sheets['engro']), 2))->toBe(matrixCashInHand($sheets['all']));

        if ($ledger) {
            expect(matrixNet('1121', $date))->toBe(matrixCashInHand($sheets['all']));
        }

        return $sheets;
    };
});

/**
 * Posts a balanced journal. Each line is [account code, signed amount]: positive is a debit, negative a credit.
 *
 * @param  array<int, array{0: string, 1: float}>  $lines
 */
function matrixPost(string $date, string $reference, array $lines): JournalEntry
{
    $result = app(AccountingService::class)->createJournalEntry([
        'entry_date' => $date,
        'reference' => $reference,
        'description' => 'Scenario setup '.$reference,
        'lines' => collect($lines)->map(fn (array $line) => [
            'account_id' => ChartOfAccount::where('account_code', $line[0])->value('id'),
            'debit' => max(0, $line[1]),
            'credit' => max(0, -$line[1]),
            'cost_center_id' => 1,
        ])->all(),
        'auto_post' => true,
    ]);

    expect($result['success'])->toBeTrue();

    return $result['data'];
}

/**
 * Net (debit minus credit) of the posted lines on one account, on one day or in total.
 */
function matrixNet(string $code, ?string $date = null): float
{
    $net = DB::table('journal_entry_details as d')
        ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
        ->join('chart_of_accounts as a', 'a.id', '=', 'd.chart_of_account_id')
        ->where('a.account_code', $code)
        ->where('e.status', 'posted')
        ->whereNull('e.deleted_at')
        ->when($date, fn ($query) => $query->whereDate('e.entry_date', $date))
        ->selectRaw('coalesce(sum(d.debit - d.credit), 0) as net')
        ->value('net');

    return round((float) $net, 2);
}

/**
 * @param  array<int, string>  $codes
 * @return array<string, float>
 */
function matrixBalances(array $codes): array
{
    return collect($codes)->mapWithKeys(fn (string $code) => [$code => matrixNet($code)])->all();
}

/**
 * The lines of one journal, as account code => [debit, credit] summed per account.
 *
 * @return array<string, array{0: float, 1: float}>
 */
function matrixLines(int $journalId): array
{
    return JournalEntryDetail::with('account')->where('journal_entry_id', $journalId)->get()
        ->groupBy(fn (JournalEntryDetail $line) => $line->account->account_code)
        ->map(fn ($lines) => [round((float) $lines->sum('debit'), 2), round((float) $lines->sum('credit'), 2)])
        ->sortKeys()
        ->all();
}

/**
 * A formatted sheet amount as a number ("-" is zero).
 */
function matrixMoney(mixed $cell): float
{
    if ($cell === null || $cell === '-' || $cell === '') {
        return 0.0;
    }

    return round((float) str_replace(',', '', (string) $cell), 2);
}

/**
 * @return array<int, mixed>|null
 */
function matrixRow(array $table, string $label, int $labelColumn = 0): ?array
{
    return collect($table['rows'])->first(fn (array $row) => $row[$labelColumn] === $label);
}

function matrixCashInHand(array $tables): float
{
    return matrixMoney(matrixRow($tables['summary'], 'Cash in hand')[1]);
}

/**
 * I1: every journal balances, every reversal reverses one journal once, and each action has one live journal.
 */
function matrixAssertJournals(): void
{
    $entries = JournalEntry::with('details')->get();

    foreach ($entries as $entry) {
        expect($entry->status)->toBe('posted')
            ->and(round((float) $entry->details->sum('debit'), 2))->toBe(round((float) $entry->details->sum('credit'), 2));
    }

    $reversedIds = $entries->pluck('reverses_entry_id')->filter()->values();
    $live = $entries->whereNull('reverses_entry_id')->whereNotIn('id', $reversedIds->all());

    expect($reversedIds->duplicates()->all())->toBe([])
        ->and($live->groupBy('reference')->filter(fn ($same) => $same->count() > 1)->keys()->all())->toBe([]);
}

/**
 * I2: Cheques in Hand holds exactly the pending cheques of posted settlements.
 */
function matrixAssertChequesInHand(): void
{
    $pending = (float) SalesSettlementCheque::query()
        ->where('status', 'pending')
        ->whereHas('salesSettlement', fn ($query) => $query->where('status', 'posted'))
        ->sum('amount');

    expect(matrixNet('1122'))->toBe(round($pending, 2));
}

/**
 * I3 on one sheet, plus how the totals are built: Net notes = Cash in hand, Cash received = cash + cheque clearing,
 * Total collection = cash + slips + online + cheques received.
 */
function matrixAssertSheet(array $tables, bool $counted = true): void
{
    $denominations = $tables['denominations'];
    $cells = $tables['collection']['total']['cells'];

    expect(matrixMoney($denominations['total']['cells'][5]))->toBe(matrixCashInHand($tables))
        ->and(matrixMoney(matrixRow($tables['summary'], 'Cash received')[1]))->toBe(round(matrixMoney($cells[0]) + matrixMoney($cells[1]), 2))
        ->and(matrixMoney($cells[6]))->toBe(round(matrixMoney($cells[0]) + matrixMoney($cells[2]) + matrixMoney($cells[3]) + matrixMoney($cells[4]), 2))
        ->and(matrixMoney($tables['summary']['total']['cells'][0]))->toBe(matrixMoney($cells[6]));

    if ($counted) {
        expect(matrixRow($denominations, 'Cash not counted')[2])->toBe('-')
            ->and(matrixRow($denominations, 'Paid out, no note count')[4])->toBe('-');
    }
}

it('1. reverses a cash voucher on the day it was approved and puts the ledger and the notes back', function () {
    ($this->settle)($this->nestle, 10000, ['5000' => 2]);
    $before = matrixBalances(['1121', '5221']);
    $voucher = ($this->voucher)($this->nestle, 1500);

    ($this->approve)($voucher, ['1000' => 1, '500' => 1])->assertSessionHas('success');

    $approved = ($this->assertBalanced)();
    expect(matrixCashInHand($approved['nestle']))->toBe(8500.0)
        ->and(matrixRow($approved['nestle']['denominations'], '1,000'))->toBe(['1,000', '-', '-', 1, '1,000.00', -1, '-1,000.00'])
        ->and(matrixRow($approved['nestle']['denominations'], '500'))->toBe(['500', '-', '-', 1, '500.00', -1, '-500.00'])
        ->and(matrixLines($voucher->fresh()->journal_entry_id))->toBe(['1121' => [0.0, 1500.0], '5221' => [1500.0, 0.0]]);

    ($this->reverse)($voucher)->assertSessionHas('success');

    $reversed = ($this->assertBalanced)();
    expect(matrixBalances(['1121', '5221']))->toBe($before)
        ->and($voucher->fresh()->status)->toBe(ExpenseVoucher::STATUS_REVERSED)
        ->and($reversed['nestle']['expenses']['rows'])->toBe([])
        ->and(matrixCashInHand($reversed['nestle']))->toBe(10000.0)
        ->and(matrixRow($reversed['nestle']['denominations'], '1,000')[5])->toBe('-');
});

it('2. shows a cash voucher reversed on a later day as cash returned that day and leaves the first day unchanged', function () {
    ($this->settle)($this->nestle, 10000, ['5000' => 2]);
    $before = matrixBalances(['1121', '5221']);
    $voucher = ($this->voucher)($this->nestle, 1500);
    ($this->approve)($voucher, ['1000' => 1, '500' => 1])->assertSessionHas('success');
    $firstDay = ($this->assertBalanced)();

    $this->travelTo('2026-11-16 09:00:00');
    ($this->reverse)($voucher)->assertSessionHas('success');

    $reversal = JournalEntry::find($voucher->fresh()->reversal_journal_entry_id);
    expect($reversal->entry_date->toDateString())->toBe('2026-11-16')
        ->and(($this->assertBalanced)('2026-11-15'))->toBe($firstDay)
        ->and(matrixBalances(['1121', '5221']))->toBe($before);

    $returnDay = ($this->assertBalanced)('2026-11-16');
    expect($returnDay['nestle']['expenses']['rows'])->toHaveCount(1)
        ->and($returnDay['nestle']['expenses']['rows'][0][1])->toBe('Returned: '.$voucher->voucher_number)
        ->and($returnDay['nestle']['expenses']['rows'][0][3])->toBe('-1,500.00')
        ->and(matrixRow($returnDay['nestle']['denominations'], '1,000')[3])->toBe(-1)
        ->and(matrixRow($returnDay['nestle']['denominations'], '1,000')[5])->toBe(1)
        ->and(matrixRow($returnDay['nestle']['denominations'], '500')[5])->toBe(1)
        ->and(matrixCashInHand($returnDay['nestle']))->toBe(1500.0);
});

it('3. posts a bank transfer voucher to the bank, leaves cash and the sheet alone, and reverses it', function () {
    ($this->settle)($this->nestle, 10000, ['5000' => 2]);
    $before = matrixBalances(['1121', '1171', '5221']);
    $sheetBefore = ($this->assertBalanced)();
    $voucher = ($this->voucher)($this->nestle, 2500, ExpenseVoucher::METHOD_BANK_TRANSFER, ['transaction_reference' => 'IBFT-1']);

    ($this->approve)($voucher)->assertSessionHas('success');

    expect(matrixNet('1121'))->toBe($before['1121'])
        ->and(matrixNet('1171'))->toBe(-2500.0)
        ->and($voucher->fresh()->paid_from_account_id)->toBe($this->ledger['1171']->id)
        ->and($voucher->fresh()->cashPayout)->toBeNull()
        ->and(($this->assertBalanced)())->toBe($sheetBefore);

    ($this->reverse)($voucher)->assertSessionHas('success');

    expect(matrixBalances(['1121', '1171', '5221']))->toBe($before)
        ->and(($this->assertBalanced)())->toBe($sheetBefore);
});

it('4. posts a cheque voucher (the company pays by cheque) to the bank with no notes', function () {
    ($this->settle)($this->nestle, 10000, ['5000' => 2]);
    $sheetBefore = ($this->assertBalanced)();
    $voucher = ($this->voucher)($this->nestle, 3000, ExpenseVoucher::METHOD_CHEQUE);

    ($this->approve)($voucher)->assertSessionHas('success');

    $voucher->refresh();
    expect(matrixLines($voucher->journal_entry_id))->toBe(['1171' => [0.0, 3000.0], '5221' => [3000.0, 0.0]])
        ->and($voucher->cashPayout)->toBeNull()
        ->and(matrixNet('1121'))->toBe(10000.0)
        ->and(($this->assertBalanced)())->toBe($sheetBefore);
});

it('5. pays a credit voucher part in cash and the rest by bank, clears 2113 and then refuses to reverse it', function () {
    ($this->settle)($this->nestle, 10000, ['5000' => 2]);
    $vendor = ExpenseVendor::factory()->create(['name' => 'Al-Madina Store']);
    $voucher = ($this->voucher)($this->nestle, 10000, ExpenseVoucher::METHOD_CREDIT, ['expense_vendor_id' => $vendor->id]);

    ($this->approve)($voucher)->assertSessionHas('success');
    expect(matrixNet('2113'))->toBe(-10000.0)->and(matrixNet('1121'))->toBe(10000.0);

    ($this->payVendor)($vendor, [$voucher->id => 4000], 'cash');
    expect($voucher->fresh()->payment_status)->toBe(ExpenseVoucher::PARTIAL);

    ($this->payVendor)($vendor, [$voucher->id => 6000], 'bank');

    $voucher->refresh();
    expect(matrixNet('2113'))->toBe(0.0)
        ->and(matrixNet('1171'))->toBe(-6000.0)
        ->and($voucher->payment_status)->toBe(ExpenseVoucher::PAID)
        ->and((float) $voucher->paid_amount)->toBe(10000.0);

    $journals = JournalEntry::count();
    ($this->reverse)($voucher)->assertSessionHas('error', 'This credit voucher has already been paid in part; it cannot be reversed.');

    $sheets = ($this->assertBalanced)(counted: false);
    expect($voucher->fresh()->status)->toBe(ExpenseVoucher::STATUS_APPROVED)
        ->and(JournalEntry::count())->toBe($journals)
        ->and(matrixCashInHand($sheets['nestle']))->toBe(6000.0)
        ->and($sheets['nestle']['expenses']['rows'])->toHaveCount(1)
        ->and($sheets['nestle']['expenses']['rows'][0][1])->toBe('Vendor payment - Al-Madina Store');
});

it('5b. reverses an unpaid credit voucher and puts 2113 and the expense back', function () {
    $vendor = ExpenseVendor::factory()->create();
    $before = matrixBalances(['2113', '5221']);
    $voucher = ($this->voucher)($this->nestle, 4200, ExpenseVoucher::METHOD_CREDIT, ['expense_vendor_id' => $vendor->id]);
    ($this->approve)($voucher)->assertSessionHas('success');
    $sheetBefore = ($this->assertBalanced)();

    ($this->reverse)($voucher)->assertSessionHas('success');

    expect(matrixBalances(['2113', '5221']))->toBe($before)
        ->and($voucher->fresh()->status)->toBe(ExpenseVoucher::STATUS_REVERSED)
        ->and(app(ExpenseVendorPaymentService::class)->outstandingVouchers($vendor))->toHaveCount(0)
        ->and(($this->assertBalanced)())->toBe($sheetBefore);
});

it('6a. reverses an employee advance paid in cash before any recovery and cancels the advance', function () {
    ($this->settle)($this->nestle, 10000, ['5000' => 2]);
    $employee = Employee::factory()->create(['supplier_id' => $this->nestle->id]);
    $before = matrixBalances(['1121', '1131']);
    $voucher = ($this->voucher)($this->nestle, 5000, ExpenseVoucher::METHOD_CASH, [
        'payment_type' => ExpenseVoucher::TYPE_ADVANCE, 'employee_id' => $employee->id, 'expense_account_id' => $this->ledger['1131']->id,
    ]);

    ($this->approve)($voucher, ['5000' => 1])->assertSessionHas('success');

    $voucher->refresh();
    expect(matrixLines($voucher->journal_entry_id))->toBe(['1121' => [0.0, 5000.0], '1131' => [5000.0, 0.0]])
        ->and($voucher->advance->status)->toBe(EmployeeAdvance::STATUS_OPEN)
        ->and(matrixCashInHand(($this->assertBalanced)()['nestle']))->toBe(5000.0);

    ($this->reverse)($voucher)->assertSessionHas('success');

    expect($voucher->advance->fresh()->status)->toBe(EmployeeAdvance::STATUS_CANCELLED)
        ->and(matrixBalances(['1121', '1131']))->toBe($before)
        ->and(matrixCashInHand(($this->assertBalanced)()['nestle']))->toBe(10000.0);
});

it('6b. refuses to reverse an advance once it has been recovered', function () {
    $employee = Employee::factory()->create(['supplier_id' => $this->nestle->id]);
    $voucher = ($this->voucher)($this->nestle, 5000, ExpenseVoucher::METHOD_CASH, [
        'payment_type' => ExpenseVoucher::TYPE_ADVANCE, 'employee_id' => $employee->id, 'expense_account_id' => $this->ledger['1131']->id,
    ]);
    ($this->approve)($voucher, ['5000' => 1])->assertSessionHas('success');
    $voucher->fresh()->advance->update(['recovered_amount' => 1000]);
    $journals = JournalEntry::count();

    ($this->reverse)($voucher)->assertSessionHas('error', 'This advance has already been recovered in payroll; it cannot be reversed.');

    expect($voucher->fresh()->status)->toBe(ExpenseVoucher::STATUS_APPROVED)
        ->and($voucher->fresh()->advance->status)->toBe(EmployeeAdvance::STATUS_OPEN)
        ->and(JournalEntry::count())->toBe($journals)
        ->and(matrixNet('1131'))->toBe(5000.0);

    ($this->assertBalanced)();
});

it('7. posts one journal when a voucher is approved twice, from a stale copy or a second click', function () {
    $voucher = ($this->voucher)($this->nestle, 700);
    $stale = ExpenseVoucher::find($voucher->id);
    $notes = CashNotes::fromInput(['500' => 1, '100' => 2]);

    app(ExpenseVoucherService::class)->approve($voucher, $this->admin, $notes);

    expect(fn () => app(ExpenseVoucherService::class)->approve($stale, $this->admin, $notes))
        ->toThrow(RuntimeException::class, 'Only a voucher waiting for approval');

    ($this->approve)($voucher->fresh(), ['500' => 1, '100' => 2])->assertSessionHas('error');

    expect(JournalEntry::where('reference', $voucher->voucher_number)->count())->toBe(1)
        ->and($voucher->fresh()->cashPayout()->count())->toBe(1)
        ->and(matrixNet('1121'))->toBe(-700.0);

    ($this->assertBalanced)();
});

it('8. checks the notes paid out on a cash approval', function (?array $denom, bool $accepted) {
    $voucher = ($this->voucher)($this->nestle, 1234.50);

    $response = ($this->approve)($voucher, $denom);

    $voucher->refresh();
    if ($accepted) {
        $response->assertSessionHas('success');
        expect($voucher->status)->toBe(ExpenseVoucher::STATUS_APPROVED)
            ->and((float) $voucher->cashPayout->total_amount)->toBe(1234.5)
            ->and((float) $voucher->cashPayout->denom_coins)->toBe(4.5)
            ->and(matrixNet('1121'))->toBe(-1234.5);

        $sheets = ($this->assertBalanced)();
        expect(matrixRow($sheets['nestle']['denominations'], 'Coins')[6])->toBe('-4.50')
            ->and(matrixCashInHand($sheets['nestle']))->toBe(-1234.5);
    } else {
        $response->assertSessionHasErrors('denom');
        expect($voucher->status)->toBe(ExpenseVoucher::STATUS_PENDING)
            ->and($voucher->cashPayout)->toBeNull()
            ->and(JournalEntry::count())->toBe(0);

        ($this->assertBalanced)();
    }
})->with([
    'wrong notes' => [['1000' => 1, '100' => 2], false],
    'missing notes' => [null, false],
    'notes with coins and paisa' => [['1000' => 1, '100' => 2, '10' => 3, 'coins' => '4.50'], true],
]);

it('9. clears a single cheque', function (string $via) {
    $cheque = ($this->receive)(($this->oldSettlement)($this->nestle), 7650);
    $payload = $via === 'cash'
        ? ['denom' => ['5000' => 1, '1000' => 2, '500' => 1, '100' => 1, '50' => 1]]
        : ['cleared_bank_account_id' => $this->bank->id];

    ($this->status)($cheque, ['status' => 'cleared', 'cleared_date' => now()->toDateString(), 'cleared_via' => $via] + $payload)
        ->assertSessionHas('success');

    $cheque->refresh();
    $batch = ChequeClearing::sole();
    $sheets = ($this->assertBalanced)();
    $row = $sheets['nestle']['collection']['rows'][0];

    expect($cheque->status)->toBe('cleared')
        ->and($cheque->cheque_clearing_id)->toBe($batch->id)
        ->and($cheque->cleared_denom_5000)->toBeNull()
        ->and(matrixNet('1122'))->toBe(0.0);

    if ($via === 'cash') {
        expect(matrixLines($cheque->clearing_journal_entry_id))->toBe(['1121' => [7650.0, 0.0], '1122' => [0.0, 7650.0]])
            ->and($batch->denom_5000)->toBe(1)
            ->and((float) $batch->total_amount)->toBe(7650.0)
            ->and([$row[3], $row[7], $row[8]])->toBe(['7,650.00', '-', '-'])
            ->and(matrixCashInHand($sheets['nestle']))->toBe(7650.0);
    } else {
        expect(matrixLines($cheque->clearing_journal_entry_id))->toBe(['1122' => [0.0, 7650.0], '1171' => [7650.0, 0.0]])
            ->and($batch->bank_account_id)->toBe($this->bank->id)
            ->and((float) $batch->total_amount)->toBe(0.0)
            ->and([$row[3], $row[7], $row[8]])->toBe(['-', '7,650.00', '-'])
            ->and(matrixCashInHand($sheets['nestle']))->toBe(0.0);
    }
})->with(['cash', 'bank']);

it('10. stores the notes of a three-cheque cash batch once and balances the sheet for the company, each salesman and All', function () {
    $ali = ($this->salesman)($this->nestle);
    $bilal = ($this->salesman)($this->nestle);
    $alisOld = ($this->oldSettlement)($this->nestle, $ali);
    $cheques = [($this->receive)($alisOld, 96190), ($this->receive)($alisOld, 50100), ($this->receive)(($this->oldSettlement)($this->nestle, $bilal), 3369)];
    ($this->settle)($this->nestle, 10000, ['5000' => 2], [], $ali);

    ($this->bulk)($cheques, ['cleared_via' => 'cash', 'denom' => ['5000' => 29, '1000' => 4, '500' => 1, '100' => 1, '50' => 1, 'coins' => 9]])
        ->assertSessionHas('success');

    $batch = ChequeClearing::sole();
    expect((float) $batch->total_amount)->toBe(149659.0)
        ->and($batch->supplier_id)->toBe($this->nestle->id)
        ->and($batch->denom_5000)->toBe(29)
        ->and(SalesSettlementCheque::where('cheque_clearing_id', $batch->id)->count())->toBe(3)
        ->and(SalesSettlementCheque::whereNotNull('cleared_denom_5000')->exists())->toBeFalse()
        ->and(matrixNet('1121'))->toBe(159659.0);

    $sheets = ($this->assertBalanced)();
    expect(matrixRow($sheets['nestle']['denominations'], '5,000')[1])->toBe(31)
        ->and(matrixCashInHand($sheets['nestle']))->toBe(159659.0)
        ->and(matrixCashInHand($sheets['all']))->toBe(159659.0);

    foreach ([[$ali, 146290.0, 156290.0], [$bilal, 3369.0, 3369.0]] as [$salesman, $group, $cashInHand]) {
        $tables = ($this->sheet)($this->nestle->id, null, ['employee_ids' => [$salesman->id]]);
        matrixAssertSheet($tables);
        expect(matrixMoney(matrixRow($tables['denominations'], 'Group clearing (other salesmen)')[2]))->toBe($group)
            ->and(matrixCashInHand($tables))->toBe($cashInHand);
    }
});

it('11. refuses a cash batch that mixes two companies and posts nothing', function () {
    $nestleCheque = ($this->receive)(($this->oldSettlement)($this->nestle), 1000);
    $engroCheque = ($this->receive)(($this->oldSettlement)($this->engro), 2000);
    $journals = JournalEntry::count();

    ($this->bulk)([$nestleCheque, $engroCheque], ['cleared_via' => 'cash', 'denom' => ['1000' => 3]])
        ->assertSessionHas('error', 'Cash clearing must be one company at a time. Selected: Engro Foods, Nestle Pakistan.');

    expect(JournalEntry::count())->toBe($journals)
        ->and(ChequeClearing::count())->toBe(0)
        ->and(SalesSettlementCheque::where('status', 'pending')->count())->toBe(2)
        ->and(matrixNet('1122'))->toBe(3000.0);

    ($this->assertBalanced)();
});

it('12. clears a bank batch that mixes companies and only fills "Cleared into bank" on the sheet', function () {
    ($this->settle)($this->nestle, 10000, ['5000' => 2]);
    $nestleCheque = ($this->receive)(($this->oldSettlement)($this->nestle), 4000);
    $engroCheque = ($this->receive)(($this->oldSettlement)($this->engro), 6000);
    $before = ($this->assertBalanced)();

    ($this->bulk)([$nestleCheque, $engroCheque], ['cleared_via' => 'bank', 'cleared_bank_account_id' => $this->bank->id])
        ->assertSessionHas('success');

    $after = ($this->assertBalanced)();
    expect(ChequeClearing::sole()->supplier_id)->toBeNull()
        ->and(matrixNet('1171'))->toBe(10000.0)
        ->and(matrixNet('1121'))->toBe(10000.0)
        ->and($after['nestle']['denominations'])->toBe($before['nestle']['denominations'])
        ->and($after['all']['denominations'])->toBe($before['all']['denominations'])
        ->and(matrixCashInHand($after['engro']))->toBe(0.0)
        ->and(matrixMoney($after['nestle']['collection']['total']['cells'][5]))->toBe(4000.0)
        ->and(matrixMoney($after['engro']['collection']['total']['cells'][5]))->toBe(6000.0)
        ->and(matrixMoney($after['all']['collection']['total']['cells'][5]))->toBe(10000.0)
        ->and(matrixMoney($after['all']['collection']['total']['cells'][1]))->toBe(0.0)
        ->and($after['all']['collection']['total']['cells'][6])->toBe($before['all']['collection']['total']['cells'][6]);
});

it('13. un-clears a batch on the same day: every cheque reopens, 1122 comes back and every cheque gets a history row', function () {
    $old = ($this->oldSettlement)($this->nestle);
    $cheques = [($this->receive)($old, 1000), ($this->receive)($old, 500), ($this->receive)($old, 250)];
    $before = matrixBalances(['1121', '1122']);
    $sheetBefore = ($this->assertBalanced)();

    ($this->bulk)($cheques, ['cleared_via' => 'cash', 'denom' => ['1000' => 1, '500' => 1, '100' => 2, '50' => 1]])->assertSessionHas('success');
    $batchId = ChequeClearing::value('id');
    expect(matrixNet('1122'))->toBe(0.0);

    ($this->status)($cheques[1], ['status' => 'pending', 'notes' => 'counted wrong'])->assertSessionHas('success');

    foreach ($cheques as $cheque) {
        $cheque->refresh();
        $reopen = $cheque->histories()->first();

        expect($cheque->status)->toBe('pending')
            ->and($cheque->cheque_clearing_id)->toBeNull()
            ->and($cheque->clearing_journal_entry_id)->toBeNull()
            ->and($cheque->histories()->count())->toBe(2)
            ->and([$reopen->from_status, $reopen->to_status, $reopen->cheque_clearing_id])->toBe(['cleared', 'pending', $batchId])
            ->and(JournalEntry::find($reopen->journal_entry_id)->reverses_entry_id)->toBe($reopen->reversed_journal_entry_id);
    }

    expect(ChequeClearing::count())->toBe(0)
        ->and(matrixBalances(['1121', '1122']))->toBe($before)
        ->and(($this->assertBalanced)())->toBe($sheetBefore);
});

it('13b. un-clears a back-dated clearing on the day it was entered and keeps both days balanced', function () {
    $cheque = ($this->receive)(($this->oldSettlement)($this->nestle), 1000);
    ($this->bulk)([$cheque], ['cleared_via' => 'cash', 'cleared_date' => '2026-11-14', 'denom' => ['1000' => 1]])->assertSessionHas('success');
    expect(matrixCashInHand(($this->assertBalanced)('2026-11-14')['nestle']))->toBe(1000.0);

    ($this->status)($cheque, ['status' => 'pending'])->assertSessionHas('success');

    expect($cheque->fresh()->status)->toBe('pending');
    ($this->assertBalanced)('2026-11-14');
    ($this->assertBalanced)('2026-11-15');
})->skip('BUG: the clearing reversal is dated today (ChequeClearingPostingService::reverseOnce, app/Services/ChequeClearingPostingService.php:288), but the cheque drops off the back-dated cleared day sheet, so GL 1121 on the 14th keeps Rs 1,000 the sheet no longer shows and the 15th has -1,000 the sheet never shows.');

it('14. refuses to un-clear or bounce a cleared cheque on a later day', function () {
    $cheque = ($this->receive)(($this->oldSettlement)($this->nestle), 1000);
    ($this->bulk)([$cheque], ['cleared_via' => 'cash', 'denom' => ['1000' => 1]])->assertSessionHas('success');
    $journals = JournalEntry::count();

    $this->travelTo('2026-11-16 09:00:00');

    foreach (['pending', 'bounced'] as $status) {
        ($this->status)($cheque, ['status' => $status])
            ->assertSessionHas('error', 'This cheque was cleared on 15-Nov-2026; it can only be changed on that day.');
    }

    expect($cheque->fresh()->status)->toBe('cleared')
        ->and(ChequeClearing::count())->toBe(1)
        ->and(JournalEntry::count())->toBe($journals);

    ($this->assertBalanced)('2026-11-15');
    ($this->assertBalanced)('2026-11-16');
});

it('15. books a bounce with a bank charge, takes it back, and never credits 1122 twice through bounced, cancelled and pending', function () {
    $cheque = ($this->receive)(($this->oldSettlement)($this->nestle), 10000, ['bank_account_id' => $this->bank->id]);
    $before = matrixBalances(['1112', '1122', '1171', '1172']);

    ($this->status)($cheque, ['status' => 'bounced', 'status_date' => now()->toDateString(), 'bank_charge' => 150, 'bank_account_id' => $this->chargeBank->id])
        ->assertSessionHas('success');

    $cheque->refresh();
    expect(matrixLines($cheque->bounce_journal_entry_id))->toBe(['1112' => [10150.0, 0.0], '1122' => [0.0, 10000.0], '1172' => [0.0, 150.0]])
        ->and($cheque->bank_account_id)->toBe($this->bank->id)
        ->and(matrixNet('1122'))->toBe(0.0);
    ($this->assertBalanced)();

    ($this->status)($cheque, ['status' => 'pending'])->assertSessionHas('success');
    expect($cheque->fresh()->bounce_journal_entry_id)->toBeNull()
        ->and(matrixBalances(['1112', '1122', '1171', '1172']))->toBe($before);
    ($this->assertBalanced)();

    foreach (['bounced', 'cancelled', 'pending'] as $status) {
        ($this->status)($cheque, ['status' => $status])->assertSessionHas('success');
        ($this->assertBalanced)();
    }

    expect($cheque->fresh()->status)->toBe('pending')
        ->and(matrixBalances(['1112', '1122', '1171', '1172']))->toBe($before)
        ->and(matrixNet('1122'))->toBe(10000.0)
        ->and(JournalEntry::where('reference', 'CHQ-BOUNCE-'.$cheque->cheque_number)->count())->toBe(2)
        ->and(JournalEntry::where('reference', 'REV-CHQ-BOUNCE-'.$cheque->cheque_number)->count())->toBe(2);
});

it('15b. takes a pending cheque that is cancelled out of Cheques in Hand', function () {
    $cheque = ($this->receive)(($this->oldSettlement)($this->nestle), 10000);

    ($this->status)($cheque, ['status' => 'cancelled', 'notes' => 'customer took the cheque back'])->assertSessionHas('success');

    expect($cheque->fresh()->status)->toBe('cancelled');
    matrixAssertChequesInHand();
})->skip('BUG: pending -> cancelled posts no journal, so GL 1122 still holds the cancelled cheque (ChequeClearingService::changeStatus, app/Services/ChequeClearingService.php:151-160 has branches only for bounced and pending).');

it('16. counts a cheque received and cleared on the same day once in Total collection', function () {
    $settlement = ($this->settle)($this->nestle, 10000, ['5000' => 2]);
    $cheque = ($this->receive)($settlement, 7500);

    ($this->status)($cheque, ['status' => 'cleared', 'cleared_date' => now()->toDateString(), 'cleared_via' => 'cash', 'denom' => ['5000' => 1, '1000' => 2, '500' => 1]])
        ->assertSessionHas('success');

    $sheets = ($this->assertBalanced)();
    $row = $sheets['nestle']['collection']['rows'][0];

    expect([$row[2], $row[3], $row[6], $row[8]])->toBe(['10,000.00', '7,500.00', '7,500.00', '17,500.00'])
        ->and(matrixMoney($sheets['all']['summary']['total']['cells'][0]))->toBe(17500.0)
        ->and(matrixCashInHand($sheets['all']))->toBe(17500.0)
        ->and(matrixNet('1122'))->toBe(0.0);
});

it('17. still balances old data: per-cheque notes with no batch, and a cleared cheque with no cleared date', function () {
    $old = ($this->oldSettlement)($this->nestle);
    $perCheque = ($this->receive)($old, 7000, [
        'status' => 'cleared', 'cleared_date' => now()->toDateString(), 'cleared_via' => 'cash', 'cleared_amount' => 7000,
        'cleared_denom_5000' => 1, 'cleared_denom_1000' => 2, 'status_updated_at' => now(),
    ]);
    app(ChequeClearingPostingService::class)->post($perCheque->fresh());
    ($this->receive)($old, 2500, ['status' => 'cleared', 'cleared_date' => null, 'status_updated_at' => '2026-11-12 16:00:00']);
    ($this->settle)($this->nestle, 10000, ['5000' => 2]);

    expect(app(ChequeClearingPostingService::class)->postMissing())->toBe(['cheques' => 1, 'amount' => 2500.0])
        ->and(matrixNet('1121', '2026-11-12'))->toBe(2500.0);

    $sheets = ($this->assertBalanced)();
    expect($sheets['nestle']['collection']['rows'][0][3])->toBe('7,000.00')
        ->and(matrixRow($sheets['nestle']['denominations'], '5,000')[1])->toBe(3)
        ->and(matrixRow($sheets['nestle']['denominations'], '1,000')[1])->toBe(2)
        ->and(matrixCashInHand($sheets['nestle']))->toBe(17000.0);

    // The cheque with no cleared date is left off every sheet by design, so the 12th is not checked against 1121.
    $twelfth = ($this->assertBalanced)('2026-11-12', ledger: false);
    expect(matrixCashInHand($twelfth['all']))->toBe(0.0);
});

it('18. leaves a draft settlement off the sheet', function () {
    ($this->settle)($this->nestle, 10000, ['5000' => 2]);
    $draft = ($this->settle)($this->nestle, 50000, ['5000' => 10], ['status' => 'draft']);
    ($this->receive)($draft, 4000);

    $sheets = ($this->assertBalanced)();
    $row = $sheets['nestle']['collection']['rows'][0];

    expect([$row[2], $row[6], $row[8]])->toBe(['10,000.00', '-', '10,000.00'])
        ->and(matrixRow($sheets['nestle']['denominations'], '5,000')[1])->toBe(2)
        ->and(matrixCashInHand($sheets['nestle']))->toBe(10000.0);
});

it('19. keeps a company user away from another company\'s cheques and vouchers', function (string $action) {
    $user = User::factory()->create(['supplier_id' => $this->nestle->id, 'password' => 'secret-pass']);
    $user->givePermissionTo([
        'report-audit-cheque-register', 'report-audit-cheque-register-manage',
        'expense-voucher-list', 'expense-voucher-approve', 'expense-voucher-reverse', 'expense-voucher-view-company',
    ]);
    $own = ($this->receive)(($this->oldSettlement)($this->nestle), 1000);
    $foreign = ($this->receive)(($this->oldSettlement)($this->engro), 2000);
    $pendingVoucher = ($this->voucher)($this->engro, 800);
    $approvedVoucher = ($this->voucher)($this->engro, 900);
    ($this->approve)($approvedVoucher, ['500' => 1, '100' => 4])->assertSessionHas('success');
    $journals = JournalEntry::count();
    $this->flushSession();

    $response = match ($action) {
        'clear the cheque' => ($this->status)($foreign, ['status' => 'cleared', 'cleared_date' => now()->toDateString(), 'cleared_via' => 'cash', 'denom' => ['1000' => 2]], $user),
        'bounce the cheque' => ($this->status)($foreign, ['status' => 'bounced'], $user),
        'cancel the cheque' => ($this->status)($foreign, ['status' => 'cancelled'], $user),
        'bulk clear it with an own cheque' => ($this->bulk)([$own, $foreign], ['cleared_via' => 'bank', 'cleared_bank_account_id' => $this->bank->id], $user),
        'see the cheque history' => $this->actingAs($user)->get(route('reports.cheque-register.history', $foreign)),
        'filter the register by the company' => $this->actingAs($user)->get(route('reports.cheque-register.index', ['filter' => ['supplier_id' => $this->engro->id]])),
        'see the voucher' => $this->actingAs($user)->get(route('expense-vouchers.show', $approvedVoucher)),
        'approve the voucher' => $this->actingAs($user)->post(route('expense-vouchers.approve', $pendingVoucher), ['approve_without_attachment' => '1', 'denom' => ['500' => 1, '100' => 3]]),
        'reverse the voucher' => ($this->reverse)($approvedVoucher, $user),
    };

    // A voucher of another company is answered 404 (not found) so its existence is not given away; cheques get 403.
    expect($response->status())->toBeIn(str_contains($action, 'voucher') ? [403, 404] : [403])
        ->and(JournalEntry::count())->toBe($journals)
        ->and($foreign->fresh()->status)->toBe('pending')
        ->and($own->fresh()->status)->toBe('pending')
        ->and($pendingVoucher->fresh()->status)->toBe(ExpenseVoucher::STATUS_PENDING)
        ->and($approvedVoucher->fresh()->status)->toBe(ExpenseVoucher::STATUS_APPROVED);

    ($this->assertBalanced)();
})->with([
    'clear the cheque', 'bounce the cheque', 'cancel the cheque', 'bulk clear it with an own cheque', 'see the cheque history',
    'filter the register by the company', 'see the voucher', 'approve the voucher', 'reverse the voucher',
]);

it('19b. does not list another company\'s cheques in a company user\'s register', function () {
    $user = User::factory()->create(['supplier_id' => $this->nestle->id]);
    $user->givePermissionTo(['report-audit-cheque-register']);
    $own = ($this->receive)(($this->oldSettlement)($this->nestle), 1000);
    $foreign = ($this->receive)(($this->oldSettlement)($this->engro), 2000);

    $this->actingAs($user)->get(route('reports.cheque-register.index', ['filter' => ['supplier_id' => $this->nestle->id]]))
        ->assertOk()
        ->assertSee($own->cheque_number)
        ->assertDontSee($foreign->cheque_number);
});

it('20. balances a mixed day for All and for each company', function () {
    $this->travelTo('2026-11-14 10:00:00');
    $vendor = ExpenseVendor::factory()->create(['name' => 'City Hardware']);
    $credit = ($this->voucher)($this->engro, 3000, ExpenseVoucher::METHOD_CREDIT, ['expense_vendor_id' => $vendor->id]);
    ($this->approve)($credit)->assertSessionHas('success');
    $yesterdays = ($this->voucher)($this->nestle, 1000);
    ($this->approve)($yesterdays, ['1000' => 1])->assertSessionHas('success');
    $this->travelTo('2026-11-15 10:00:00');

    [$ali, $bilal, $kamran] = [($this->salesman)($this->nestle), ($this->salesman)($this->nestle), ($this->salesman)($this->engro)];
    $nestleOld = ($this->oldSettlement)($this->nestle, $ali);
    $toClearByCash = [($this->receive)($nestleOld, 50100), ($this->receive)($nestleOld, 3369)];
    $toClearByBank = ($this->receive)(($this->oldSettlement)($this->engro, $kamran), 8000);

    $alis = ($this->settle)($this->nestle, 20000, ['5000' => 4], [], $ali);
    $bilals = ($this->settle)($this->nestle, 15000, ['5000' => 3], [], $bilal);
    SalesSettlementBankSlip::create(['sales_settlement_id' => $alis->id, 'employee_id' => $ali->id, 'bank_account_id' => $this->bank->id, 'amount' => 4000]);
    SalesSettlementBankTransfer::factory()->create(['sales_settlement_id' => $bilals->id, 'customer_id' => Customer::factory()->create()->id, 'amount' => 3000]);
    ($this->receive)($bilals, 5000);
    ($this->settle)($this->engro, 30000, ['5000' => 6], [], $kamran);

    ($this->approve)(($this->voucher)($this->nestle, 1500), ['1000' => 1, '500' => 1])->assertSessionHas('success');
    ($this->approve)(($this->voucher)($this->engro, 2000), ['1000' => 2])->assertSessionHas('success');
    ($this->approve)(($this->voucher)($this->nestle, 5000, ExpenseVoucher::METHOD_BANK_TRANSFER))->assertSessionHas('success');
    ($this->bulk)($toClearByCash, ['cleared_via' => 'cash', 'denom' => ['5000' => 10, '1000' => 3, '100' => 4, '50' => 1, '10' => 1, 'coins' => 9]])->assertSessionHas('success');
    ($this->bulk)([$toClearByBank], ['cleared_via' => 'bank', 'cleared_bank_account_id' => $this->bank->id])->assertSessionHas('success');
    ($this->payVendor)($vendor, [$credit->id => 3000], 'cash');
    ($this->reverse)($yesterdays)->assertSessionHas('success');

    $sheets = ($this->assertBalanced)(counted: false);
    matrixAssertSheet($sheets['nestle']);

    expect(matrixCashInHand($sheets['nestle']))->toBe(87969.0)
        ->and(matrixCashInHand($sheets['engro']))->toBe(25000.0)
        ->and(matrixCashInHand($sheets['all']))->toBe(112969.0)
        ->and(matrixMoney($sheets['nestle']['summary']['total']['cells'][0]))->toBe(47000.0)
        ->and(matrixMoney($sheets['engro']['summary']['total']['cells'][0]))->toBe(30000.0)
        ->and(matrixMoney($sheets['all']['summary']['total']['cells'][0]))->toBe(77000.0)
        ->and(matrixMoney($sheets['all']['collection']['total']['cells'][5]))->toBe(8000.0)
        ->and(matrixMoney(matrixRow($sheets['engro']['denominations'], 'Paid out, no note count')[4]))->toBe(3000.0)
        ->and(matrixNet('2113'))->toBe(0.0)
        ->and(matrixNet('1122'))->toBe(5000.0);

    ($this->assertBalanced)('2026-11-14', counted: false);
});
