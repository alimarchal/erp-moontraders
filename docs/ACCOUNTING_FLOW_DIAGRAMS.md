# Double-Entry Accounting Flow Diagrams

## Transaction Flow: Stock Receipt (Purchase from Supplier)

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. STOCK RECEIPT CREATED (Draft)                                │
├─────────────────────────────────────────────────────────────────┤
│ • receipt_number: SR-001                                         │
│ • supplier_id: Company ABC                                       │
│ • warehouse_id: Main Warehouse                                   │
│ • delivery_note_number: DN-12345                                 │
│ • received_date: 2025-11-02                                      │
│ • posting_status: 'draft'                                        │
│ • journal_entry_id: NULL                                         │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. ADD LINE ITEMS                                                │
├─────────────────────────────────────────────────────────────────┤
│ Product A: 100 units @ $50 = $5,000                             │
│ Product B: 50 units @ $80 = $4,000                              │
│ TOTAL: $9,000                                                    │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. POST TO ACCOUNTING (Button Click)                            │
└─────────────────────────────────────────────────────────────────┘
                              ↓
        ┌────────────────────────────────────┐
        │ Journal Entry Created               │
        │ ID: 123                             │
        │ Date: 2025-11-02                    │
        │ Reference: SR-001                   │
        │ Status: Posted                      │
        └────────────────────────────────────┘
                     ↓
        ┌────────────────────────────────────┐
        │ Journal Entry Details               │
        ├────────────────────────────────────┤
        │ Line 1:                             │
        │   Account: Inventory - Product A    │
        │   Debit: $5,000                     │
        │   Credit: $0                        │
        ├────────────────────────────────────┤
        │ Line 2:                             │
        │   Account: Inventory - Product B    │
        │   Debit: $4,000                     │
        │   Credit: $0                        │
        ├────────────────────────────────────┤
        │ Line 3:                             │
        │   Account: AP - Company ABC         │
        │   Debit: $0                         │
        │   Credit: $9,000                    │
        ├────────────────────────────────────┤
        │ TOTALS: Debit $9,000 = Credit $9,000│
        └────────────────────────────────────┘
                     ↓
        ┌────────────────────────────────────┐
        │ Inventory Transactions Created      │
        ├────────────────────────────────────┤
        │ Product A @ Main Warehouse:         │
        │   type: 'receipt'                   │
        │   quantity_in: 100                  │
        │   unit_cost: $50                    │
        │   balance: 100 (new)                │
        │   balance_value: $5,000             │
        │   journal_entry_id: 123             │
        ├────────────────────────────────────┤
        │ Product B @ Main Warehouse:         │
        │   type: 'receipt'                   │
        │   quantity_in: 50                   │
        │   unit_cost: $80                    │
        │   balance: 50 (new)                 │
        │   balance_value: $4,000             │
        │   journal_entry_id: 123             │
        └────────────────────────────────────┘
                     ↓
        ┌────────────────────────────────────┐
        │ Update Product Cost (Average)       │
        ├────────────────────────────────────┤
        │ Product A: cost_price = $50         │
        │ Product B: cost_price = $80         │
        └────────────────────────────────────┘
                     ↓
        ┌────────────────────────────────────┐
        │ Update Stock Receipt                │
        ├────────────────────────────────────┤
        │ posting_status: 'posted'            │
        │ journal_entry_id: 123               │
        └────────────────────────────────────┘

RESULT:
✅ Inventory increased by $9,000 (Asset)
✅ Accounts Payable increased by $9,000 (Liability)
✅ Balance Sheet equation maintained: Assets = Liabilities
✅ Inventory quantities updated in inventory_transactions
```

---

## Transaction Flow: Cash Sale to Customer

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. SALE CREATED (Draft)                                          │
├─────────────────────────────────────────────────────────────────┤
│ • sale_number: S-001                                             │
│ • customer_id: John's Retail Store                              │
│ • warehouse_id: Main Warehouse                                   │
│ • vehicle_id: V-001                                              │
│ • employee_id: Driver Ali                                        │
│ • sale_date: 2025-11-02                                          │
│ • payment_type: 'cash'                                           │
│ • posting_status: 'draft'                                        │
│ • journal_entry_id: NULL                                         │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. ADD LINE ITEMS & CALCULATE COGS                              │
├─────────────────────────────────────────────────────────────────┤
│ Product A: 20 units @ $75 selling = $1,500 revenue             │
│            cost: $50/unit = $1,000 COGS                         │
│                                                                  │
│ Product B: 10 units @ $120 selling = $1,200 revenue            │
│            cost: $80/unit = $800 COGS                           │
│                                                                  │
│ TOTALS:                                                          │
│   total_amount: $2,700 (Revenue)                                │
│   cost_of_goods_sold: $1,800 (COGS)                            │
│   Gross Profit: $900                                            │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. POST TO ACCOUNTING (Button Click)                            │
└─────────────────────────────────────────────────────────────────┘
                              ↓
        ┌────────────────────────────────────┐
        │ Journal Entry Created               │
        │ ID: 124                             │
        │ Date: 2025-11-02                    │
        │ Reference: S-001                    │
        │ Status: Posted                      │
        └────────────────────────────────────┘
                     ↓
        ┌────────────────────────────────────┐
        │ Journal Entry Details (4 Lines)     │
        ├────────────────────────────────────┤
        │ Line 1: REVENUE RECOGNITION         │
        │   Account: Cash                     │
        │   Debit: $2,700                     │
        │   Credit: $0                        │
        ├────────────────────────────────────┤
        │ Line 2:                             │
        │   Account: Sales Revenue            │
        │   Debit: $0                         │
        │   Credit: $2,700                    │
        ├────────────────────────────────────┤
        │ Line 3: COGS RECOGNITION            │
        │   Account: Cost of Goods Sold       │
        │   Debit: $1,800                     │
        │   Credit: $0                        │
        ├────────────────────────────────────┤
        │ Line 4:                             │
        │   Account: Inventory                │
        │   Debit: $0                         │
        │   Credit: $1,800                    │
        ├────────────────────────────────────┤
        │ TOTALS: Debit $4,500 = Credit $4,500│
        └────────────────────────────────────┘
                     ↓
        ┌────────────────────────────────────┐
        │ Inventory Transactions Created      │
        ├────────────────────────────────────┤
        │ Product A @ Main Warehouse:         │
        │   type: 'sale'                      │
        │   quantity_out: 20                  │
        │   unit_cost: $50                    │
        │   balance: 80 (100 - 20)           │
        │   balance_value: $4,000 ($5k - $1k)│
        │   journal_entry_id: 124             │
        ├────────────────────────────────────┤
        │ Product B @ Main Warehouse:         │
        │   type: 'sale'                      │
        │   quantity_out: 10                  │
        │   unit_cost: $80                    │
        │   balance: 40 (50 - 10)            │
        │   balance_value: $3,200 ($4k - $800)│
        │   journal_entry_id: 124             │
        └────────────────────────────────────┘
                     ↓
        ┌────────────────────────────────────┐
        │ Update Sale                         │
        ├────────────────────────────────────┤
        │ posting_status: 'posted'            │
        │ payment_status: 'paid'              │
        │ journal_entry_id: 124               │
        └────────────────────────────────────┘

RESULT:
✅ Cash increased by $2,700 (Asset)
✅ Inventory decreased by $1,800 (Asset)
✅ Sales Revenue increased by $2,700 (Income)
✅ COGS increased by $1,800 (Expense)
✅ Net Income = $2,700 - $1,800 = $900 (Gross Profit)
✅ Inventory quantities reduced in inventory_transactions
```

---

## Transaction Flow: Credit Sale to Customer

```
┌─────────────────────────────────────────────────────────────────┐
│ CREDIT SALE (payment_type: 'credit')                            │
│ Same as cash sale, but Line 1 changes:                          │
├─────────────────────────────────────────────────────────────────┤
│ Line 1:                                                          │
│   Account: AR - John's Retail Store (from customer)            │
│   Debit: $2,700                                                 │
│   Credit: $0                                                     │
└─────────────────────────────────────────────────────────────────┘
                              ↓
        ┌────────────────────────────────────┐
        │ Update Customer                     │
        ├────────────────────────────────────┤
        │ current_balance += $2,700           │
        │ (Outstanding receivable)            │
        └────────────────────────────────────┘
                     ↓
        ┌────────────────────────────────────┐
        │ Update Sale                         │
        ├────────────────────────────────────┤
        │ payment_status: 'unpaid'            │
        │ balance_amount: $2,700              │
        └────────────────────────────────────┘

RESULT:
✅ Accounts Receivable increased by $2,700 (Asset)
✅ Customer owes $2,700
✅ Payment can be collected later
```

---

## Transaction Flow: Customer Payment (on Credit Sale)

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. PAYMENT CREATED (Draft)                                       │
├─────────────────────────────────────────────────────────────────┤
│ • payment_number: PAY-001                                        │
│ • sale_id: S-001                                                 │
│ • customer_id: John's Retail Store                              │
│ • payment_date: 2025-11-05                                       │
│ • amount: $2,700                                                 │
│ • payment_method: 'cash'                                         │
│ • posting_status: 'draft'                                        │
│ • journal_entry_id: NULL                                         │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. POST TO ACCOUNTING (Button Click)                            │
└─────────────────────────────────────────────────────────────────┘
                              ↓
        ┌────────────────────────────────────┐
        │ Journal Entry Created               │
        │ ID: 125                             │
        │ Date: 2025-11-05                    │
        │ Reference: PAY-001                  │
        │ Status: Posted                      │
        └────────────────────────────────────┘
                     ↓
        ┌────────────────────────────────────┐
        │ Journal Entry Details               │
        ├────────────────────────────────────┤
        │ Line 1:                             │
        │   Account: Cash                     │
        │   Debit: $2,700                     │
        │   Credit: $0                        │
        ├────────────────────────────────────┤
        │ Line 2:                             │
        │   Account: AR - John's Retail       │
        │   Debit: $0                         │
        │   Credit: $2,700                    │
        ├────────────────────────────────────┤
        │ TOTALS: Debit $2,700 = Credit $2,700│
        └────────────────────────────────────┘
                     ↓
        ┌────────────────────────────────────┐
        │ Update Sale                         │
        ├────────────────────────────────────┤
        │ paid_amount: $2,700                 │
        │ balance_amount: $0                  │
        │ payment_status: 'paid'              │
        └────────────────────────────────────┘
                     ↓
        ┌────────────────────────────────────┐
        │ Update Customer                     │
        ├────────────────────────────────────┤
        │ current_balance -= $2,700           │
        │ (AR reduced to $0)                  │
        └────────────────────────────────────┘
                     ↓
        ┌────────────────────────────────────┐
        │ Update Payment                      │
        ├────────────────────────────────────┤
        │ posting_status: 'posted'            │
        │ journal_entry_id: 125               │
        └────────────────────────────────────┘

RESULT:
✅ Cash increased by $2,700 (Asset)
✅ Accounts Receivable decreased by $2,700 (Asset)
✅ Customer balance cleared
✅ Asset conversion: AR → Cash (no impact on income)
```

---

## Transaction Flow: Vehicle Fuel Expense

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. VEHICLE EXPENSE CREATED (Draft)                              │
├─────────────────────────────────────────────────────────────────┤
│ • vehicle_id: V-001                                              │
│ • employee_id: Driver Ali                                        │
│ • expense_date: 2025-11-02                                       │
│ • expense_type: 'fuel'                                           │
│ • amount: $150                                                   │
│ • odometer_reading: 12,500 km                                    │
│ • receipt_number: FUEL-123                                       │
│ • posting_status: 'draft'                                        │
│ • journal_entry_id: NULL                                         │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. POST TO ACCOUNTING (Button Click)                            │
└─────────────────────────────────────────────────────────────────┘
                              ↓
        ┌────────────────────────────────────┐
        │ Journal Entry Created               │
        │ ID: 126                             │
        │ Date: 2025-11-02                    │
        │ Reference: FUEL-123                 │
        │ Status: Posted                      │
        └────────────────────────────────────┘
                     ↓
        ┌────────────────────────────────────┐
        │ Journal Entry Details               │
        ├────────────────────────────────────┤
        │ Line 1:                             │
        │   Account: Vehicle Fuel Expense     │
        │   Debit: $150                       │
        │   Credit: $0                        │
        ├────────────────────────────────────┤
        │ Line 2:                             │
        │   Account: Cash                     │
        │   Debit: $0                         │
        │   Credit: $150                      │
        ├────────────────────────────────────┤
        │ TOTALS: Debit $150 = Credit $150    │
        └────────────────────────────────────┘
                     ↓
        ┌────────────────────────────────────┐
        │ Update Vehicle Expense              │
        ├────────────────────────────────────┤
        │ posting_status: 'posted'            │
        │ journal_entry_id: 126               │
        └────────────────────────────────────┘

RESULT:
✅ Vehicle Fuel Expense increased by $150 (Expense)
✅ Cash decreased by $150 (Asset)
✅ Operating expense recorded
✅ Reduces net income by $150
```

---

## Complete Accounting Equation Impact

### After All Above Transactions:

**ASSETS**
```
Cash:                 $2,700 (from sale) - $150 (fuel) = +$2,550
Inventory:            +$9,000 (purchases) - $1,800 (COGS) = +$7,200
Total Assets:         +$9,750
```

**LIABILITIES**
```
Accounts Payable:     +$9,000 (supplier debt)
Total Liabilities:    +$9,000
```

**EQUITY (via Income Statement)**
```
Sales Revenue:        +$2,700
Cost of Goods Sold:   -$1,800
Gross Profit:         +$900

Operating Expenses:
  Vehicle Fuel:       -$150
  
Net Income:           +$750 (increases Retained Earnings/Equity)
```

**ACCOUNTING EQUATION VERIFICATION:**
```
Assets = Liabilities + Equity
$9,750 = $9,000 + $750
✅ BALANCED
```

---

## Inventory Movement Tracking

### Product A - Main Warehouse

```
Date       | Type    | Qty In | Qty Out | Balance | Unit Cost | Balance Value
-----------|---------|--------|---------|---------|-----------|---------------
2025-11-02 | receipt |   100  |    0    |   100   |   $50     |   $5,000
2025-11-02 | sale    |    0   |   20    |    80   |   $50     |   $4,000
```

### Product B - Main Warehouse

```
Date       | Type    | Qty In | Qty Out | Balance | Unit Cost | Balance Value
-----------|---------|--------|---------|---------|-----------|---------------
2025-11-02 | receipt |    50  |    0    |    50   |   $80     |   $4,000
2025-11-02 | sale    |    0   |   10    |    40   |   $80     |   $3,200
```

**Total Inventory Value: $7,200** (matches asset account balance)

---

## Reporting Queries

### Income Statement (P&L)
```sql
SELECT 
    coa.account_name,
    SUM(jed.credit - jed.debit) as amount
FROM journal_entry_details jed
JOIN chart_of_accounts coa ON jed.chart_of_account_id = coa.id
JOIN account_types at ON coa.account_type_id = at.id
WHERE at.type_name IN ('Income', 'Expense')
    AND je.entry_date BETWEEN '2025-11-01' AND '2025-11-30'
GROUP BY coa.account_name
ORDER BY at.type_name, coa.account_code;
```

### Balance Sheet
```sql
SELECT 
    coa.account_name,
    SUM(jed.debit - jed.credit) as balance
FROM journal_entry_details jed
JOIN chart_of_accounts coa ON jed.chart_of_account_id = coa.id
JOIN account_types at ON coa.account_type_id = at.id
WHERE at.type_name IN ('Asset', 'Liability', 'Equity')
GROUP BY coa.account_name
ORDER BY at.type_name, coa.account_code;
```

### Inventory Balance by Warehouse
```sql
SELECT 
    w.warehouse_name,
    p.product_name,
    it.balance as quantity,
    it.balance_value as value,
    it.transaction_date
FROM inventory_transactions it
JOIN products p ON it.product_id = p.id
JOIN warehouses w ON it.warehouse_id = w.id
WHERE it.id IN (
    SELECT MAX(id) 
    FROM inventory_transactions 
    GROUP BY product_id, warehouse_id
)
ORDER BY w.warehouse_name, p.product_name;
```

### Customer Aging Report
```sql
SELECT 
    c.customer_name,
    s.sale_number,
    s.sale_date,
    s.total_amount,
    s.paid_amount,
    s.balance_amount,
    DATEDIFF(CURRENT_DATE, s.sale_date) as days_outstanding
FROM sales s
JOIN customers c ON s.customer_id = c.id
WHERE s.payment_status != 'paid'
    AND s.posting_status = 'posted'
ORDER BY days_outstanding DESC;
```

---

## Key Benefits of This Design

1. ✅ **Complete Double-Entry Integrity**: Every business transaction creates balanced journal entries
2. ✅ **Audit Trail**: Every record links back to journal_entry_id
3. ✅ **Perpetual Inventory**: Real-time inventory balances and values
4. ✅ **COGS Tracking**: Accurate cost tracking for profitability analysis
5. ✅ **Financial Reporting**: Standard reports work automatically
6. ✅ **Operational Reporting**: Business-specific reports available
7. ✅ **Multi-Warehouse**: Inventory tracked by location
8. ✅ **Customer/Supplier Tracking**: Sub-accounts for detailed AR/AP
9. ✅ **Flexible Product Costing**: Support for FIFO, LIFO, Average, Standard
10. ✅ **Draft/Post Workflow**: Safe data entry before committing to accounting
