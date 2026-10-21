<?php

use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\BankEntryCategory;
use App\Models\BankStatementEntry;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Services\BankRegister\BankEntryApprovalService;
use App\Services\BankRegister\BankRegisterLedger;
use App\Services\BankRegister\BankReportService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->fixture = bankRegisterFixture();
    config()->set('bank.maker_checker.enabled', false);
    $this->ledger = app(BankRegisterLedger::class);
    $this->maker = bankUser(['bank-statement-entry-list', 'bank-statement-entry-submit', 'bank-statement-entry-post', 'bank-ledger-account-list'], 'Maker');
});

function postRegisterEntry(array $fixture, $maker, array $overrides = []): BankStatementEntry
{
    $entry = BankStatementEntry::factory()->create($overrides + [
        'bank_account_id' => $fixture['bank']->id, 'company_id' => $fixture['company']->id, 'bank_entry_category_id' => $fixture['category']->id,
        'posting_date' => now()->toDateString(), 'credit' => 5000, 'debit' => 0, 'status' => 'classified',
    ]);

    return app(BankEntryApprovalService::class)->submit($entry, $maker);
}

test('the register accounts live under their own root with their own account type and report group', function () {
    $this->ledger->ensureStructure();

    $type = AccountType::where('type_name', 'Bank Register')->firstOrFail();
    $root = ChartOfAccount::where('account_name', 'Bank Register (memo accounts)')->firstOrFail();

    expect($type->report_group)->toBe('BankRegister')
        ->and($root->account_code)->toBe('6000')->and($root->is_group)->toBeTrue()
        ->and(ChartOfAccount::where('account_type_id', $type->id)->count())->toBe(1 + count(BankRegisterLedger::GROUPS) + count(BankRegisterLedger::ACCOUNTS) + 1); // + the fixture's bank ledger
    foreach (ChartOfAccount::where('account_type_id', $type->id)->pluck('account_code') as $code) {
        expect($code)->toMatch('/^\d{4}$/');
    }
});

test('creating the accounts twice makes no duplicates', function () {
    $this->ledger->ensureStructure();
    $count = ChartOfAccount::count();

    $this->ledger->ensureStructure();
    $this->ledger->ledgerForBank($this->fixture['bank']);
    $this->ledger->ledgerForBank($this->fixture['bank']);

    expect(ChartOfAccount::count())->toBe($count + 0)->and(ChartOfAccount::where('account_name', 'like', 'BR - %')->count())->toBeGreaterThan(0);
});

test('codes already used by live accounts are never taken or changed', function () {
    // start from a database where the register accounts do not exist yet
    BankAccount::query()->update(['register_chart_of_account_id' => null]);
    ChartOfAccount::query()->where('account_name', 'like', 'BR - %')->orWhere('account_name', 'Bank Register (memo accounts)')
        ->orderByDesc('account_code')->get()->each->delete();

    $type = AccountType::firstWhere('type_name', 'Assets');
    foreach (['6000', '6100', '6101', '6200', '6201', '6202'] as $code) {
        ChartOfAccount::create(['account_type_id' => $type->id, 'currency_id' => $this->fixture['liveLedger']->currency_id, 'account_code' => $code, 'account_name' => "Live account {$code}", 'normal_balance' => 'debit', 'is_active' => true]);
    }
    BankAccount::query()->update(['register_chart_of_account_id' => null]);

    $this->ledger->ensureStructure();
    $mine = ChartOfAccount::where('account_name', 'like', 'BR - %')->orWhere('account_name', 'Bank Register (memo accounts)')->pluck('account_code');

    expect($mine->duplicates())->toBeEmpty()
        ->and($mine)->not->toContain('6101')->and($mine)->not->toContain('6201')->and($mine)->not->toContain('6000')
        ->and(ChartOfAccount::where('account_name', 'Live account 6201')->exists())->toBeTrue()
        ->and(ChartOfAccount::pluck('account_code')->duplicates())->toBeEmpty();
});

test('every bank account gets its own register ledger, separate from its live ledger', function () {
    $second = BankAccount::factory()->create(['account_name' => 'Meezan Wallet', 'account_number' => 'MZN-1', 'chart_of_account_id' => $this->fixture['liveLedger']->id]);

    $first = $this->ledger->ledgerForBank($this->fixture['bank']);
    $other = $this->ledger->ledgerForBank($second);

    expect($first->id)->not->toBe($other->id)->and($first->id)->not->toBe($this->fixture['liveLedger']->id)
        ->and($other->account_name)->toBe('BR - Meezan Wallet (MZN-1)')
        ->and($second->fresh()->register_chart_of_account_id)->toBe($other->id)
        ->and($second->fresh()->chart_of_account_id)->toBe($this->fixture['liveLedger']->id);
});

test('each company has its own register account and the companies keep their own balances', function () {
    $dalda = Company::factory()->create(['company_name' => 'Dalda Foods']);

    postRegisterEntry($this->fixture, $this->maker, ['credit' => 1000]);
    postRegisterEntry($this->fixture, $this->maker, ['credit' => 0, 'debit' => 400, 'company_id' => $dalda->id]);

    $unilever = $this->ledger->companyAccount($this->fixture['company']);
    $daldaAccount = $this->ledger->companyAccount($dalda);

    expect($unilever->account_name)->toBe('BR - Unilever')->and($daldaAccount->account_name)->toBe('BR - Dalda Foods')
        ->and($unilever->id)->not->toBe($daldaAccount->id)
        ->and(JournalEntryDetail::where('chart_of_account_id', $unilever->id)->sum('credit'))->toEqual(1000)
        ->and(JournalEntryDetail::where('chart_of_account_id', $daldaAccount->id)->sum('debit'))->toEqual(400);
});

test('the register accounts are kept out of the Balance Sheet and the Income Statement', function () {
    postRegisterEntry($this->fixture, $this->maker);
    $type = AccountType::where('type_name', 'Bank Register')->firstOrFail();

    expect($type->report_group)->not->toBeIn(['BalanceSheet', 'IncomeStatement']);

    foreach (['report-financial-balance-sheet', 'report-financial-income-statement'] as $permission) {
        Permission::findOrCreate($permission);
    }
    $user = bankUser(['report-financial-balance-sheet', 'report-financial-income-statement']);
    $this->actingAs($user);

    $this->get(route('reports.balance-sheet.index'))->assertOk()->assertDontSee('BR - Unilever')->assertDontSee('BR - HBL');
    $this->get(route('reports.income-statement.index'))->assertOk()->assertDontSee('BR - Bank Charges');
});

test('the register ledger page lists the accounts with their balances and the live-vs-register mapping', function () {
    postRegisterEntry($this->fixture, $this->maker, ['credit' => 12000]);
    $this->actingAs($this->maker);

    $this->get(route('bank-ledger-accounts.index'))->assertOk()
        ->assertSee('The Bank Register keeps its own books')
        ->assertSee('Bank Register (memo accounts)')->assertSee('BR - Unilever')->assertSee('12,000.00')
        ->assertSee($this->fixture['liveLedger']->account_code.' — '.$this->fixture['liveLedger']->account_name, false);

    $this->actingAs(bankUser(['bank-statement-entry-list']))->get(route('bank-ledger-accounts.index'))->assertForbidden();
});

test('the live-gap report lists statement lines the live ledger has no entry for', function () {
    $matched = BankStatementEntry::factory()->create([
        'bank_account_id' => $this->fixture['bank']->id, 'posting_date' => now()->toDateString(), 'credit' => 7000, 'debit' => 0, 'party_name_in_statement' => 'IN LIVE',
    ]);
    BankStatementEntry::factory()->create([
        'bank_account_id' => $this->fixture['bank']->id, 'posting_date' => now()->toDateString(), 'credit' => 0, 'debit' => 3300, 'party_name_in_statement' => 'ONLY IN BANK',
    ]);

    // a live journal (as a settlement would book it) for the first line, on the LIVE ledger
    $journal = JournalEntry::create(['currency_id' => Currency::value('id'), 'entry_date' => now()->subDay()->toDateString(), 'reference' => 'SETTLEMENT-1', 'description' => 'live', 'status' => 'draft']);
    $journal->details()->create(['chart_of_account_id' => $this->fixture['liveLedger']->id, 'line_no' => 1, 'debit' => 7000, 'credit' => 0]);
    $journal->details()->create(['chart_of_account_id' => $this->fixture['liveReceivable']->id, 'line_no' => 2, 'debit' => 0, 'credit' => 7000]);
    $journal->update(['status' => 'posted']);

    $report = app(BankReportService::class)->build('live-gap', []);

    expect($report['rows'])->toHaveCount(1)->and($report['rows'][0][2])->toBe('ONLY IN BANK')->and($report['rows'][0][6])->toBe(3300.0)
        ->and($report['notes'][0])->toContain('1 statement line(s) were found');
});

test('a category with its own account keeps using it and unknown categories without a company go to suspense', function () {
    $own = ChartOfAccount::create(['account_type_id' => $this->fixture['bankLedger']->account_type_id, 'currency_id' => $this->fixture['bankLedger']->currency_id, 'account_code' => '6290', 'account_name' => 'BR - Rent', 'normal_balance' => 'debit', 'is_active' => true]);
    $rent = BankEntryCategory::factory()->create(['name' => 'Rent', 'slug' => 'rent', 'type' => 'payment', 'default_chart_of_account_id' => $own->id]);

    $posted = postRegisterEntry($this->fixture, $this->maker, ['bank_entry_category_id' => $rent->id, 'company_id' => null, 'credit' => 0, 'debit' => 900]);
    expect($posted->journalEntry->details->pluck('chart_of_account_id'))->toContain($own->id);

    $unknown = BankEntryCategory::factory()->create(['name' => 'Misc', 'slug' => 'misc', 'type' => 'receipt']);
    $suspense = postRegisterEntry($this->fixture, $this->maker, ['bank_entry_category_id' => $unknown->id, 'company_id' => null, 'credit' => 450, 'debit' => 0]);
    expect($suspense->journalEntry->details->pluck('chart_of_account_id'))->toContain($this->ledger->account('unidentified_receipts')->id);
});
