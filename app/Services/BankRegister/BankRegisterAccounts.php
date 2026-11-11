<?php

namespace App\Services\BankRegister;

use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\BankStatementEntry;
use App\Models\ChartOfAccount;
use App\Models\Currency;

/**
 * The live accounts the Bank Register posts to. The register has no books of its own: the bank side is always the
 * bank account's own ledger (the one settlement, supplier payments and the rest already use), and the other side is
 * an account of the main chart of accounts, so everything it posts is in the Trial Balance, Balance Sheet and Income
 * Statement, and the reconciliation compares the bank statement with that one ledger.
 *
 * Money that is already in the books (a settlement deposit, a supplier payment) is never posted again: the statement
 * line is linked to the existing journal line instead (BankEntryPostingService::link).
 */
class BankRegisterAccounts
{
    /**
     * Accounts the register creates in the main chart when they do not exist yet.
     * Key => [name, parent code, preferred code, last code of its block, account type name, normal balance].
     *
     * @var array<string, array{0: string, 1: string, 2: int, 3: int, 4: string, 5: string}>
     */
    public const CREATED = [
        'bank_charges' => ['Bank Charges', '5200', 5284, 5299, 'Expenses', 'debit'],
        'bank_tax' => ['Advance Tax - Bank Deductions', '1160', 1165, 1169, 'Assets', 'debit'],
        'transfers' => ['Bank Transfers in Transit', '1100', 1190, 1199, 'Assets', 'debit'],
        'suspense' => ['Bank Suspense', '2100', 2150, 2199, 'Liabilities', 'credit'],
        'owner' => ['Owner / Partner Current Account', '3000', 3600, 3699, 'Equity', 'credit'],
    ];

    /**
     * Accounts that already exist in the main chart and are used as they are (by code). When one is missing the
     * entry goes to the suspense account, where it is easy to find and move.
     *
     * @var array<string, string>
     */
    public const EXISTING = [
        'cash' => '1121',
        'debtors' => '1111',
        'creditors' => '2111',
        'salary' => '5281',
        'opening_equity' => '3300',
    ];

    /** Category slug => account key used when the category has no account of its own (Settings > Bank categories). */
    public const CATEGORY_ACCOUNTS = [
        'opening-balance' => 'opening_equity',
        'customer-receipt' => 'debtors',
        'cash-deposit' => 'cash',
        'owner-partner' => 'owner',
        'principal-payment' => 'creditors',
        'supplier-payment' => 'creditors',
        'salary-staff' => 'salary',
        'bank-charges' => 'bank_charges',
        'tax-government' => 'bank_tax',
        'inter-bank-transfer' => 'transfers',
        'own-self-transfer' => 'transfers',
    ];

    /**
     * The bank, cash or wallet account's own ledger: the bank side of every register journal and of the reconciliation.
     */
    public function bankLedger(BankAccount $bank): ChartOfAccount
    {
        $ledger = $bank->chart_of_account_id ? ChartOfAccount::query()->find($bank->chart_of_account_id) : null;

        if (! $ledger) {
            throw new BankRegisterException("{$bank->account_name} has no ledger account. Link it in Settings › Bank Accounts first.");
        }

        return $ledger;
    }

    /**
     * The account on the other side of the bank for a statement line: the category's own account when it has one,
     * else the account its kind of money belongs to, else the suspense account.
     */
    public function otherSide(BankStatementEntry $entry): ChartOfAccount
    {
        $categoryAccountId = $entry->category?->default_chart_of_account_id;

        if ($categoryAccountId && $account = ChartOfAccount::query()->where('is_active', true)->find($categoryAccountId)) {
            return $account;
        }

        return $this->account(self::CATEGORY_ACCOUNTS[$entry->category?->slug] ?? 'suspense');
    }

    public function account(string $key): ChartOfAccount
    {
        if (isset(self::EXISTING[$key])) {
            return ChartOfAccount::query()->where('account_code', self::EXISTING[$key])->where('is_active', true)->first()
                ?? $this->account('suspense');
        }

        if (! isset(self::CREATED[$key])) {
            throw new BankRegisterException("Unknown register account \"{$key}\".");
        }

        return $this->ensure($key);
    }

    /**
     * Create every account of self::CREATED that is missing. Returns false when there is no currency yet (empty database).
     */
    public function ensureAll(): bool
    {
        if (! $this->currency()) {
            return false;
        }

        foreach (array_keys(self::CREATED) as $key) {
            $this->ensure($key);
        }

        return true;
    }

    /**
     * Journal text that stays readable when the data is exported: receipt, party / company, reference, narration.
     */
    public function narration(BankStatementEntry $entry, ?string $receiptNo): string
    {
        $parts = array_filter([
            $receiptNo,
            $entry->company?->supplier_name ?: $entry->party_name_in_statement,
            $entry->reference,
            $entry->description ?: $entry->narration,
        ], fn ($part) => filled($part));

        return mb_substr(implode(' | ', $parts), 0, 250);
    }

    private function ensure(string $key): ChartOfAccount
    {
        [$name, $parentCode, $preferred, $blockEnd, $typeName, $normalBalance] = self::CREATED[$key];

        $existing = ChartOfAccount::query()->where('account_name', $name)->first();

        if ($existing) {
            return $existing;
        }

        $currency = $this->currency() ?? throw new BankRegisterException('There is no base currency yet, so the bank accounts cannot be created.');
        $parent = ChartOfAccount::query()->where('account_code', $parentCode)->first();

        return ChartOfAccount::create([
            'parent_id' => $parent?->id,
            'account_type_id' => $parent?->account_type_id ?? $this->accountType($typeName)->id,
            'currency_id' => $currency->id,
            'account_code' => $this->freeCode($preferred, $blockEnd),
            'account_name' => $name,
            'normal_balance' => $normalBalance,
            'description' => 'Created by the Bank Register for bank lines that no other module books.',
            'is_group' => false,
            'is_active' => true,
        ]);
    }

    private function freeCode(int $from, int $to): string
    {
        for ($code = $from; $code <= $to; $code++) {
            if (! ChartOfAccount::query()->where('account_code', (string) $code)->exists()) {
                return (string) $code;
            }
        }

        throw new BankRegisterException("No free account code between {$from} and {$to} for the bank accounts.");
    }

    private function accountType(string $name): AccountType
    {
        $reportGroup = in_array($name, ['Expenses', 'Income'], true) ? 'IncomeStatement' : 'BalanceSheet';

        return AccountType::query()->firstOrCreate(['type_name' => $name], ['report_group' => $reportGroup]);
    }

    private function currency(): ?Currency
    {
        return Currency::query()->where('is_base_currency', true)->first() ?? Currency::query()->first();
    }
}
