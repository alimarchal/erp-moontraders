<?php

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\BankEntryCategory;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/Support/settlement-fixtures.php';

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * The Chart of Accounts and cost center a GRN needs to post.
 *
 * Posting a GRN writes its journal entry or fails, so any test that posts one
 * has to give it somewhere to post to. Safe to call more than once.
 */
function seedGrnPostingAccounts(): void
{
    $currency = Currency::where('is_base_currency', true)->first()
        ?? Currency::create([
            'currency_code' => 'PKR',
            'currency_name' => 'Pakistani Rupee',
            'currency_symbol' => 'Rs',
            'is_base_currency' => true,
        ]);

    CostCenter::firstOrCreate(
        ['code' => 'CC006'],
        ['name' => 'Warehouse & Inventory', 'is_active' => true]
    );

    // The journal entry is dated on the GRN's receipt date, which tests often set a few
    // days back, so open last month's period too or they fail early in the month.
    foreach ([now()->subMonthNoOverflow(), now()] as $month) {
        if (! AccountingPeriod::where('start_date', '<=', $month)->where('end_date', '>=', $month)->exists()) {
            AccountingPeriod::create([
                'name' => $month->format('F Y'),
                'start_date' => $month->copy()->startOfMonth(),
                'end_date' => $month->copy()->endOfMonth(),
                'status' => 'open',
            ]);
        }
    }

    $accounts = [
        '1151' => ['Stock In Hand', 'Assets', 'BalanceSheet', 'Asset', 'debit'],
        '1155' => ['Van Stock', 'Assets', 'BalanceSheet', 'Asset', 'debit'],
        '2111' => ['Creditors', 'Liabilities', 'BalanceSheet', 'Liability', 'credit'],
        '2142' => ['Stock Received But Not Billed', 'Liabilities', 'BalanceSheet', 'Liability', 'credit'],
        '4210' => ['FMR Allowance Liquid', 'Income', 'IncomeStatement', 'Revenue', 'credit'],
        '4220' => ['FMR Allowance Powder', 'Income', 'IncomeStatement', 'Revenue', 'credit'],
        '5111' => ['Cost of Goods Sold', 'Expenses', 'IncomeStatement', 'Expense', 'debit'],
        '5213' => ['Inventory Shortage', 'Expenses', 'IncomeStatement', 'Expense', 'debit'],
        '5271' => ['Round Off', 'Expenses', 'IncomeStatement', 'Expense', 'debit'],
        '5273' => ['Stock Loss - Other', 'Expenses', 'IncomeStatement', 'Expense', 'debit'],
    ];

    foreach ($accounts as $code => [$name, $typeName, $reportGroup, $category, $normalBalance]) {
        $type = AccountType::firstOrCreate(
            ['type_name' => $typeName],
            ['report_group' => $reportGroup, 'category' => $category]
        );

        ChartOfAccount::firstOrCreate(
            ['account_code' => $code],
            [
                'account_type_id' => $type->id,
                'currency_id' => $currency->id,
                'account_name' => $name,
                'normal_balance' => $normalBalance,
                'is_active' => true,
            ]
        );
    }
}

/**
 * Accounts, an open accounting period and the cost center the expense, vendor-payment and payroll services post to.
 * Posting writes its journal entry or fails, so any test that approves one needs this. Safe to call more than once.
 *
 * @return array<string, ChartOfAccount> accounts keyed by account code
 */
function seedExpenseLedger(): array
{
    $currency = Currency::where('is_base_currency', true)->first()
        ?? Currency::create(['currency_code' => 'PKR', 'currency_name' => 'Pakistani Rupee', 'currency_symbol' => 'Rs', 'is_base_currency' => true]);

    // The services post to cost center 1.
    CostCenter::unguarded(fn () => CostCenter::updateOrCreate(['id' => 1], ['code' => 'CC001', 'name' => 'Head Office', 'is_active' => true]));

    foreach ([now()->subMonthNoOverflow(), now(), now()->addMonthNoOverflow()] as $month) {
        if (! AccountingPeriod::where('start_date', '<=', $month)->where('end_date', '>=', $month)->exists()) {
            AccountingPeriod::create(['name' => $month->format('F Y'), 'start_date' => $month->copy()->startOfMonth(), 'end_date' => $month->copy()->endOfMonth(), 'status' => 'open']);
        }
    }

    $definitions = [
        '1121' => ['Cash', 'Assets', 'BalanceSheet', 'Asset', 'debit'],
        '1122' => ['Cheques in Hand', 'Assets', 'BalanceSheet', 'Asset', 'debit'],
        '1131' => ['Employee Advances', 'Assets', 'BalanceSheet', 'Asset', 'debit'],
        '1132' => ['Employee Loans', 'Assets', 'BalanceSheet', 'Asset', 'debit'],
        '1171' => ['HBL Main Account', 'Assets', 'BalanceSheet', 'Asset', 'debit'],
        '2112' => ['Payroll Payable', 'Liabilities', 'BalanceSheet', 'Liability', 'credit'],
        '2113' => ['Expense Payables', 'Liabilities', 'BalanceSheet', 'Liability', 'credit'],
        '2120' => ['Duties and Taxes', 'Liabilities', 'BalanceSheet', 'Liability', 'credit'],
        '5221' => ['Miscellaneous Expenses', 'Expenses', 'IncomeStatement', 'Expense', 'debit'],
        '5281' => ['Salary', 'Expenses', 'IncomeStatement', 'Expense', 'debit'],
    ];

    $accounts = [];

    foreach ($definitions as $code => [$name, $typeName, $reportGroup, $category, $normalBalance]) {
        $type = AccountType::firstOrCreate(['type_name' => $typeName], ['report_group' => $reportGroup, 'category' => $category]);

        $accounts[$code] = ChartOfAccount::firstOrCreate(['account_code' => $code], [
            'account_type_id' => $type->id, 'currency_id' => $currency->id, 'account_name' => $name,
            'normal_balance' => $normalBalance, 'is_active' => true,
        ]);
    }

    return $accounts;
}

/**
 * Settlement Excess Income (4250), where a settlement books cash handed in above what was due.
 */
function seedSettlementExcessAccount(): ChartOfAccount
{
    $type = AccountType::firstOrCreate(['type_name' => 'Income'], ['report_group' => 'IncomeStatement', 'category' => 'Income']);

    return ChartOfAccount::firstOrCreate(['account_code' => '4250'], [
        'account_type_id' => $type->id, 'currency_id' => Currency::where('is_base_currency', true)->value('id'), 'account_name' => 'Settlement Excess Income',
        'normal_balance' => 'credit', 'is_active' => true,
    ]);
}

/**
 * Everything a Bank Statement Register test needs to post a journal: a base currency, an open
 * accounting period, a bank account linked to a ledger, and a company with receivable/payable ledgers.
 *
 * The register posts to the live chart: `bankLedger` (= `liveLedger`) is the bank account's own ledger, the one
 * settlement and the rest use too.
 *
 * @return array{bank: BankAccount, company: Supplier, category: BankEntryCategory, bankLedger: ChartOfAccount, liveLedger: ChartOfAccount, liveReceivable: ChartOfAccount, livePayable: ChartOfAccount}
 */
function bankRegisterFixture(): array
{
    $currency = Currency::where('is_base_currency', true)->first()
        ?? Currency::create([
            'currency_code' => 'PKR',
            'currency_name' => 'Pakistani Rupee',
            'currency_symbol' => 'Rs',
            'is_base_currency' => true,
        ]);

    if (! AccountingPeriod::where('start_date', '<=', now())->where('end_date', '>=', now())->exists()) {
        AccountingPeriod::create([
            'name' => now()->format('F Y'),
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => 'open',
        ]);
    }

    $type = AccountType::firstOrCreate(
        ['type_name' => 'Assets'],
        ['report_group' => 'BalanceSheet', 'category' => 'Asset']
    );

    $ledger = fn (string $code, string $name) => ChartOfAccount::create([
        'account_type_id' => $type->id,
        'currency_id' => $currency->id,
        'account_code' => $code,
        'account_name' => $name,
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);

    $liveLedger = $ledger('1121', 'HBL Bank');
    $receivable = $ledger('1141', 'Company Receivable');
    $payable = $ledger('2111', 'Company Payable');

    $bank = BankAccount::factory()->create(['chart_of_account_id' => $liveLedger->id]);

    return [
        'bank' => $bank,
        'company' => Supplier::factory()->create(['supplier_name' => 'Unilever', 'disabled' => false]),
        'category' => BankEntryCategory::query()->where('slug', 'customer-receipt')->firstOrFail(),
        'bankLedger' => $liveLedger,
        'liveLedger' => $liveLedger,
        'liveReceivable' => $receivable,
        'livePayable' => $payable,
    ];
}

/**
 * A user holding exactly the given permissions (the permissions come from the bank register migration).
 *
 * @param  array<int, string>  $permissions
 */
function bankUser(array $permissions, string $name = 'Test User'): User
{
    $user = User::factory()->create(['name' => $name]);
    $user->givePermissionTo($permissions);

    return $user;
}
