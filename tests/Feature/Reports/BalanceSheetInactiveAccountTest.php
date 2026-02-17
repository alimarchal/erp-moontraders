<?php

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\User;
use App\Services\AccountingService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'report-view-financial']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-view-financial');
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
});

it('includes inactive balance sheet accounts with posted transactions', function () {
    $cashAccount = ChartOfAccount::create([
        'account_code' => '1001',
        'account_name' => 'Cash',
        'account_type_id' => $this->assetType->id,
        'currency_id' => $this->currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $bankAccount = ChartOfAccount::create([
        'account_code' => '1002',
        'account_name' => 'Bank Account',
        'account_type_id' => $this->assetType->id,
        'currency_id' => $this->currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $creditors = ChartOfAccount::create([
        'account_code' => '2001',
        'account_name' => 'Creditors',
        'account_type_id' => $this->liabilityType->id,
        'currency_id' => $this->currency->id,
        'normal_balance' => 'credit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $service = app(AccountingService::class);

    $result = $service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Deposit to bank',
        'lines' => [
            ['account_id' => $bankAccount->id, 'debit' => 5000, 'credit' => 0, 'description' => 'Bank deposit', 'cost_center_id' => null],
            ['account_id' => $creditors->id, 'debit' => 0, 'credit' => 5000, 'description' => 'Creditor payment', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    expect($result['success'])->toBeTrue();

    $bankAccount->update(['is_active' => false]);

    $response = $this->get(route('reports.balance-sheet.index', [
        'as_of_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertDontSee('Balance Sheet does not balance');
    $response->assertSee('Balance Sheet is balanced');
    $response->assertSee('Bank Account');
});

it('includes inactive income statement accounts in net income calculation', function () {
    $cashAccount = ChartOfAccount::create([
        'account_code' => '1001',
        'account_name' => 'Cash',
        'account_type_id' => $this->assetType->id,
        'currency_id' => $this->currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $creditors = ChartOfAccount::create([
        'account_code' => '2001',
        'account_name' => 'Creditors',
        'account_type_id' => $this->liabilityType->id,
        'currency_id' => $this->currency->id,
        'normal_balance' => 'credit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $salesAccount = ChartOfAccount::create([
        'account_code' => '4001',
        'account_name' => 'Sales Revenue',
        'account_type_id' => $this->incomeType->id,
        'currency_id' => $this->currency->id,
        'normal_balance' => 'credit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $service = app(AccountingService::class);

    $result1 = $service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Credit purchase',
        'lines' => [
            ['account_id' => $cashAccount->id, 'debit' => 3000, 'credit' => 0, 'description' => 'Cash received', 'cost_center_id' => null],
            ['account_id' => $creditors->id, 'debit' => 0, 'credit' => 3000, 'description' => 'Creditor', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    $result2 = $service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Sale recorded',
        'lines' => [
            ['account_id' => $cashAccount->id, 'debit' => 2000, 'credit' => 0, 'description' => 'Cash from sale', 'cost_center_id' => null],
            ['account_id' => $salesAccount->id, 'debit' => 0, 'credit' => 2000, 'description' => 'Sales revenue', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    expect($result1['success'])->toBeTrue();
    expect($result2['success'])->toBeTrue();

    $salesAccount->update(['is_active' => false]);

    $response = $this->get(route('reports.balance-sheet.index', [
        'as_of_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertDontSee('Balance Sheet does not balance');
    $response->assertSee('Balance Sheet is balanced');
});

it('balance sheet balances with all active accounts', function () {
    $cashAccount = ChartOfAccount::create([
        'account_code' => '1001',
        'account_name' => 'Cash',
        'account_type_id' => $this->assetType->id,
        'currency_id' => $this->currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $creditors = ChartOfAccount::create([
        'account_code' => '2001',
        'account_name' => 'Creditors',
        'account_type_id' => $this->liabilityType->id,
        'currency_id' => $this->currency->id,
        'normal_balance' => 'credit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $service = app(AccountingService::class);

    $result = $service->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'description' => 'Initial transaction',
        'lines' => [
            ['account_id' => $cashAccount->id, 'debit' => 10000, 'credit' => 0, 'description' => 'Cash in', 'cost_center_id' => null],
            ['account_id' => $creditors->id, 'debit' => 0, 'credit' => 10000, 'description' => 'Creditor', 'cost_center_id' => null],
        ],
        'auto_post' => true,
    ]);

    expect($result['success'])->toBeTrue();

    $response = $this->get(route('reports.balance-sheet.index', [
        'as_of_date' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertSee('Balance Sheet is balanced');
});
