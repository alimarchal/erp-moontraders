<?php

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $permissions = [
        'report-financial-general-ledger',
        'report-financial-trial-balance',
        'report-financial-account-balances',
        'report-financial-balance-sheet',
        'report-financial-income-statement',
        'journal-entry-list',
        'journal-entry-create',
        'journal-entry-edit',
        'journal-entry-delete',
        'journal-entry-post',
        'journal-entry-reverse',
    ];

    foreach ($permissions as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo($permissions);
    $this->actingAs($this->user);

    $this->currency = Currency::factory()->base()->create();

    $this->period = AccountingPeriod::create([
        'name' => 'Test Period',
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->endOfYear()->toDateString(),
        'status' => AccountingPeriod::STATUS_OPEN,
    ]);

    $this->assetType = AccountType::create([
        'type_name' => 'Asset',
        'report_group' => 'BalanceSheet',
        'description' => 'Asset accounts',
    ]);

    $this->liabilityType = AccountType::create([
        'type_name' => 'Liability',
        'report_group' => 'BalanceSheet',
        'description' => 'Liability accounts',
    ]);

    $this->equityType = AccountType::create([
        'type_name' => 'Equity',
        'report_group' => 'BalanceSheet',
        'description' => 'Equity accounts',
    ]);

    $this->incomeType = AccountType::create([
        'type_name' => 'Income',
        'report_group' => 'IncomeStatement',
        'description' => 'Income accounts',
    ]);

    $this->expenseType = AccountType::create([
        'type_name' => 'Expense',
        'report_group' => 'IncomeStatement',
        'description' => 'Expense accounts',
    ]);

    $this->cash = ChartOfAccount::create([
        'account_code' => '1001',
        'account_name' => 'Cash',
        'account_type_id' => $this->assetType->id,
        'currency_id' => $this->currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $this->bank = ChartOfAccount::create([
        'account_code' => '1002',
        'account_name' => 'Bank Account',
        'account_type_id' => $this->assetType->id,
        'currency_id' => $this->currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $this->receivables = ChartOfAccount::create([
        'account_code' => '1003',
        'account_name' => 'Accounts Receivable',
        'account_type_id' => $this->assetType->id,
        'currency_id' => $this->currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $this->creditors = ChartOfAccount::create([
        'account_code' => '2001',
        'account_name' => 'Creditors',
        'account_type_id' => $this->liabilityType->id,
        'currency_id' => $this->currency->id,
        'normal_balance' => 'credit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $this->capital = ChartOfAccount::create([
        'account_code' => '3001',
        'account_name' => 'Owner Capital',
        'account_type_id' => $this->equityType->id,
        'currency_id' => $this->currency->id,
        'normal_balance' => 'credit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $this->salesRevenue = ChartOfAccount::create([
        'account_code' => '4001',
        'account_name' => 'Sales Revenue',
        'account_type_id' => $this->incomeType->id,
        'currency_id' => $this->currency->id,
        'normal_balance' => 'credit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $this->rentExpense = ChartOfAccount::create([
        'account_code' => '5001',
        'account_name' => 'Rent Expense',
        'account_type_id' => $this->expenseType->id,
        'currency_id' => $this->currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $this->service = app(AccountingService::class);
});

/*
|--------------------------------------------------------------------------
| 1. AccountingService – Journal Entry CRUD
|--------------------------------------------------------------------------
*/

it('creates a draft journal entry via service', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Owner investment',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 10000, 'credit' => 0, 'description' => 'Cash in', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 10000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['data']->status)->toBe('draft');
    expect($result['data']->details)->toHaveCount(2);
});

it('creates and auto-posts a journal entry', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Auto-post entry',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 5000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 5000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['data']->status)->toBe('posted');
    expect($result['data']->posted_at)->not->toBeNull();
});

it('rejects unbalanced journal entries', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Unbalanced entry',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 5000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 4000, 'description' => 'Less capital', 'cost_center_id' => null],
        ],
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('not balanced');
});

it('rejects entries with fewer than two lines', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Single line',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 5000, 'credit' => 0, 'description' => 'Only one', 'cost_center_id' => null],
        ],
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('two lines');
});

it('rejects lines with both debit and credit', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Both amounts',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 5000, 'credit' => 5000, 'description' => 'Both', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 5000, 'credit' => 5000, 'description' => 'Both', 'cost_center_id' => null],
        ],
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('both debit and credit');
});

it('rejects lines with zero debit and credit', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Zero amounts',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 0, 'credit' => 0, 'description' => 'Zero', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 0, 'description' => 'Zero', 'cost_center_id' => null],
        ],
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('debit or credit');
});

/*
|--------------------------------------------------------------------------
| 2. AccountingService – Post / Update / Reverse
|--------------------------------------------------------------------------
*/

it('posts a draft journal entry', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Draft to post',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 2000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 2000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
    ]);

    $postResult = $this->service->postJournalEntry($result['data']->id);

    expect($postResult['success'])->toBeTrue();
    expect($postResult['data']->status)->toBe('posted');
});

it('prevents posting an already posted entry', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Already posted',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 1000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $postResult = $this->service->postJournalEntry($result['data']->id);

    expect($postResult['success'])->toBeFalse();
    expect($postResult['message'])->toContain('already posted');
});

it('prevents updating a posted entry', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Posted entry',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 1000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $updateResult = $this->service->updateJournalEntry($result['data'], [
        'entry_date' => now()->toDateString(),
        'description' => 'Modified',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 2000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 2000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
    ]);

    expect($updateResult['success'])->toBeFalse();
    expect($updateResult['message'])->toContain('cannot be modified');
});

it('updates a draft journal entry', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Draft entry',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 1000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
    ]);

    $updateResult = $this->service->updateJournalEntry($result['data'], [
        'entry_date' => now()->toDateString(),
        'description' => 'Updated draft',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 3000, 'credit' => 0, 'description' => 'More cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 3000, 'description' => 'More capital', 'cost_center_id' => null],
        ],
    ]);

    expect($updateResult['success'])->toBeTrue();
    expect($updateResult['data']->description)->toBe('Updated draft');
    expect((float) $updateResult['data']->details->first()->debit)->toBe(3000.0);
});

it('reverses a posted journal entry', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Entry to reverse',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 5000, 'credit' => 0, 'description' => 'Cash in', 'cost_center_id' => null],
            ['account_id' => $this->creditors->id, 'debit' => 0, 'credit' => 5000, 'description' => 'Creditor', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $reverseResult = $this->service->reverseJournalEntry($result['data']->id, 'Reversing test');

    expect($reverseResult['success'])->toBeTrue();
    expect($reverseResult['data']->status)->toBe('posted');
    expect($reverseResult['data']->description)->toContain('Reversing test');

    $reversedDetails = $reverseResult['data']->details;
    $originalDetails = $result['data']->details;

    expect((float) $reversedDetails[0]->debit)->toBe((float) $originalDetails[0]->credit);
    expect((float) $reversedDetails[0]->credit)->toBe((float) $originalDetails[0]->debit);
});

it('only allows reversing posted entries', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Draft cannot reverse',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 1000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
    ]);

    $reverseResult = $this->service->reverseJournalEntry($result['data']->id);

    expect($reverseResult['success'])->toBeFalse();
    expect($reverseResult['message'])->toContain('Only posted');
});

/*
|--------------------------------------------------------------------------
| 3. Fundamental Accounting Equation: Assets = Liabilities + Equity + Net Income
|--------------------------------------------------------------------------
*/

it('enforces total debits equal total credits for all posted entries', function () {
    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Capital investment',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 50000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 50000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Sold goods for cash',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 15000, 'credit' => 0, 'description' => 'Cash from sale', 'cost_center_id' => null],
            ['account_id' => $this->salesRevenue->id, 'debit' => 0, 'credit' => 15000, 'description' => 'Revenue', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Paid rent',
        'lines' => [
            ['account_id' => $this->rentExpense->id, 'debit' => 3000, 'credit' => 0, 'description' => 'Rent', 'cost_center_id' => null],
            ['account_id' => $this->cash->id, 'debit' => 0, 'credit' => 3000, 'description' => 'Cash out', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $totals = DB::table('journal_entry_details as jed')
        ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
        ->where('je.status', 'posted')
        ->selectRaw('COALESCE(SUM(jed.debit), 0) as total_debits, COALESCE(SUM(jed.credit), 0) as total_credits')
        ->first();

    expect((float) $totals->total_debits)->toBe((float) $totals->total_credits);
    expect((float) $totals->total_debits)->toBe(68000.0);
});

it('verifies accounting equation after multiple transactions', function () {
    $entries = [
        ['desc' => 'Capital', 'dr' => $this->cash, 'cr' => $this->capital, 'amount' => 100000],
        ['desc' => 'Sale', 'dr' => $this->cash, 'cr' => $this->salesRevenue, 'amount' => 25000],
        ['desc' => 'Rent', 'dr' => $this->rentExpense, 'cr' => $this->cash, 'amount' => 8000],
        ['desc' => 'Credit purchase', 'dr' => $this->cash, 'cr' => $this->creditors, 'amount' => 15000],
        ['desc' => 'Bank deposit', 'dr' => $this->bank, 'cr' => $this->cash, 'amount' => 40000],
    ];

    foreach ($entries as $e) {
        $result = $this->service->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'description' => $e['desc'],
            'lines' => [
                ['account_id' => $e['dr']->id, 'debit' => $e['amount'], 'credit' => 0, 'description' => $e['desc'], 'cost_center_id' => null],
                ['account_id' => $e['cr']->id, 'debit' => 0, 'credit' => $e['amount'], 'description' => $e['desc'], 'cost_center_id' => null],
            ],
            'auto_post' => true,
        ]);
        expect($result['success'])->toBeTrue();
    }

    $assetBalance = DB::table('journal_entry_details as jed')
        ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
        ->join('chart_of_accounts as coa', 'coa.id', '=', 'jed.chart_of_account_id')
        ->join('account_types as at', 'at.id', '=', 'coa.account_type_id')
        ->where('je.status', 'posted')
        ->where('at.report_group', 'BalanceSheet')
        ->where('at.type_name', 'Asset')
        ->selectRaw('COALESCE(SUM(jed.debit - jed.credit), 0) as balance')
        ->value('balance');

    $liabilityBalance = DB::table('journal_entry_details as jed')
        ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
        ->join('chart_of_accounts as coa', 'coa.id', '=', 'jed.chart_of_account_id')
        ->join('account_types as at', 'at.id', '=', 'coa.account_type_id')
        ->where('je.status', 'posted')
        ->where('at.report_group', 'BalanceSheet')
        ->where('at.type_name', 'Liability')
        ->selectRaw('COALESCE(SUM(jed.credit - jed.debit), 0) as balance')
        ->value('balance');

    $equityBalance = DB::table('journal_entry_details as jed')
        ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
        ->join('chart_of_accounts as coa', 'coa.id', '=', 'jed.chart_of_account_id')
        ->join('account_types as at', 'at.id', '=', 'coa.account_type_id')
        ->where('je.status', 'posted')
        ->where('at.report_group', 'BalanceSheet')
        ->where('at.type_name', 'Equity')
        ->selectRaw('COALESCE(SUM(jed.credit - jed.debit), 0) as balance')
        ->value('balance');

    $incomeBalance = DB::table('journal_entry_details as jed')
        ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
        ->join('chart_of_accounts as coa', 'coa.id', '=', 'jed.chart_of_account_id')
        ->join('account_types as at', 'at.id', '=', 'coa.account_type_id')
        ->where('je.status', 'posted')
        ->where('at.type_name', 'Income')
        ->selectRaw('COALESCE(SUM(jed.credit - jed.debit), 0) as balance')
        ->value('balance');

    $expenseBalance = DB::table('journal_entry_details as jed')
        ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
        ->join('chart_of_accounts as coa', 'coa.id', '=', 'jed.chart_of_account_id')
        ->join('account_types as at', 'at.id', '=', 'coa.account_type_id')
        ->where('je.status', 'posted')
        ->where('at.type_name', 'Expense')
        ->selectRaw('COALESCE(SUM(jed.debit - jed.credit), 0) as balance')
        ->value('balance');

    $netIncome = (float) $incomeBalance - (float) $expenseBalance;

    expect((float) $assetBalance)
        ->toBe((float) $liabilityBalance + (float) $equityBalance + $netIncome);
});

/*
|--------------------------------------------------------------------------
| 4. Balance Sheet Report
|--------------------------------------------------------------------------
*/

it('balance sheet report renders and balances', function () {
    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Capital investment',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 50000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 50000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $response = $this->get(route('reports.balance-sheet.index', [
        'as_of_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertSee('Balance Sheet is balanced');
    $response->assertSee('Cash');
    $response->assertSee('Owner Capital');
});

it('balance sheet shows correct amounts for multi-type transactions', function () {
    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Capital',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 100000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 100000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Credit purchase',
        'lines' => [
            ['account_id' => $this->bank->id, 'debit' => 30000, 'credit' => 0, 'description' => 'Bank', 'cost_center_id' => null],
            ['account_id' => $this->creditors->id, 'debit' => 0, 'credit' => 30000, 'description' => 'Creditors', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $response = $this->get(route('reports.balance-sheet.index', [
        'as_of_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertSee('Balance Sheet is balanced');
});

it('balance sheet includes inactive accounts with posted transactions', function () {
    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Deposit to bank',
        'lines' => [
            ['account_id' => $this->bank->id, 'debit' => 5000, 'credit' => 0, 'description' => 'Bank deposit', 'cost_center_id' => null],
            ['account_id' => $this->creditors->id, 'debit' => 0, 'credit' => 5000, 'description' => 'Creditor', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $this->bank->update(['is_active' => false]);

    $response = $this->get(route('reports.balance-sheet.index', [
        'as_of_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertDontSee('Balance Sheet does not balance');
    $response->assertSee('Balance Sheet is balanced');
    $response->assertSee('Bank Account');
});

it('balance sheet excludes inactive accounts without transactions', function () {
    $unusedAccount = ChartOfAccount::create([
        'account_code' => '1099',
        'account_name' => 'Unused Asset',
        'account_type_id' => $this->assetType->id,
        'currency_id' => $this->currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => false,
    ]);

    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Normal entry',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 1000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $response = $this->get(route('reports.balance-sheet.index', [
        'as_of_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertDontSee('Unused Asset');
});

it('balance sheet filters by date correctly', function () {
    $this->service->createJournalEntry([
        'entry_date' => '2026-01-15',
        'description' => 'January entry',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 10000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 10000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $this->service->createJournalEntry([
        'entry_date' => '2026-06-15',
        'description' => 'June entry',
        'lines' => [
            ['account_id' => $this->bank->id, 'debit' => 5000, 'credit' => 0, 'description' => 'Bank', 'cost_center_id' => null],
            ['account_id' => $this->creditors->id, 'debit' => 0, 'credit' => 5000, 'description' => 'Creditors', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $responseJan = $this->get(route('reports.balance-sheet.index', ['as_of_date' => '2026-01-31']));
    $responseJan->assertSuccessful();
    $responseJan->assertSee('Cash');
    $responseJan->assertDontSee('Bank Account');

    $responseJun = $this->get(route('reports.balance-sheet.index', ['as_of_date' => '2026-06-30']));
    $responseJun->assertSuccessful();
    $responseJun->assertSee('Cash');
    $responseJun->assertSee('Bank Account');
});

/*
|--------------------------------------------------------------------------
| 5. Income Statement Report
|--------------------------------------------------------------------------
*/

it('income statement report renders with revenue and expenses', function () {
    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Cash sale',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 20000, 'credit' => 0, 'description' => 'Cash from sale', 'cost_center_id' => null],
            ['account_id' => $this->salesRevenue->id, 'debit' => 0, 'credit' => 20000, 'description' => 'Revenue', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Rent paid',
        'lines' => [
            ['account_id' => $this->rentExpense->id, 'debit' => 5000, 'credit' => 0, 'description' => 'Rent', 'cost_center_id' => null],
            ['account_id' => $this->cash->id, 'debit' => 0, 'credit' => 5000, 'description' => 'Cash out', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $response = $this->get(route('reports.income-statement.index', [
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertSee('Sales Revenue');
    $response->assertSee('Rent Expense');
});

it('income statement includes inactive income/expense accounts with posted transactions', function () {
    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Sale',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 10000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->salesRevenue->id, 'debit' => 0, 'credit' => 10000, 'description' => 'Revenue', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $this->salesRevenue->update(['is_active' => false]);

    $response = $this->get(route('reports.income-statement.index', [
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertSee('Sales Revenue');
});

it('income statement filters by date range correctly', function () {
    $this->service->createJournalEntry([
        'entry_date' => '2026-01-15',
        'description' => 'January sale',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 10000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->salesRevenue->id, 'debit' => 0, 'credit' => 10000, 'description' => 'Jan Revenue', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $this->service->createJournalEntry([
        'entry_date' => '2026-03-15',
        'description' => 'March rent',
        'lines' => [
            ['account_id' => $this->rentExpense->id, 'debit' => 2000, 'credit' => 0, 'description' => 'Rent', 'cost_center_id' => null],
            ['account_id' => $this->cash->id, 'debit' => 0, 'credit' => 2000, 'description' => 'Cash out', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $responseJan = $this->get(route('reports.income-statement.index', [
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
    ]));
    $responseJan->assertSuccessful();
    $responseJan->assertSee('Sales Revenue');
    $responseJan->assertDontSee('Rent Expense');

    $responseQ1 = $this->get(route('reports.income-statement.index', [
        'start_date' => '2026-01-01',
        'end_date' => '2026-03-31',
    ]));
    $responseQ1->assertSuccessful();
    $responseQ1->assertSee('Sales Revenue');
    $responseQ1->assertSee('Rent Expense');
});

it('income statement net income flows into balance sheet', function () {
    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Capital',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 50000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 50000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Revenue',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 20000, 'credit' => 0, 'description' => 'Cash from sale', 'cost_center_id' => null],
            ['account_id' => $this->salesRevenue->id, 'debit' => 0, 'credit' => 20000, 'description' => 'Revenue', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Expense',
        'lines' => [
            ['account_id' => $this->rentExpense->id, 'debit' => 7000, 'credit' => 0, 'description' => 'Rent', 'cost_center_id' => null],
            ['account_id' => $this->cash->id, 'debit' => 0, 'credit' => 7000, 'description' => 'Cash out', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $bsResponse = $this->get(route('reports.balance-sheet.index', [
        'as_of_date' => now()->toDateString(),
    ]));
    $bsResponse->assertSuccessful();
    $bsResponse->assertSee('Balance Sheet is balanced');
});

/*
|--------------------------------------------------------------------------
| 6. Trial Balance Report
|--------------------------------------------------------------------------
*/

it('trial balance shows zero difference for balanced entries', function () {
    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Capital',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 25000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 25000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Sale',
        'lines' => [
            ['account_id' => $this->receivables->id, 'debit' => 8000, 'credit' => 0, 'description' => 'Receivable', 'cost_center_id' => null],
            ['account_id' => $this->salesRevenue->id, 'debit' => 0, 'credit' => 8000, 'description' => 'Revenue', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $response = $this->get(route('reports.trial-balance.index', [
        'as_of_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertSee('0.00');
});

it('trial balance renders with correct totals', function () {
    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Single entry',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 10000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 10000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $response = $this->get(route('reports.trial-balance.index', [
        'as_of_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertSee('10,000.00');
});

it('trial balance ignores draft entries', function () {
    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Draft only',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 99999, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 99999, 'description' => 'Capital', 'cost_center_id' => null],
        ],
    ]);

    $response = $this->get(route('reports.trial-balance.index', [
        'as_of_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertDontSee('99,999.00');
});

it('trial balance filters by date', function () {
    $this->service->createJournalEntry([
        'entry_date' => '2026-01-10',
        'description' => 'January',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 5000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 5000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $this->service->createJournalEntry([
        'entry_date' => '2026-06-10',
        'description' => 'June',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 3000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->creditors->id, 'debit' => 0, 'credit' => 3000, 'description' => 'Creditor', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $responseJan = $this->get(route('reports.trial-balance.index', ['as_of_date' => '2026-01-31']));
    $responseJan->assertSuccessful();
    $responseJan->assertSee('5,000.00');

    $responseJun = $this->get(route('reports.trial-balance.index', ['as_of_date' => '2026-06-30']));
    $responseJun->assertSuccessful();
    $responseJun->assertSee('8,000.00');
});

/*
|--------------------------------------------------------------------------
| 7. Account Balances Report
|--------------------------------------------------------------------------
*/

it('account balances report renders all accounts', function () {
    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Capital',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 20000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 20000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $response = $this->get(route('reports.account-balances.index', [
        'as_of_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertSee('Cash');
    $response->assertSee('Owner Capital');
});

it('account balances shows correct debit and credit totals', function () {
    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Entry A',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 10000, 'credit' => 0, 'description' => 'Cash in', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 10000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Entry B',
        'lines' => [
            ['account_id' => $this->rentExpense->id, 'debit' => 3000, 'credit' => 0, 'description' => 'Rent', 'cost_center_id' => null],
            ['account_id' => $this->cash->id, 'debit' => 0, 'credit' => 3000, 'description' => 'Cash out', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $response = $this->get(route('reports.account-balances.index', [
        'as_of_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertSee('Cash');
    $response->assertSee('Rent Expense');
});

/*
|--------------------------------------------------------------------------
| 8. General Ledger Report
|--------------------------------------------------------------------------
*/

it('general ledger report renders posted entries', function () {
    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'GL test entry',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 15000, 'credit' => 0, 'description' => 'Cash in', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 15000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $response = $this->get(route('reports.general-ledger.index'));

    $response->assertSuccessful();
    $response->assertSee('Cash');
    $response->assertSee('Owner Capital');
});

it('general ledger respects date filter', function () {
    $this->service->createJournalEntry([
        'entry_date' => '2026-01-10',
        'description' => 'Jan GL',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 5000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 5000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $this->service->createJournalEntry([
        'entry_date' => '2026-06-10',
        'description' => 'Jun GL',
        'lines' => [
            ['account_id' => $this->bank->id, 'debit' => 3000, 'credit' => 0, 'description' => 'Bank', 'cost_center_id' => null],
            ['account_id' => $this->creditors->id, 'debit' => 0, 'credit' => 3000, 'description' => 'Creditor', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $responseJan = $this->get(route('reports.general-ledger.index', [
        'filter' => ['entry_date_from' => '2026-01-01', 'entry_date_to' => '2026-01-31'],
    ]));
    $responseJan->assertSuccessful();
    $responseJan->assertSee('Cash');

    $responseJun = $this->get(route('reports.general-ledger.index', [
        'filter' => ['entry_date_from' => '2026-06-01', 'entry_date_to' => '2026-06-30'],
    ]));
    $responseJun->assertSuccessful();
    $responseJun->assertSee('Bank Account');
});

it('general ledger filters by status', function () {
    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Draft GL entry',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 1000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
    ]);

    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Posted GL entry',
        'lines' => [
            ['account_id' => $this->bank->id, 'debit' => 2000, 'credit' => 0, 'description' => 'Bank', 'cost_center_id' => null],
            ['account_id' => $this->creditors->id, 'debit' => 0, 'credit' => 2000, 'description' => 'Creditor', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $responsePosted = $this->get(route('reports.general-ledger.index', [
        'filter' => ['status' => 'posted'],
    ]));
    $responsePosted->assertSuccessful();
    $responsePosted->assertSee('Bank Account');

    $responseDraft = $this->get(route('reports.general-ledger.index', [
        'filter' => ['status' => 'draft'],
    ]));
    $responseDraft->assertSuccessful();
    $responseDraft->assertSee('Cash');
});

/*
|--------------------------------------------------------------------------
| 9. Journal Entry Controller – HTTP Routes
|--------------------------------------------------------------------------
*/

it('journal entry index page renders', function () {
    $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Index test',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 1000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $response = $this->get(route('journal-entries.index'));
    $response->assertSuccessful();
    $response->assertSee('Index test');
});

it('journal entry show page renders', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Show test',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 5000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 5000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $response = $this->get(route('journal-entries.show', $result['data']->id));
    $response->assertSuccessful();
    $response->assertSee('Show test');
});

it('journal entry create page renders', function () {
    $response = $this->get(route('journal-entries.create'));
    $response->assertSuccessful();
});

it('journal entry can be posted via HTTP', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'HTTP post test',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 1000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
    ]);

    $response = $this->post(route('journal-entries.post', $result['data']->id));
    $response->assertRedirect();

    expect($result['data']->fresh()->status)->toBe('posted');
});

it('journal entry can be reversed via HTTP', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'HTTP reverse test',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 2000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 2000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $response = $this->post(route('journal-entries.reverse', $result['data']->id), [
        'description' => 'Reverse via HTTP',
    ]);
    $response->assertRedirect();

    expect(JournalEntry::count())->toBe(2);
});

it('draft journal entry can be deleted', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Delete test',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 1000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
    ]);

    $response = $this->delete(route('journal-entries.destroy', $result['data']->id));
    $response->assertRedirect();

    expect(JournalEntry::find($result['data']->id))->toBeNull();
});

it('posted journal entry cannot be deleted via HTTP', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Cannot delete posted',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 1000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $response = $this->delete(route('journal-entries.destroy', $result['data']->id));
    $response->assertRedirect();

    expect(JournalEntry::find($result['data']->id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| 10. Reversal Balancing – Net Zero After Reversal
|--------------------------------------------------------------------------
*/

it('reversed entry results in net zero balances', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Original entry',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 10000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->creditors->id, 'debit' => 0, 'credit' => 10000, 'description' => 'Creditor', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $this->service->reverseJournalEntry($result['data']->id, 'Full reversal');

    $cashBalance = DB::table('journal_entry_details as jed')
        ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
        ->where('je.status', 'posted')
        ->where('jed.chart_of_account_id', $this->cash->id)
        ->selectRaw('COALESCE(SUM(jed.debit - jed.credit), 0) as balance')
        ->value('balance');

    $creditorBalance = DB::table('journal_entry_details as jed')
        ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
        ->where('je.status', 'posted')
        ->where('jed.chart_of_account_id', $this->creditors->id)
        ->selectRaw('COALESCE(SUM(jed.credit - jed.debit), 0) as balance')
        ->value('balance');

    expect((float) $cashBalance)->toBe(0.0);
    expect((float) $creditorBalance)->toBe(0.0);
});

/*
|--------------------------------------------------------------------------
| 11. Multi-line Journal Entries
|--------------------------------------------------------------------------
*/

it('handles multi-line journal entries correctly', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Split payment',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 5000, 'credit' => 0, 'description' => 'Cash part', 'cost_center_id' => null],
            ['account_id' => $this->bank->id, 'debit' => 3000, 'credit' => 0, 'description' => 'Bank part', 'cost_center_id' => null],
            ['account_id' => $this->receivables->id, 'debit' => 2000, 'credit' => 0, 'description' => 'On credit', 'cost_center_id' => null],
            ['account_id' => $this->salesRevenue->id, 'debit' => 0, 'credit' => 10000, 'description' => 'Total revenue', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['data']->details)->toHaveCount(4);

    $response = $this->get(route('reports.balance-sheet.index', [
        'as_of_date' => now()->toDateString(),
    ]));
    $response->assertSuccessful();
    $response->assertSee('Balance Sheet is balanced');
});

/*
|--------------------------------------------------------------------------
| 12. Authorization Guards
|--------------------------------------------------------------------------
*/

it('unauthenticated user cannot access reports', function () {
    auth()->logout();

    $this->get(route('reports.balance-sheet.index'))->assertRedirect();
    $this->get(route('reports.income-statement.index'))->assertRedirect();
    $this->get(route('reports.trial-balance.index'))->assertRedirect();
    $this->get(route('reports.account-balances.index'))->assertRedirect();
    $this->get(route('reports.general-ledger.index'))->assertRedirect();
});

it('user without permission cannot access financial reports', function () {
    $unprivilegedUser = User::factory()->create();
    $this->actingAs($unprivilegedUser);

    $this->get(route('reports.balance-sheet.index'))->assertForbidden();
    $this->get(route('reports.income-statement.index'))->assertForbidden();
    $this->get(route('reports.trial-balance.index'))->assertForbidden();
    $this->get(route('reports.account-balances.index'))->assertForbidden();
    $this->get(route('reports.general-ledger.index'))->assertForbidden();
});

it('user without journal permission cannot create entries', function () {
    $unprivilegedUser = User::factory()->create();
    $this->actingAs($unprivilegedUser);

    $this->get(route('journal-entries.index'))->assertForbidden();
    $this->get(route('journal-entries.create'))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| 13. Accounting Period Assignment
|--------------------------------------------------------------------------
*/

it('journal entry is assigned to correct accounting period', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Period test',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 1000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    expect($result['data']->accounting_period_id)->toBe($this->period->id);
});

/*
|--------------------------------------------------------------------------
| 14. Edge Cases
|--------------------------------------------------------------------------
*/

it('handles decimal amounts with rounding correctly', function () {
    $result = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Decimal test',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 1000.55, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 1000.55, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    expect($result['success'])->toBeTrue();
    expect((float) $result['data']->details[0]->debit)->toBe(1000.55);
    expect((float) $result['data']->details[1]->credit)->toBe(1000.55);
});

it('complex scenario: investment → sale → expense → reversal → balance sheet balances', function () {
    $r1 = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Capital investment',
        'lines' => [
            ['account_id' => $this->cash->id, 'debit' => 100000, 'credit' => 0, 'description' => 'Cash', 'cost_center_id' => null],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 100000, 'description' => 'Capital', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $r2 = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Credit sale',
        'lines' => [
            ['account_id' => $this->receivables->id, 'debit' => 25000, 'credit' => 0, 'description' => 'Receivable', 'cost_center_id' => null],
            ['account_id' => $this->salesRevenue->id, 'debit' => 0, 'credit' => 25000, 'description' => 'Revenue', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $r3 = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Rent payment',
        'lines' => [
            ['account_id' => $this->rentExpense->id, 'debit' => 8000, 'credit' => 0, 'description' => 'Rent', 'cost_center_id' => null],
            ['account_id' => $this->cash->id, 'debit' => 0, 'credit' => 8000, 'description' => 'Cash out', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $this->service->reverseJournalEntry($r3['data']->id, 'Void rent');

    $r4 = $this->service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Correct rent',
        'lines' => [
            ['account_id' => $this->rentExpense->id, 'debit' => 6000, 'credit' => 0, 'description' => 'Correct rent', 'cost_center_id' => null],
            ['account_id' => $this->cash->id, 'debit' => 0, 'credit' => 6000, 'description' => 'Cash out', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    expect($r1['success'])->toBeTrue();
    expect($r2['success'])->toBeTrue();
    expect($r3['success'])->toBeTrue();
    expect($r4['success'])->toBeTrue();

    $response = $this->get(route('reports.balance-sheet.index', [
        'as_of_date' => now()->toDateString(),
    ]));
    $response->assertSuccessful();
    $response->assertSee('Balance Sheet is balanced');

    $tbResponse = $this->get(route('reports.trial-balance.index', [
        'as_of_date' => now()->toDateString(),
    ]));
    $tbResponse->assertSuccessful();
    $tbResponse->assertSee('0.00');
});
