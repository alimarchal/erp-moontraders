# Creditor Management System Documentation

## Overview
This document explains how to track and manage creditors (Accounts Payable) using the double-entry accounting system in MoonTrader. Creditors represent amounts owed to suppliers, vendors, and other parties.

---

## Table of Contents
1. [Understanding Creditors](#understanding-creditors)
2. [Chart of Accounts Setup](#chart-of-accounts-setup)
3. [Tracking Creditor Transactions](#tracking-creditor-transactions)
4. [Creditor Aging Reports](#creditor-aging-reports)
5. [Payment Processing](#payment-processing)
6. [Reconciliation](#reconciliation)
7. [API and Queries](#api-and-queries)

---

## 1. Understanding Creditors

### 1.1 What are Creditors?

**Creditors (Accounts Payable)** are liabilities representing money owed to:
- Suppliers for inventory purchases
- Vendors for services
- Contractors for work done
- Utility companies
- Landlords for rent
- Any party providing goods/services on credit

### 1.2 Double-Entry Principle

In double-entry accounting, every creditor transaction affects at least two accounts:

```
PURCHASE ON CREDIT:
DR: Purchases/Inventory/Expense    PKR 100,000
    CR: Accounts Payable (Creditor)              PKR 100,000

PAYMENT TO CREDITOR:
DR: Accounts Payable (Creditor)    PKR 100,000
    CR: Cash/Bank                                PKR 100,000
```

---

## 2. Chart of Accounts Setup

### 2.1 Creditor Account Structure

```
Liabilities (Account Type: Liability)
├── Current Liabilities (2000)
│   ├── Accounts Payable (2100) [Control Account]
│   │   ├── Supplier A (2101)
│   │   ├── Supplier B (2102)
│   │   ├── Vendor XYZ (2103)
│   │   ├── Utility Provider (2104)
│   │   └── ...
│   ├── Accrued Expenses (2200)
│   ├── Short-term Loans (2300)
├── Long-term Liabilities (2500)
    ├── Long-term Loans (2510)
    └── Deferred Revenue (2520)
```

### 2.2 Creating Creditor Accounts

**Option 1: Individual Creditor Accounts**
```sql
-- Each supplier/vendor gets their own account
INSERT INTO chart_of_accounts (
    account_name,
    account_code,
    account_type_id,
    parent_account_id,
    is_group,
    company_id
) VALUES (
    'ABC Suppliers Pvt. Ltd.',
    '2101',
    (SELECT id FROM account_types WHERE type_name = 'Liability'),
    (SELECT id FROM chart_of_accounts WHERE account_code = '2100'), -- Parent: Accounts Payable
    false,
    1
);
```

**Option 2: Subsidiary Ledger (Recommended for many creditors)**
```
Chart of Accounts:
└── Accounts Payable (2100) - Control Account

Separate Creditor Master Table:
├── creditors table
│   ├── id
│   ├── creditor_name
│   ├── creditor_type (supplier/vendor/contractor)
│   ├── control_account_id → chart_of_accounts.id (2100)
│   ├── contact_info
│   ├── credit_terms
│   └── credit_limit
```

For this approach, track individual creditor balances through journal entry references:
```sql
journal_entries
├── entry_date
├── reference (e.g., "Invoice INV-001 from ABC Suppliers")
├── creditor_id (foreign key)
└── journal_entry_details
    ├── account_id (2100 - Accounts Payable)
    ├── debit/credit amounts
```

---

## 3. Tracking Creditor Transactions

### 3.1 Purchase on Credit

**Scenario:** Purchased inventory worth PKR 500,000 from "XYZ Suppliers" on 30-day credit terms.

**Journal Entry:**
```sql
INSERT INTO journal_entries (
    entry_date,
    reference,
    narration,
    total_debit,
    total_credit,
    creditor_id
) VALUES (
    '2025-11-02',
    'PO-2025-045',
    'Purchase from XYZ Suppliers - Invoice INV-5432',
    500000.00,
    500000.00,
    15 -- XYZ Suppliers creditor_id
);

-- Detail #1: Debit Purchases
INSERT INTO journal_entry_details (
    journal_entry_id,
    account_id,
    debit,
    credit
) VALUES (
    LAST_INSERT_ID(),
    (SELECT id FROM chart_of_accounts WHERE account_code = '5100'), -- Purchases
    500000.00,
    0.00
);

-- Detail #2: Credit Accounts Payable
INSERT INTO journal_entry_details (
    journal_entry_id,
    account_id,
    debit,
    credit
) VALUES (
    LAST_INSERT_ID(),
    (SELECT id FROM chart_of_accounts WHERE account_code = '2100'), -- Accounts Payable
    0.00,
    500000.00
);
```

**Effect on Accounts:**
```
Purchases Account (5100)
DR: PKR 500,000

Accounts Payable (2100)
CR: PKR 500,000 ← Liability increased (we owe XYZ Suppliers)
```

### 3.2 Expense on Credit

**Scenario:** Office rent of PKR 50,000 payable to landlord.

**Journal Entry:**
```
Date: 2025-11-01
Reference: RENT-NOV-2025
Creditor: City Properties (Landlord)

DR: Rent Expense (5200)              PKR 50,000
    CR: Accounts Payable (2100)                  PKR 50,000
```

### 3.3 Service on Credit

**Scenario:** Legal consultancy fees PKR 75,000 from Law Firm ABC.

**Journal Entry:**
```
Date: 2025-11-05
Reference: LEGAL-INV-789
Creditor: Law Firm ABC

DR: Professional Fees (5900)         PKR 75,000
    CR: Accounts Payable (2100)                  PKR 75,000
```

---

## 4. Creditor Aging Reports

### 4.1 Understanding Aging

Creditor aging categorizes outstanding payables by how long they've been unpaid:
- **Current**: 0-30 days
- **31-60 days**: Slightly overdue
- **61-90 days**: Overdue
- **90+ days**: Significantly overdue

### 4.2 Aging Report SQL Query

```sql
WITH creditor_transactions AS (
    -- Get all transactions affecting Accounts Payable
    SELECT 
        je.id as journal_entry_id,
        je.entry_date,
        je.reference,
        je.creditor_id,
        c.creditor_name,
        jed.debit,
        jed.credit,
        (jed.credit - jed.debit) as outstanding_amount,
        DATEDIFF(CURDATE(), je.entry_date) as days_outstanding
    FROM journal_entries je
    JOIN journal_entry_details jed ON je.id = jed.journal_entry_id
    JOIN chart_of_accounts coa ON jed.account_id = coa.id
    LEFT JOIN creditors c ON je.creditor_id = c.id
    WHERE coa.account_code = '2100' -- Accounts Payable
      AND je.posting_status = 'posted'
),
creditor_balances AS (
    -- Calculate running balance per creditor
    SELECT 
        creditor_id,
        creditor_name,
        entry_date,
        reference,
        outstanding_amount,
        days_outstanding,
        SUM(outstanding_amount) OVER (
            PARTITION BY creditor_id 
            ORDER BY entry_date
        ) as running_balance
    FROM creditor_transactions
)
SELECT 
    creditor_name,
    reference,
    entry_date as invoice_date,
    outstanding_amount as invoice_amount,
    days_outstanding,
    CASE 
        WHEN days_outstanding <= 30 THEN 'Current'
        WHEN days_outstanding <= 60 THEN '31-60 Days'
        WHEN days_outstanding <= 90 THEN '61-90 Days'
        ELSE '90+ Days'
    END as aging_bucket,
    running_balance as current_balance
FROM creditor_balances
WHERE running_balance > 0 -- Only show unpaid invoices
ORDER BY creditor_name, entry_date;
```

### 4.3 Aging Summary by Creditor

```sql
SELECT 
    c.creditor_name,
    SUM(CASE WHEN days_outstanding <= 30 THEN outstanding ELSE 0 END) as current_0_30,
    SUM(CASE WHEN days_outstanding BETWEEN 31 AND 60 THEN outstanding ELSE 0 END) as days_31_60,
    SUM(CASE WHEN days_outstanding BETWEEN 61 AND 90 THEN outstanding ELSE 0 END) as days_61_90,
    SUM(CASE WHEN days_outstanding > 90 THEN outstanding ELSE 0 END) as days_90_plus,
    SUM(outstanding) as total_outstanding
FROM (
    SELECT 
        je.creditor_id,
        c.creditor_name,
        DATEDIFF(CURDATE(), je.entry_date) as days_outstanding,
        SUM(jed.credit - jed.debit) as outstanding
    FROM journal_entries je
    JOIN journal_entry_details jed ON je.id = jed.journal_entry_id
    JOIN chart_of_accounts coa ON jed.account_id = coa.id
    LEFT JOIN creditors c ON je.creditor_id = c.id
    WHERE coa.account_code = '2100'
    GROUP BY je.creditor_id, je.entry_date, je.id
) AS aged_payables
LEFT JOIN creditors c ON aged_payables.creditor_id = c.id
GROUP BY c.creditor_name
HAVING total_outstanding > 0
ORDER BY total_outstanding DESC;
```

**Sample Output:**
```
Creditor Name          | 0-30 Days | 31-60   | 61-90   | 90+     | Total
-----------------------|-----------|---------|---------|---------|----------
ABC Suppliers          | 500,000   | 250,000 | 0       | 0       | 750,000
XYZ Vendors            | 300,000   | 0       | 100,000 | 50,000  | 450,000
City Properties        | 50,000    | 0       | 0       | 0       | 50,000
```

### 4.4 Total Accounts Payable Balance

```sql
SELECT 
    SUM(jed.credit - jed.debit) as total_accounts_payable
FROM journal_entry_details jed
JOIN chart_of_accounts coa ON jed.account_id = coa.id
WHERE coa.account_code = '2100'
  AND jed.journal_entry_id IN (
      SELECT id FROM journal_entries WHERE posting_status = 'posted'
  );
```

---

## 5. Payment Processing

### 5.1 Full Payment to Creditor

**Scenario:** Paying PKR 500,000 to XYZ Suppliers via bank transfer.

**Journal Entry:**
```sql
Date: 2025-11-15
Reference: PAYMENT-XYZ-001
Creditor: XYZ Suppliers

DR: Accounts Payable (2100)          PKR 500,000
    CR: Bank Account (1200)                      PKR 500,000
```

**Implementation:**
```php
// Create payment journal entry
$journalEntry = JournalEntry::create([
    'entry_date' => '2025-11-15',
    'reference' => 'PAYMENT-XYZ-001',
    'narration' => 'Payment to XYZ Suppliers via bank transfer',
    'creditor_id' => 15,
    'total_debit' => 500000,
    'total_credit' => 500000
]);

// Debit Accounts Payable (reduces liability)
JournalEntryDetail::create([
    'journal_entry_id' => $journalEntry->id,
    'account_id' => $accountsPayableAccount->id,
    'debit' => 500000,
    'credit' => 0
]);

// Credit Bank Account (reduces cash)
JournalEntryDetail::create([
    'journal_entry_id' => $journalEntry->id,
    'account_id' => $bankAccount->id,
    'debit' => 0,
    'credit' => 500000
]);
```

### 5.2 Partial Payment

**Scenario:** Paying PKR 200,000 out of PKR 500,000 owed.

**Journal Entry:**
```
Date: 2025-11-15
Reference: PARTIAL-PAYMENT-XYZ-001

DR: Accounts Payable (2100)          PKR 200,000
    CR: Bank Account (1200)                      PKR 200,000
```

**Remaining Balance:** PKR 300,000 still owed to XYZ Suppliers

### 5.3 Payment with Discount

**Scenario:** Supplier offers 2% early payment discount. Original amount: PKR 500,000.

**Journal Entry:**
```
Date: 2025-11-10
Reference: PAYMENT-XYZ-002-DISCOUNT

DR: Accounts Payable (2100)          PKR 500,000
    CR: Bank Account (1200)                      PKR 490,000
    CR: Purchase Discount (4200)                 PKR 10,000
```

### 5.4 Payment via Cheque

**Scenario:** Issued cheque to creditor, cheque not yet cleared.

**Step 1: Issue Cheque**
```
Date: 2025-11-15
Reference: CHQ-123456

DR: Accounts Payable (2100)          PKR 500,000
    CR: Cheques Issued (2150)                    PKR 500,000
```

**Step 2: Cheque Cleared**
```
Date: 2025-11-18
Reference: CHQ-123456-CLEARED

DR: Cheques Issued (2150)            PKR 500,000
    CR: Bank Account (1200)                      PKR 500,000
```

---

## 6. Reconciliation

### 6.1 Supplier Statement Reconciliation

**Purpose:** Match internal records with supplier's statement.

**Process:**
1. Get supplier statement
2. Query all transactions for that creditor
3. Compare balances
4. Identify discrepancies

**Query to Get Creditor Transaction History:**
```sql
SELECT 
    je.entry_date,
    je.reference,
    je.narration,
    jed.debit as payment,
    jed.credit as purchase,
    @running_balance := @running_balance + (jed.credit - jed.debit) as balance
FROM journal_entries je
JOIN journal_entry_details jed ON je.id = jed.journal_entry_id
JOIN chart_of_accounts coa ON jed.account_id = coa.id
CROSS JOIN (SELECT @running_balance := 0) vars
WHERE coa.account_code = '2100'
  AND je.creditor_id = 15 -- XYZ Suppliers
ORDER BY je.entry_date, je.id;
```

**Sample Output:**
```
Date       | Reference    | Payment   | Purchase | Balance
-----------|--------------|-----------|----------|----------
2025-10-01 | PO-2025-040  | 0         | 500,000  | 500,000
2025-10-15 | PAYMENT-001  | 200,000   | 0        | 300,000
2025-11-01 | PO-2025-045  | 0         | 250,000  | 550,000
2025-11-10 | PAYMENT-002  | 550,000   | 0        | 0
```

### 6.2 Common Reconciliation Issues

**Issue 1: Supplier shows higher balance**
- **Cause:** Purchase invoice not recorded in our system
- **Resolution:** Create missing journal entry

**Issue 2: Our balance higher than supplier's**
- **Cause:** Payment made but not applied to account
- **Resolution:** Verify payment reference, update if needed

**Issue 3: Timing differences**
- **Cause:** Cheque issued but not yet cleared by supplier
- **Resolution:** Normal, track via "Cheques Issued" account

---

## 7. API and Queries

### 7.1 Get All Creditors with Outstanding Balances

```php
// CreditorController.php
public function getOutstandingCreditors()
{
    $creditors = DB::select("
        SELECT 
            c.id,
            c.creditor_name,
            c.contact_email,
            c.contact_phone,
            SUM(jed.credit - jed.debit) as outstanding_balance,
            MIN(je.entry_date) as oldest_invoice_date,
            MAX(je.entry_date) as latest_invoice_date,
            COUNT(DISTINCT je.id) as transaction_count
        FROM creditors c
        JOIN journal_entries je ON c.id = je.creditor_id
        JOIN journal_entry_details jed ON je.id = jed.journal_entry_id
        JOIN chart_of_accounts coa ON jed.account_id = coa.id
        WHERE coa.account_code = '2100'
          AND je.posting_status = 'posted'
        GROUP BY c.id
        HAVING outstanding_balance > 0
        ORDER BY outstanding_balance DESC
    ");
    
    return response()->json($creditors);
}
```

### 7.2 Get Creditor Statement

```php
public function getCreditorStatement($creditorId)
{
    $statement = DB::select("
        SELECT 
            je.entry_date,
            je.reference,
            je.narration,
            jed.debit as payment,
            jed.credit as purchase,
            @balance := @balance + (jed.credit - jed.debit) as balance
        FROM journal_entries je
        JOIN journal_entry_details jed ON je.id = jed.journal_entry_id
        JOIN chart_of_accounts coa ON jed.account_id = coa.id
        CROSS JOIN (SELECT @balance := 0) vars
        WHERE coa.account_code = '2100'
          AND je.creditor_id = ?
          AND je.posting_status = 'posted'
        ORDER BY je.entry_date, je.id
    ", [$creditorId]);
    
    return response()->json($statement);
}
```

### 7.3 Record Payment to Creditor

```php
public function recordPayment(Request $request)
{
    DB::transaction(function() use ($request) {
        // Create journal entry
        $journalEntry = JournalEntry::create([
            'entry_date' => $request->payment_date,
            'reference' => $request->reference,
            'narration' => $request->narration,
            'creditor_id' => $request->creditor_id,
            'total_debit' => $request->amount,
            'total_credit' => $request->amount,
            'posting_status' => 'posted'
        ]);
        
        // Debit Accounts Payable
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => ChartOfAccount::where('account_code', '2100')->first()->id,
            'debit' => $request->amount,
            'credit' => 0
        ]);
        
        // Credit Bank/Cash
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $request->payment_account_id,
            'debit' => 0,
            'credit' => $request->amount
        ]);
        
        // If cheque payment, create cheque record
        if ($request->payment_method === 'cheque') {
            Payment::create([
                'payment_date' => $request->payment_date,
                'amount' => $request->amount,
                'payment_method' => 'cheque',
                'cheque_number' => $request->cheque_number,
                'cheque_bank' => $request->cheque_bank,
                'cheque_date' => $request->cheque_date,
                'cheque_status' => 'pending',
                'journal_entry_id' => $journalEntry->id
            ]);
        }
    });
    
    return response()->json(['message' => 'Payment recorded successfully']);
}
```

### 7.4 Dashboard: Total Payables Summary

```sql
-- Total Accounts Payable
SELECT SUM(credit - debit) as total_payables
FROM journal_entry_details
WHERE account_id IN (
    SELECT id FROM chart_of_accounts WHERE account_code = '2100'
);

-- By Aging Bucket
SELECT 
    SUM(CASE WHEN days_outstanding <= 30 THEN balance ELSE 0 END) as current,
    SUM(CASE WHEN days_outstanding BETWEEN 31 AND 60 THEN balance ELSE 0 END) as overdue_30,
    SUM(CASE WHEN days_outstanding BETWEEN 61 AND 90 THEN balance ELSE 0 END) as overdue_60,
    SUM(CASE WHEN days_outstanding > 90 THEN balance ELSE 0 END) as overdue_90_plus,
    SUM(balance) as total
FROM (
    SELECT 
        DATEDIFF(CURDATE(), je.entry_date) as days_outstanding,
        SUM(jed.credit - jed.debit) as balance
    FROM journal_entries je
    JOIN journal_entry_details jed ON je.id = jed.journal_entry_id
    WHERE jed.account_id IN (SELECT id FROM chart_of_accounts WHERE account_code = '2100')
    GROUP BY je.id
    HAVING balance > 0
) aged;
```

---

## 8. Best Practices

### 8.1 Regular Reconciliation
- Reconcile creditor statements monthly
- Match invoices to payments
- Investigate discrepancies promptly

### 8.2 Payment Terms Management
- Track credit terms per creditor (30, 60, 90 days)
- Set up payment reminders
- Take advantage of early payment discounts

### 8.3 Credit Limit Management
```sql
-- Check if creditor is within credit limit
SELECT 
    c.creditor_name,
    c.credit_limit,
    COALESCE(SUM(jed.credit - jed.debit), 0) as current_balance,
    c.credit_limit - COALESCE(SUM(jed.credit - jed.debit), 0) as available_credit
FROM creditors c
LEFT JOIN journal_entries je ON c.id = je.creditor_id
LEFT JOIN journal_entry_details jed ON je.id = jed.journal_entry_id
WHERE jed.account_id IN (SELECT id FROM chart_of_accounts WHERE account_code = '2100')
GROUP BY c.id
HAVING current_balance > c.credit_limit;
```

### 8.4 Audit Trail
- Every payment must reference original invoice
- Maintain complete transaction history
- Use soft deletes, never hard delete

### 8.5 Cash Flow Management
```sql
-- Upcoming payments due (next 30 days)
SELECT 
    c.creditor_name,
    je.entry_date as invoice_date,
    je.reference,
    c.credit_terms_days,
    DATE_ADD(je.entry_date, INTERVAL c.credit_terms_days DAY) as due_date,
    SUM(jed.credit - jed.debit) as amount_due
FROM creditors c
JOIN journal_entries je ON c.id = je.creditor_id
JOIN journal_entry_details jed ON je.id = jed.journal_entry_id
WHERE jed.account_id IN (SELECT id FROM chart_of_accounts WHERE account_code = '2100')
  AND DATE_ADD(je.entry_date, INTERVAL c.credit_terms_days DAY) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
GROUP BY c.id, je.id
HAVING amount_due > 0
ORDER BY due_date;
```

---

## 9. Integration with Other Modules

### 9.1 Purchase Orders
When purchase order received:
```
DR: Inventory/Purchases
    CR: Accounts Payable (Creditor)
```

### 9.2 Expense Bookings
When expense incurred:
```
DR: Expense Account
    CR: Accounts Payable (Creditor)
```

### 9.3 Employee Salaries
If salary not paid immediately:
```
DR: Salary Expense
    CR: Salaries Payable (subset of Accounts Payable)
```

---

## 10. Reports

### 10.1 Key Reports

1. **Creditor Aging Report** - Show outstanding balances by age
2. **Creditor Statement** - Transaction history for specific creditor
3. **Accounts Payable Summary** - Total payables at a glance
4. **Payment Schedule** - Upcoming payments due
5. **Overdue Payables** - Payments past due date
6. **Creditor Analysis** - Purchase volume, payment behavior
7. **Cash Flow Forecast** - Projected payments

### 10.2 Export Formats
- PDF for printing/emailing
- Excel for analysis
- CSV for import into other systems
- JSON for API integration

---

## Conclusion

The creditor management system leverages the double-entry accounting framework to provide real-time visibility into:
- Who you owe money to
- How much you owe
- When payments are due
- Complete transaction history
- Aging analysis for better cash flow management

By maintaining accurate creditor records through journal entries, the system ensures financial integrity and enables informed decision-making.

For detailed transaction examples and technical implementation, refer to the main ACCOUNTING_USAGE_GUIDE.md documentation.
