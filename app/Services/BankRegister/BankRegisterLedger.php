<?php

namespace App\Services\BankRegister;

use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\BankStatementEntry;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Supplier;
use Illuminate\Support\Collection;

/**
 * The Bank Register keeps its own books, separate from GRN, settlement, goods issue and the rest.
 *
 * Every journal it posts uses only accounts created here, under their own root (6000) and their own
 * account type (report group "BankRegister"). The Balance Sheet, Income Statement and period closing only
 * read the "BalanceSheet" / "IncomeStatement" groups, so nothing the register does can change those
 * reports or any balance of the live accounts. Trial balance, general ledger and account balances still
 * show the register accounts, because the journals are ordinary journals.
 */
class BankRegisterLedger
{
    public const TYPE_NAME = 'Bank Register';

    public const REPORT_GROUP = 'BankRegister';

    /** @var array{0: string, 1: string, 2: string} preferred code, name, normal balance */
    public const ROOT = ['6000', 'Bank Register (memo accounts)', 'debit'];

    /** @var array<string, array{0: string, 1: string, 2: string}> */
    public const GROUPS = [
        'bank' => ['6100', 'BR - Bank & Cash Accounts', 'debit'],
        'clearing' => ['6200', 'BR - Clearing & Suspense', 'debit'],
        'company' => ['6300', 'BR - Company Accounts', 'debit'],
    ];

    /** @var array<string, array{0: string, 1: string, 2: string}> */
    public const ACCOUNTS = [
        'customer_receipts' => ['6201', 'BR - Customer Receipts Clearing', 'credit'],
        'cash_deposits' => ['6202', 'BR - Cash Deposits Clearing', 'credit'],
        'unidentified_receipts' => ['6203', 'BR - Unidentified Receipts (suspense)', 'credit'],
        'owner_partner' => ['6204', 'BR - Owner / Partner', 'credit'],
        'supplier_payments' => ['6205', 'BR - Principal & Supplier Payments Clearing', 'debit'],
        'salary' => ['6206', 'BR - Salary & Staff Payments Clearing', 'debit'],
        'bank_charges' => ['6207', 'BR - Bank Charges', 'debit'],
        'tax' => ['6208', 'BR - Tax & Government Payments', 'debit'],
        'transfers' => ['6209', 'BR - Inter-account & Self Transfers', 'debit'],
        'unidentified_payments' => ['6210', 'BR - Unidentified Payments (suspense)', 'debit'],
    ];

    /**
     * Categories whose money belongs to a company: when the entry has a company, that company's own register
     * account is used (one per company, so every company has its own ledger), otherwise the clearing account.
     *
     * @var array<int, string>
     */
    public const COMPANY_CATEGORIES = ['customer-receipt', 'principal-payment', 'supplier-payment', 'other'];

    /** Category slug => register account used when the category has no account of its own. */
    public const CATEGORY_ACCOUNTS = [
        'customer-receipt' => 'customer_receipts',
        'cash-deposit' => 'cash_deposits',
        'owner-partner' => 'owner_partner',
        'principal-payment' => 'supplier_payments',
        'supplier-payment' => 'supplier_payments',
        'salary-staff' => 'salary',
        'bank-charges' => 'bank_charges',
        'tax-government' => 'tax',
        'inter-bank-transfer' => 'transfers',
        'own-self-transfer' => 'transfers',
    ];

    /**
     * Create the type, the root, the two groups and every clearing account that does not exist yet.
     * Codes are taken from the preferred number upwards inside their block, so an existing live
     * account is never reused or overwritten. Returns false when there is no currency yet.
     */
    public function ensureStructure(): bool
    {
        if (! $this->currency()) {
            return false;
        }

        $root = $this->ensure(self::ROOT[1], self::ROOT[0], self::ROOT[2], null, true, 9000);

        $groups = [];
        foreach (self::GROUPS as $key => [$code, $name, $balance]) {
            $groups[$key] = $this->ensure($name, $code, $balance, $root->id, true, (int) $code + 99);
        }

        foreach (self::ACCOUNTS as [$code, $name, $balance]) {
            $this->ensure($name, $code, $balance, $groups['clearing']->id, false, (int) $groups['clearing']->account_code + 99);
        }

        return true;
    }

    /**
     * Create the clearing accounts and a register ledger for every active bank / cash / wallet account.
     */
    public function ensureAll(): int
    {
        if (! $this->ensureStructure()) {
            return 0;
        }

        $created = 0;

        foreach (BankAccount::query()->where('is_active', true)->whereNull('register_chart_of_account_id')->get() as $bank) {
            $this->ledgerForBank($bank);
            $created++;
        }

        return $created;
    }

    public function account(string $key): ChartOfAccount
    {
        if (! $this->ensureStructure()) {
            throw new BankRegisterException('There is no base currency yet, so the register accounts cannot be created.');
        }

        return ChartOfAccount::query()->where('account_name', self::ACCOUNTS[$key][1])->firstOrFail();
    }

    /**
     * The register's own ledger for one bank / cash / wallet account (created on first use).
     */
    public function ledgerForBank(BankAccount $bank): ChartOfAccount
    {
        if ($bank->register_chart_of_account_id && $ledger = ChartOfAccount::find($bank->register_chart_of_account_id)) {
            return $ledger;
        }

        if (! $this->ensureStructure()) {
            throw new BankRegisterException('There is no base currency yet, so the register accounts cannot be created.');
        }

        $group = ChartOfAccount::query()->where('account_name', self::GROUPS['bank'][1])->firstOrFail();
        $name = "BR - {$bank->account_name} ({$bank->account_number})";
        $ledger = $this->ensure($name, (string) ((int) $group->account_code + 1), 'debit', $group->id, false, (int) $group->account_code + 99);

        $bank->forceFill(['register_chart_of_account_id' => $ledger->id])->save();

        return $ledger;
    }

    /**
     * One register account per company: payments to it are debits, receipts from it are credits, so its balance
     * is what the register has paid out to that company minus what it has received.
     */
    public function companyAccount(Supplier $company): ChartOfAccount
    {
        if (! $this->ensureStructure()) {
            throw new BankRegisterException('There is no base currency yet, so the register accounts cannot be created.');
        }

        $group = ChartOfAccount::query()->where('account_name', self::GROUPS['company'][1])->firstOrFail();

        return $this->ensure("BR - {$company->supplier_name}", (string) ((int) $group->account_code + 1), 'debit', $group->id, false, (int) $group->account_code + 99);
    }

    /**
     * The account on the other side of the bank for a statement line, always one of the register's own.
     * A category may be given an account of its own in Settings; otherwise its slug decides, and anything
     * unknown goes to the suspense account for its direction, with the party and narration in the journal text.
     */
    public function otherSide(BankStatementEntry $entry): ChartOfAccount
    {
        $categoryAccountId = $entry->category?->default_chart_of_account_id;

        if ($categoryAccountId && $account = ChartOfAccount::query()->where('is_active', true)->find($categoryAccountId)) {
            return $account;
        }

        $slug = $entry->category?->slug;

        if ($entry->company && (in_array($slug, self::COMPANY_CATEGORIES, true) || ! isset(self::CATEGORY_ACCOUNTS[$slug]))) {
            try {
                return $this->companyAccount($entry->company);
            } catch (BankRegisterException) {
                // the 99 company accounts are used up: fall through to the clearing account
            }
        }

        $key = self::CATEGORY_ACCOUNTS[$slug] ?? ($entry->direction === 'credit' ? 'unidentified_receipts' : 'unidentified_payments');

        return $this->account($key);
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

    /**
     * @return Collection<int, ChartOfAccount>
     */
    public function accounts()
    {
        $root = ChartOfAccount::query()->where('account_name', self::ROOT[1])->first();

        return $root
            ? ChartOfAccount::query()->where('account_code', '>=', $root->account_code)->where('account_code', '<=', '6999')->orderBy('account_code')->get()
            : collect();
    }

    private function ensure(string $name, string $preferredCode, string $normalBalance, ?int $parentId, bool $isGroup, int $blockEnd): ChartOfAccount
    {
        $existing = ChartOfAccount::query()->where('account_name', $name)->first();

        if ($existing) {
            return $existing;
        }

        $currency = $this->currency();

        return ChartOfAccount::create([
            'parent_id' => $parentId,
            'account_type_id' => $this->accountType()->id,
            'currency_id' => $currency->id,
            'account_code' => $this->freeCode((int) $preferredCode, $blockEnd),
            'account_name' => $name,
            'normal_balance' => $normalBalance,
            'description' => 'Created by the Bank Register. Memo account, not part of the Balance Sheet or Income Statement.',
            'is_group' => $isGroup,
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

        throw new BankRegisterException("No free account code between {$from} and {$to} for the Bank Register accounts.");
    }

    private function accountType(): AccountType
    {
        return AccountType::query()->firstOrCreate(
            ['type_name' => self::TYPE_NAME],
            ['report_group' => self::REPORT_GROUP, 'description' => 'Accounts kept by the Bank Register, outside the Balance Sheet and Income Statement.']
        );
    }

    private function currency(): ?Currency
    {
        return Currency::query()->where('is_base_currency', true)->first() ?? Currency::query()->first();
    }
}
