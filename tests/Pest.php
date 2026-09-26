<?php

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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

    // The journal entry is dated on the GRN's receipt date and needs an open period for it.
    if (! AccountingPeriod::where('start_date', '<=', now())->where('end_date', '>=', now())->exists()) {
        AccountingPeriod::create([
            'name' => now()->format('F Y'),
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => 'open',
        ]);
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
