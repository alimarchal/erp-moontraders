# Double-Entry Accounting System - Usage Guide

## Table of Contents
- [Understanding Double-Entry](#understanding-double-entry)
- [Opening Balance Entry](#opening-balance-entry)
- [Service Revenue Entry](#service-revenue-entry)
- [Common Transactions](#common-transactions)
- [Laravel Code Examples](#laravel-code-examples)

---

## Understanding Double-Entry

In double-entry accounting, every transaction affects **at least two accounts**:
- **Debit (Dr)**: Left side - increases Assets & Expenses, decreases Liabilities & Equity & Revenue
- **Credit (Cr)**: Right side - increases Liabilities & Equity & Revenue, decreases Assets & Expenses

**Golden Rule**: Total Debits = Total Credits (always!)

---

## Opening Balance Entry

### Scenario: Starting with Cash ₨500

When you start a business with ₨500 cash, you're injecting **Owner's Equity** (Capital).

**Accounting Entry:**
```
Dr. Cash                 ₨500
    Cr. Capital Stock           ₨500
```

**Why?**
- **Cash (Asset)** increases → Debit
- **Capital (Equity)** increases → Credit

### Laravel Code:

```php
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    // Create journal entry header
    $journalEntry = JournalEntry::create([
        'currency_id' => 1,              // PKR
        'entry_date' => '2025-10-30',
        'reference' => 'OB-001',
        'description' => 'Opening balance - Owner capital investment',
        'status' => 'draft',             // Start as draft
        'fx_rate_to_base' => 1.0,
    ]);

    // Line 1: Debit Cash (increases asset)
    JournalEntryDetail::create([
        'journal_entry_id' => $journalEntry->id,
        'chart_of_account_id' => 7,      // Cash account ID (code: 1131)
        'line_no' => 1,                   // ✅ Must set explicitly
        'debit' => 500.00,
        'credit' => 0.00,
        'description' => 'Opening cash balance',
    ]);

    // Line 2: Credit Capital Stock (increases equity)
    JournalEntryDetail::create([
        'journal_entry_id' => $journalEntry->id,
        'chart_of_account_id' => 29,     // Capital Stock account ID (code: 3100)
        'line_no' => 2,                   // ✅ Next line number
        'debit' => 0.00,
        'credit' => 500.00,
        'description' => 'Owner initial investment',
    ]);

    // Post the entry (makes it permanent)
    $journalEntry->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => auth()->id()]);
});
```

**Result:**
- Cash balance: ₨500
- Owner's Equity: ₨500
- Trial Balance: Balanced ✅

---

## Service Revenue Entry

### Scenario: Earned ₨1,000 from Services (Cash)

When you earn ₨1,000 from services and receive cash immediately.

**Accounting Entry:**
```
Dr. Cash                 ₨1,000
    Cr. Service Revenue         ₨1,000
```

**Why?**
- **Cash (Asset)** increases → Debit
- **Service Revenue (Revenue)** increases → Credit

### Laravel Code:

```php
DB::transaction(function () {
    // Create journal entry
    $journalEntry = JournalEntry::create([
        'currency_id' => 1,
        'entry_date' => '2025-10-30',
        'reference' => 'INV-001',
        'description' => 'Service revenue - Web development project',
        'status' => 'draft',
        'fx_rate_to_base' => 1.0,
    ]);

    // Line 1: Debit Cash (increases asset)
    JournalEntryDetail::create([
        'journal_entry_id' => $journalEntry->id,
        'chart_of_account_id' => 7,      // Cash account (code: 1131)
        'line_no' => 1,
        'debit' => 1000.00,
        'credit' => 0.00,
        'description' => 'Cash received from client',
    ]);

    // Line 2: Credit Service Revenue (increases income)
    JournalEntryDetail::create([
        'journal_entry_id' => $journalEntry->id,
        'chart_of_account_id' => 67,     // Service account ID (code: 4120)
        'line_no' => 2,
        'debit' => 0.00,
        'credit' => 1000.00,
        'description' => 'Web development services rendered',
    ]);

    // Post it
    $journalEntry->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => auth()->id()]);
});
```

**Result After Both Entries:**
- Cash balance: ₨500 + ₨1,000 = ₨1,500
- Service Revenue: ₨1,000
- Owner's Equity: ₨500
- Trial Balance: Still balanced ✅

---

## Common Transactions

### 1. Purchase Office Equipment (₨5,000 Cash)

```php
DB::transaction(function () {
    $je = JournalEntry::create([
        'currency_id' => 1,
        'entry_date' => '2025-10-30',
        'reference' => 'PO-001',
        'description' => 'Purchase office computer',
        'status' => 'draft',
        'fx_rate_to_base' => 1.0,
    ]);

    // Dr. Office Equipment (Asset increases)
    JournalEntryDetail::create([
        'journal_entry_id' => $je->id,
        'chart_of_account_id' => 22,     // Office Equipment (code: 1270)
        'line_no' => 1,
        'debit' => 5000.00,
        'credit' => 0.00,
        'description' => 'Dell laptop purchase',
    ]);

    // Cr. Cash (Asset decreases)
    JournalEntryDetail::create([
        'journal_entry_id' => $je->id,
        'chart_of_account_id' => 7,      // Cash (code: 1131)
        'line_no' => 2,
        'debit' => 0.00,
        'credit' => 5000.00,
        'description' => 'Payment for laptop',
    ]);

    $je->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => auth()->id()]);
});
```

### 2. Pay Rent Expense (₨2,000 Cash)

```php
DB::transaction(function () {
    $je = JournalEntry::create([
        'currency_id' => 1,
        'entry_date' => '2025-10-30',
        'reference' => 'PAY-001',
        'description' => 'Monthly office rent',
        'status' => 'draft',
        'fx_rate_to_base' => 1.0,
    ]);

    // Dr. Rent Expense (Expense increases)
    JournalEntryDetail::create([
        'journal_entry_id' => $je->id,
        'chart_of_account_id' => 54,     // Office Rent (code: 52130)
        'line_no' => 1,
        'debit' => 2000.00,
        'credit' => 0.00,
        'description' => 'October rent payment',
    ]);

    // Cr. Cash (Asset decreases)
    JournalEntryDetail::create([
        'journal_entry_id' => $je->id,
        'chart_of_account_id' => 7,      // Cash (code: 1131)
        'line_no' => 2,
        'debit' => 0.00,
        'credit' => 2000.00,
        'description' => 'Payment to landlord',
    ]);

    $je->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => auth()->id()]);
});
```

### 3. Service Revenue on Credit (₨3,000 - Customer will pay later)

```php
DB::transaction(function () {
    $je = JournalEntry::create([
        'currency_id' => 1,
        'entry_date' => '2025-10-30',
        'reference' => 'INV-002',
        'description' => 'Service revenue - Mobile app development',
        'status' => 'draft',
        'fx_rate_to_base' => 1.0,
    ]);

    // Dr. Accounts Receivable / Debtors (Asset increases)
    JournalEntryDetail::create([
        'journal_entry_id' => $je->id,
        'chart_of_account_id' => 4,      // Debtors (code: 1111)
        'line_no' => 1,
        'debit' => 3000.00,
        'credit' => 0.00,
        'description' => 'Invoice to ABC Company',
    ]);

    // Cr. Service Revenue (Income increases)
    JournalEntryDetail::create([
        'journal_entry_id' => $je->id,
        'chart_of_account_id' => 67,     // Service (code: 4120)
        'line_no' => 2,
        'debit' => 0.00,
        'credit' => 3000.00,
        'description' => 'Mobile app development services',
    ]);

    $je->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => auth()->id()]);
});
```

### 4. Receive Payment from Customer (₨3,000)

```php
DB::transaction(function () {
    $je = JournalEntry::create([
        'currency_id' => 1,
        'entry_date' => '2025-11-05',
        'reference' => 'REC-001',
        'description' => 'Payment received from ABC Company',
        'status' => 'draft',
        'fx_rate_to_base' => 1.0,
    ]);

    // Dr. Cash (Asset increases)
    JournalEntryDetail::create([
        'journal_entry_id' => $je->id,
        'chart_of_account_id' => 7,      // Cash (code: 1131)
        'line_no' => 1,
        'debit' => 3000.00,
        'credit' => 0.00,
        'description' => 'Cash received from ABC Company',
    ]);

    // Cr. Accounts Receivable / Debtors (Asset decreases)
    JournalEntryDetail::create([
        'journal_entry_id' => $je->id,
        'chart_of_account_id' => 4,      // Debtors (code: 1111)
        'line_no' => 2,
        'debit' => 0.00,
        'credit' => 3000.00,
        'description' => 'Settlement of invoice INV-002',
    ]);

    $je->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => auth()->id()]);
});
```

### 5. Pay Supplier (₨1,500 Cash for Inventory)

```php
DB::transaction(function () {
    $je = JournalEntry::create([
        'currency_id' => 1,
        'entry_date' => '2025-10-30',
        'reference' => 'PUR-001',
        'description' => 'Purchase inventory from supplier',
        'status' => 'draft',
        'fx_rate_to_base' => 1.0,
    ]);

    // Dr. Inventory (Asset increases)
    JournalEntryDetail::create([
        'journal_entry_id' => $je->id,
        'chart_of_account_id' => 13,     // Stock In Hand (code: 1161)
        'line_no' => 1,
        'debit' => 1500.00,
        'credit' => 0.00,
        'description' => 'Purchase goods for resale',
    ]);

    // Cr. Cash (Asset decreases)
    JournalEntryDetail::create([
        'journal_entry_id' => $je->id,
        'chart_of_account_id' => 7,      // Cash (code: 1131)
        'line_no' => 2,
        'debit' => 0.00,
        'credit' => 1500.00,
        'description' => 'Payment to supplier',
    ]);

    $je->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => auth()->id()]);
});
```

---

## Laravel Code Examples

### Helper Service Class

Create a reusable service for journal entries:

```php
// app/Services/AccountingService.php
<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    /**
     * Create a journal entry with multiple lines
     * 
     * @param array $data [
     *   'entry_date' => '2025-10-30',
     *   'reference' => 'JE-001',
     *   'description' => 'Transaction description',
     *   'lines' => [
     *       ['account_id' => 1131, 'debit' => 1000, 'credit' => 0, 'description' => 'Line desc'],
     *       ['account_id' => 4100, 'debit' => 0, 'credit' => 1000, 'description' => 'Line desc'],
     *   ]
     * ]
     * @return JournalEntry
     */
    public function createJournalEntry(array $data): JournalEntry
    {
        return DB::transaction(function () use ($data) {
            // Validate lines balance
            $totalDebits = collect($data['lines'])->sum('debit');
            $totalCredits = collect($data['lines'])->sum('credit');
            
            if (abs($totalDebits - $totalCredits) > 0.01) {
                throw new \Exception("Entry is not balanced. Debits: {$totalDebits}, Credits: {$totalCredits}");
            }

            // Create journal entry header
            $journalEntry = JournalEntry::create([
                'currency_id' => $data['currency_id'] ?? 1,
                'entry_date' => $data['entry_date'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'],
                'status' => 'draft',
                'fx_rate_to_base' => $data['fx_rate'] ?? 1.0,
            ]);

            // Create journal entry lines
            $lineNo = 1;
            foreach ($data['lines'] as $line) {
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'chart_of_account_id' => $line['account_id'],
                    'line_no' => $lineNo++,
                    'debit' => $line['debit'] ?? 0.00,
                    'credit' => $line['credit'] ?? 0.00,
                    'description' => $line['description'] ?? null,
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                ]);
            }

            // Auto-post if requested
            if ($data['auto_post'] ?? false) {
                $journalEntry->update([
                    'status' => 'posted',
                    'posted_at' => now(),
                    'posted_by' => auth()->id(),
                ]);
            }

            return $journalEntry;
        });
    }

    /**
     * Quick cash receipt entry
     */
    public function recordCashReceipt(float $amount, int $revenueAccountId, string $description)
    {
        return $this->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'reference' => 'CR-' . date('YmdHis'),
            'description' => $description,
            'lines' => [
                ['account_id' => 7, 'debit' => 1000, 'credit' => 0, 'description' => 'Cash received'],
                ['account_id' => $revenueAccountId, 'debit' => 0, 'credit' => $amount, 'description' => $description],
            ],
            'auto_post' => true,
        ]);
    }

    /**
     * Quick cash payment entry
     */
    public function recordCashPayment(float $amount, int $expenseAccountId, string $description)
    {
        return $this->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'reference' => 'CP-' . date('YmdHis'),
            'description' => $description,
            'lines' => [
                ['account_id' => $expenseAccountId, 'debit' => $amount, 'credit' => 0, 'description' => $description],
                ['account_id' => 7, 'debit' => 0, 'credit' => $amount, 'description' => 'Cash paid'],
            ],
            'auto_post' => true,
        ]);
    }
}
```

### Using the Service:

```php
// In your controller
use App\Services\AccountingService;

class TransactionController extends Controller
{
    protected $accounting;

    public function __construct(AccountingService $accounting)
    {
        $this->accounting = $accounting;
    }

    public function recordOpeningBalance()
    {
        $entry = $this->accounting->createJournalEntry([
            'entry_date' => '2025-10-30',
            'reference' => 'OB-001',
            'description' => 'Opening balance',
            'lines' => [
                ['account_id' => 7, 'debit' => 500, 'credit' => 0, 'description' => 'Opening cash'],
                ['account_id' => 29, 'debit' => 0, 'credit' => 500, 'description' => 'Owner capital'],
            ],
            'auto_post' => true,
        ]);

        return response()->json(['message' => 'Opening balance recorded', 'entry' => $entry]);
    }

    public function recordServiceRevenue()
    {
        $entry = $this->accounting->recordCashReceipt(
            amount: 1000.00,
            revenueAccountId: 67, // Service (code: 4120)
            description: 'Web development services'
        );

        return response()->json(['message' => 'Revenue recorded', 'entry' => $entry]);
    }

    public function payRent()
    {
        $entry = $this->accounting->recordCashPayment(
            amount: 2000.00,
            expenseAccountId: 54, // Office Rent (code: 52130)
            description: 'Monthly office rent'
        );

        return response()->json(['message' => 'Rent payment recorded', 'entry' => $entry]);
    }
}
```

---

## Important Account IDs (From Your Seeded Data)

```php
// Assets
7   => 'Cash (code: 1131)',
4   => 'Debtors / Accounts Receivable (code: 1111)',
5   => 'Bank Accounts (code: 1120)',
13  => 'Stock In Hand / Inventory (code: 1161)',
22  => 'Office Equipments (code: 1270)',

// Liabilities
72  => 'Creditors / Accounts Payable (code: 2111)',
78  => 'Secured Loans (code: 2132)',

// Equity
29  => 'Capital Stock (code: 3100)',
32  => 'Retained Earnings (code: 3400)',

// Revenue (Income)
66  => 'Sales (code: 4110)',
67  => 'Service (code: 4120)',

// Expenses
37  => 'Cost of Goods Sold (code: 5111)',
54  => 'Office Rent (code: 52130)',
58  => 'Salary (code: 52170)',
62  => 'Utility Expenses (code: 52210)',
44  => 'Depreciation (code: 5230)',
```

---

## Checking Your Balances

### View Trial Balance:

```php
$trialBalance = DB::table('vw_trial_balance')->first();
echo "Debits: " . $trialBalance->total_debits . "\n";
echo "Credits: " . $trialBalance->total_credits . "\n";
echo "Difference: " . $trialBalance->difference . "\n"; // Should be 0
```

### View Account Balances:

```php
$accounts = DB::table('vw_account_balances')
    ->where('balance', '!=', 0)
    ->orderBy('account_code')
    ->get();

foreach ($accounts as $account) {
    echo "{$account->account_code} - {$account->account_name}: {$account->balance}\n";
}
```

### View General Ledger:

```php
$ledger = DB::table('vw_general_ledger')
    ->where('account_id', 1131) // Cash account
    ->orderBy('entry_date')
    ->get();

foreach ($ledger as $entry) {
    echo "{$entry->entry_date}: {$entry->journal_description} - Dr: {$entry->debit}, Cr: {$entry->credit}\n";
}
```

---

## Quick Reference: Debit or Credit?

| Account Type | Increases by | Decreases by | Normal Balance |
|--------------|-------------|--------------|----------------|
| **Assets** | Debit | Credit | Debit |
| **Liabilities** | Credit | Debit | Credit |
| **Equity** | Credit | Debit | Credit |
| **Revenue** | Credit | Debit | Credit |
| **Expenses** | Debit | Credit | Debit |

**Remember:** In every transaction, Debits = Credits!

---

## Tips for Success

1. **Always use transactions** - Wrap journal entries in `DB::transaction()`
2. **Set line_no explicitly** - The system requires it (1, 2, 3...)
3. **Validate balance** - Check debits = credits before saving
4. **Use drafts first** - Create as 'draft', verify, then post
5. **Posted entries are immutable** - You can't edit them, create reversing entries instead
6. **Use cost centers** - Track expenses by department/project (optional field)
7. **Add attachments** - Link receipts/invoices to journal entries
8. **Check trial balance regularly** - It should always be 0.00

---

## Need Help?

- View all account codes: `php artisan tinker` → `DB::table('chart_of_accounts')->select('id','account_code','account_name')->get()`
- Check system status: See `DOUBLE_ENTRY_ENHANCEMENTS.md`
- Verify constraints are working: All triggers and constraints are active in your MariaDB 10.4.28

Happy Accounting! 📊✨
