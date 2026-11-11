<?php

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationItem;
use App\Models\BankStatementEntry;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\AccountingService;
use App\Services\BankRegister\BankStatementImportService;
use App\Services\BankRegister\SettlementBankRegisterSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    config(['settlement_attachments.enabled' => true, 'settlement_attachments.disk' => 'local', 'bank.attachments.disk' => 'local']);

    $this->user = User::factory()->create(['is_super_admin' => 'Yes']);

    AccountingPeriod::create([
        'name' => 'FY',
        'start_date' => now()->startOfYear()->format('Y-m-d'),
        'end_date' => now()->endOfYear()->format('Y-m-d'),
        'status' => AccountingPeriod::STATUS_OPEN,
    ]);

    $currency = Currency::factory()->base()->create();
    $accountType = AccountType::create(['type_name' => 'Asset', 'report_group' => 'BalanceSheet']);
    $this->income = ChartOfAccount::create([
        'account_code' => '4110', 'account_name' => 'Sales', 'account_type_id' => $accountType->id,
        'currency_id' => $currency->id, 'normal_balance' => 'credit', 'is_group' => false, 'is_active' => true,
    ]);
    $coa = ChartOfAccount::create([
        'account_code' => '1001', 'account_name' => 'Bank', 'account_type_id' => $accountType->id,
        'currency_id' => $currency->id, 'normal_balance' => 'debit', 'is_group' => false, 'is_active' => true,
    ]);

    $this->bank = BankAccount::create([
        'account_name' => 'Main Collection', 'account_number' => '0012345', 'bank_name' => 'HBL',
        'chart_of_account_id' => $coa->id, 'is_active' => true,
    ]);
    $this->customer = Customer::factory()->create(['customer_name' => 'Al Madina Store']);
    $this->salesman = Employee::factory()->create(['name' => 'Imran Khan']);
    $this->goodsIssue = GoodsIssue::factory()->create([
        'status' => 'issued',
        'warehouse_id' => Warehouse::factory()->create()->id,
        'vehicle_id' => Vehicle::factory()->create()->id,
        'employee_id' => $this->salesman->id,
    ]);
    $this->product = Product::factory()->create();

    $this->payload = fn (array $entries = [], array $files = []): array => array_merge([
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $this->goodsIssue->id,
        'items' => [[
            'product_id' => $this->product->id, 'quantity_issued' => 10, 'quantity_sold' => 5, 'quantity_returned' => 5,
            'unit_cost' => 100, 'selling_price' => 150, 'batches' => [],
        ]],
        'cash_sales_amount' => 0, 'cheque_sales_amount' => 0, 'bank_transfer_amount' => 0,
        'credit_sales_amount' => 0, 'cash_collected' => 0, 'cheques_collected' => 0, 'expenses_claimed' => 0,
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
        'attachment_files' => $files,
    ], $entries);

    $this->bankMoney = fn (): array => [
        'bank_transfers' => [['bank_account_id' => $this->bank->id, 'customer_id' => $this->customer->id, 'amount' => 300, 'reference_number' => 'TRX-77', 'attachment_key' => 'k3']],
        'bank_slips' => json_encode([['bank_account_id' => $this->bank->id, 'amount' => 400, 'reference_number' => 'SLIP-9', 'attachment_key' => 'k4']]),
        'recoveries_entries' => json_encode([
            ['customer_id' => $this->customer->id, 'payment_method' => 'bank_transfer', 'bank_account_id' => $this->bank->id, 'amount' => 500, 'attachment_key' => 'k5'],
            ['customer_id' => $this->customer->id, 'payment_method' => 'cash', 'amount' => 50],
        ]),
    ];

    $this->files = fn (): array => [
        'k3' => UploadedFile::fake()->image('transfer.png'),
        'k4' => UploadedFile::fake()->create('slip.pdf', 50, 'application/pdf'),
        'k5' => UploadedFile::fake()->image('receipt.jpg'),
    ];

    $this->storeSettlement = function (): SalesSettlement {
        $this->actingAs($this->user)
            ->post(route('sales-settlements.store'), ($this->payload)(($this->bankMoney)(), ($this->files)()))
            ->assertRedirect();

        return SalesSettlement::latest('id')->firstOrFail();
    };

    /*
     * Stand-in for DistributionService::postSalesSettlement(): a posted journal that debits the bank once per kind of
     * money, worded as the settlement posting words it, then the same sync call the posting makes.
     */
    $this->post = function (SalesSettlement $settlement): SalesSettlement {
        DB::transaction(function () use ($settlement) {
            $journal = JournalEntry::create(['currency_id' => Currency::value('id'), 'entry_date' => now()->toDateString(), 'reference' => $settlement->settlement_number, 'description' => 'settlement', 'status' => 'draft']);
            $line = 1;
            foreach ([
                'Bank Recovery from Debtor - HBL' => $settlement->recoveries()->whereNotIn('payment_method', ['cash', 'cheque'])->sum('amount'),
                'Bank Transfer Sales - HBL' => $settlement->bankTransfers()->sum('amount'),
                'Bank Deposit (Slip) - HBL' => $settlement->bankSlips()->sum('amount'),
            ] as $description => $amount) {
                if ($amount > 0) {
                    $journal->details()->create(['chart_of_account_id' => $this->bank->chart_of_account_id, 'line_no' => $line++, 'debit' => $amount, 'credit' => 0, 'description' => $description]);
                    $journal->details()->create(['chart_of_account_id' => $this->income->id, 'line_no' => $line++, 'debit' => 0, 'credit' => $amount, 'description' => $description]);
                }
            }
            $journal->update(['status' => 'posted']);
            $settlement->update(['status' => 'posted', 'posted_at' => now(), 'journal_entry_id' => $journal->id]);
            app(SettlementBankRegisterSync::class)->sync($settlement->fresh(), $this->user->id);
        });
        app(SettlementBankRegisterSync::class)->linkAttachments($settlement->fresh(), $this->user->id);

        return $settlement->fresh();
    };

    /* Stand-in for SalesSettlementRevertService: the settlement journal is reversed and the settlement is a draft again. */
    $this->revert = function (SalesSettlement $settlement): SalesSettlement {
        DB::transaction(function () use ($settlement) {
            app(AccountingService::class)->reverseJournalEntry($settlement->journal_entry_id, 'Revert');
            $settlement->update(['status' => 'draft', 'posted_at' => null, 'journal_entry_id' => null]);
            app(SettlementBankRegisterSync::class)->sync($settlement->fresh(), $this->user->id);
        });

        return $settlement->fresh();
    };

    $this->postedSettlement = fn (): SalesSettlement => ($this->post)(($this->storeSettlement)());
});

it('raises no register line while the settlement is a draft', function () {
    $settlement = ($this->storeSettlement)();

    expect($settlement->status)->toBe('draft')
        ->and(BankStatementEntry::where('sales_settlement_id', $settlement->id)->count())->toBe(0);
});

it('books one register line for each online recovery, bank transfer and bank slip when the settlement is posted', function () {
    $settlement = ($this->postedSettlement)();

    $entries = BankStatementEntry::where('sales_settlement_id', $settlement->id)->with('journalEntryDetail')->get()->keyBy('settlement_section');

    expect($entries)->toHaveCount(3)
        ->and((float) $entries['recoveries']->credit)->toBe(500.0)
        ->and((float) $entries['bank_transfers']->credit)->toBe(300.0)
        ->and((float) $entries['bank_slips']->credit)->toBe(400.0)
        ->and($entries['bank_slips']->journalEntryDetail->description)->toStartWith('Bank Deposit (Slip)')
        ->and($entries['bank_transfers']->journalEntryDetail->description)->toStartWith('Bank Transfer Sales')
        ->and($entries['recoveries']->journalEntryDetail->description)->toStartWith('Bank Recovery from Debtor')
        // the settlement's own journal is the only one: nothing is posted twice
        ->and(JournalEntry::count())->toBe(1);

    foreach ($entries as $entry) {
        expect($entry->status)->toBe(BankStatementEntry::STATUS_POSTED)
            ->and($entry->journal_entry_id)->toBe($settlement->journal_entry_id)
            ->and($entry->receipt_no)->toBeNull()
            ->and($entry->bookingLabel())->toBe('Booked by settlement')
            ->and($entry->needs_review)->toBeFalse()
            ->and($entry->source_statement)->toBe("Sales Settlement {$settlement->settlement_number}");
    }
});

it('writes a narration naming the party, bank account, reference, settlement and salesman', function () {
    $settlement = ($this->postedSettlement)();
    $entries = BankStatementEntry::where('sales_settlement_id', $settlement->id)->get()->keyBy('settlement_section');

    expect($entries['bank_transfers']->narration)
        ->toContain('Online bank transfer received from Al Madina Store')
        ->toContain('Credited to HBL - Main Collection (A/C No. 0012345)')
        ->toContain('Transaction Ref. TRX-77')
        ->toContain("Sales Settlement {$settlement->settlement_number} dated ".now()->format('d-M-Y'))
        ->toContain("Goods Issue {$this->goodsIssue->issue_number}")
        ->toContain('Salesman Imran Khan')
        ->and($entries['bank_transfers']->reference)->toBe('TRX-77')
        ->and($entries['bank_slips']->narration)->toContain('Cash deposited into bank by salesman Imran Khan')
        ->and($entries['bank_slips']->party_name_in_statement)->toBe('Imran Khan');
});

it('raises no register line for a cash recovery', function () {
    $this->actingAs($this->user)
        ->post(route('sales-settlements.store'), ($this->payload)([
            'recoveries_entries' => json_encode([['customer_id' => $this->customer->id, 'payment_method' => 'cash', 'amount' => 50]]),
        ]))
        ->assertRedirect();

    ($this->post)(SalesSettlement::latest('id')->firstOrFail());

    expect(BankStatementEntry::count())->toBe(0);
});

it('links each settlement proof to its register line and opens it from the register', function () {
    $settlement = ($this->postedSettlement)();
    $entry = BankStatementEntry::where('sales_settlement_id', $settlement->id)->where('settlement_section', 'bank_slips')->firstOrFail();

    expect(BankStatementEntry::where('sales_settlement_id', $settlement->id)->withCount('attachments')->pluck('attachments_count')->all())->toBe([1, 1, 1])
        ->and($entry->attachments->first()->file_path)->toBe($settlement->bankSlips->first()->attachment)
        ->and($entry->attachments->first()->label)->toBe('slip');

    $this->actingAs($this->user)
        ->get(route('bank-attachments.download', ['attachment' => $entry->attachments->first(), 'inline' => 1]))
        ->assertOk();
});

it('reverses the register lines with the settlement and links them to the new journal when it is posted again', function () {
    $settlement = ($this->postedSettlement)();
    $before = BankStatementEntry::where('sales_settlement_id', $settlement->id)->pluck('id')->sort()->values();

    $settlement = ($this->revert)($settlement);
    $reverted = BankStatementEntry::where('sales_settlement_id', $settlement->id)->get();

    expect($reverted)->toHaveCount(3)
        ->and($reverted->pluck('status')->unique()->all())->toBe([BankStatementEntry::STATUS_REVERSED])
        ->and($reverted->whereNotNull('journal_entry_id'))->toBeEmpty();

    $settlement = ($this->post)($settlement);
    $again = BankStatementEntry::where('sales_settlement_id', $settlement->id)->get();

    expect($again->pluck('id')->sort()->values()->all())->toBe($before->all())
        ->and($again->pluck('status')->unique()->all())->toBe([BankStatementEntry::STATUS_POSTED])
        ->and($again->pluck('journal_entry_id')->unique()->all())->toBe([$settlement->journal_entry_id]);
});

it('flags a reconciled line for review instead of reversing it when the settlement is reverted', function () {
    $settlement = ($this->postedSettlement)();
    $slip = BankStatementEntry::where('sales_settlement_id', $settlement->id)->where('settlement_section', 'bank_slips')->firstOrFail();
    $slip->forceFill(['status' => BankStatementEntry::STATUS_RECONCILED])->save();

    ($this->revert)($settlement);

    expect($slip->refresh())
        ->status->toBe(BankStatementEntry::STATUS_RECONCILED)
        ->needs_review->toBeTrue();
});

it('lets an open reconciliation go of a line that is reversed with its settlement', function () {
    $settlement = ($this->postedSettlement)();
    $slip = BankStatementEntry::where('sales_settlement_id', $settlement->id)->where('settlement_section', 'bank_slips')->firstOrFail();
    $reconciliation = BankReconciliation::create([
        'bank_account_id' => $this->bank->id, 'period_from' => now()->startOfMonth(), 'period_to' => now()->endOfMonth(),
        'statement_date' => now()->endOfMonth(), 'statement_balance' => 0, 'opening_balance' => 0, 'book_balance' => 0,
        'difference' => 0, 'status' => BankReconciliation::STATUS_IN_PROGRESS, 'created_by' => $this->user->id,
    ]);
    BankReconciliationItem::create(['bank_reconciliation_id' => $reconciliation->id, 'bank_statement_entry_id' => $slip->id, 'journal_entry_detail_id' => $slip->journal_entry_detail_id, 'match_type' => 'auto', 'matched_by' => $this->user->id, 'matched_at' => now()]);

    ($this->revert)($settlement);

    expect(BankReconciliationItem::count())->toBe(0)->and($slip->refresh()->status)->toBe(BankStatementEntry::STATUS_REVERSED);
});

it('cannot reverse or unlink a settlement line from the register', function () {
    $settlement = ($this->postedSettlement)();
    $entry = BankStatementEntry::where('sales_settlement_id', $settlement->id)->firstOrFail();

    $this->actingAs($this->user)->post(route('bank-statement-entries.reverse', $entry), ['reason' => 'test'])
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'Revert the settlement'));

    expect($entry->refresh()->status)->toBe(BankStatementEntry::STATUS_POSTED);
});

it('a bank statement confirms the settlement line instead of adding the same deposit again', function () {
    $settlement = ($this->postedSettlement)();
    $slip = BankStatementEntry::where('sales_settlement_id', $settlement->id)->where('settlement_section', 'bank_slips')->firstOrFail();

    $sheet = new Spreadsheet;
    $active = $sheet->getActiveSheet();
    $active->fromArray(['Statement ID', 'Posting date', 'Value date', 'Party name in statement', 'Client party name - unnamed entries', 'Party used for summary', 'Debit (PKR)', 'Credit (PKR)', 'Transaction / reference', 'Original statement narration', 'Source statement', 'PDF page', 'Response status'], null, 'A6');
    $active->fromArray([
        [1, now()->addDay()->toDateString(), null, 'CASH DEPOSIT', null, null, 0, 400, 'Cash Deposit 555', 'Cash Deposit 555', 'Nov statement', 3, null],
        [2, now()->toDateString(), null, 'BANK', null, null, 75, 0, 'Charges', 'Service charges', 'Nov statement', 3, null],
    ], null, 'A7');
    $path = tempnam(sys_get_temp_dir(), 'stmt').'.xlsx';
    (new Xlsx($sheet))->save($path);

    $imports = app(BankStatementImportService::class);
    $import = $imports->preview($this->bank, new UploadedFile($path, 'statement.xlsx', null, null, true), ['control_total_debit' => 75, 'control_total_credit' => 400], $this->user);
    $imports->commit($import, $this->user);

    expect(BankStatementEntry::where('bank_account_id', $this->bank->id)->where('credit', 400)->count())->toBe(1)
        ->and($slip->refresh()->import_id)->toBe($import->id)
        ->and($slip->status)->toBe(BankStatementEntry::STATUS_POSTED)
        ->and(BankStatementEntry::where('import_id', $import->id)->count())->toBe(2)
        ->and($import->fresh()->imported_rows)->toBe(2);
});

it('shows the source settlement on the register entry', function () {
    $settlement = ($this->postedSettlement)();
    $entry = BankStatementEntry::where('sales_settlement_id', $settlement->id)->firstOrFail();

    $this->actingAs($this->user)->get(route('bank-statement-entries.show', $entry))
        ->assertOk()
        ->assertSee("Sales Settlement {$settlement->settlement_number}")
        ->assertSee('Booked by settlement')
        ->assertSee(route('sales-settlements.show', $settlement), false);
});
