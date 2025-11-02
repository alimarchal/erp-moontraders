# Inventory & Sales - Double-Entry Accounting Integration

## Overview
All inventory, sales, and operational transactions are fully integrated with the double-entry accounting system. Each business transaction automatically generates corresponding journal entries.

## Database Schema Alignment

### Key Integration Fields

All transactional tables include:
- `journal_entry_id`: Links to the accounting journal entry
- `posting_status`: Tracks whether transaction is posted to accounting (draft/posted/cancelled)
- Appropriate indexes for reporting queries

### Transactional Tables

#### 1. Stock Receipts (Purchases from Suppliers)
**Table**: `stock_receipts`
**Accounting Entry** (When Posted):
```
Debit:  Inventory (Asset)                 $X,XXX.XX
Credit: Accounts Payable (Liability)      $X,XXX.XX
```

**Fields**:
- `journal_entry_id` → links to journal_entries
- `posting_status` → draft/posted/cancelled
- `supplier_id` → links to companies (supplier)
- `total_amount` → total purchase cost

**Line Items**: `stock_receipt_items`
- `unit_price` → purchase cost per unit (used for inventory valuation)
- `total_amount` → quantity × unit_price

---

#### 2. Sales/Deliveries to Customers
**Table**: `sales`
**Accounting Entry** (When Posted):
```
For Cash Sale:
  Debit:  Cash (Asset)                      $X,XXX.XX
  Credit: Sales Revenue (Income)            $X,XXX.XX
  Debit:  Cost of Goods Sold (Expense)      $X,XXX.XX
  Credit: Inventory (Asset)                 $X,XXX.XX

For Credit Sale:
  Debit:  Accounts Receivable (Asset)       $X,XXX.XX
  Credit: Sales Revenue (Income)            $X,XXX.XX
  Debit:  Cost of Goods Sold (Expense)      $X,XXX.XX
  Credit: Inventory (Asset)                 $X,XXX.XX
```

**Fields**:
- `journal_entry_id` → links to journal_entries
- `posting_status` → draft/posted/cancelled
- `customer_id` → links to customers
- `total_amount` → total sales revenue
- `cost_of_goods_sold` → total COGS for this sale
- `payment_type` → cash/credit (determines AR vs Cash debit)
- `payment_status` → unpaid/partial/paid

**Line Items**: `sale_items`
- `unit_price` → selling price (revenue)
- `total_amount` → quantity × unit_price (revenue)
- `unit_cost` → cost at time of sale (for COGS)
- `total_cost` → quantity × unit_cost (COGS)

---

#### 3. Customer Payments
**Table**: `payments`
**Accounting Entry** (When Posted):
```
Debit:  Cash/Bank Account (Asset)          $X,XXX.XX
Credit: Accounts Receivable (Asset)        $X,XXX.XX
```

**Fields**:
- `journal_entry_id` → links to journal_entries
- `posting_status` → draft/posted/cancelled
- `payment_method` → determines which cash/bank account to debit
- `amount` → payment received

---

#### 4. Vehicle Expenses
**Table**: `vehicle_expenses`
**Accounting Entry** (When Posted):
```
Debit:  Vehicle Expense - [Type] (Expense) $X,XXX.XX
Credit: Cash/Bank (Asset)                  $X,XXX.XX
```

**Expense Types**:
- Fuel → Vehicle Fuel Expense
- Toll → Toll Expense
- Tax → Vehicle Tax Expense
- Maintenance → Vehicle Maintenance Expense
- Misc → Miscellaneous Vehicle Expense

**Fields**:
- `journal_entry_id` → links to journal_entries
- `posting_status` → draft/posted/cancelled
- `expense_type` → determines which expense account to debit

---

### Product Configuration for Accounting

**Table**: `products`

**Account Mappings** (per product):
- `inventory_account_id` → Asset: Inventory - [Product Category]
- `cogs_account_id` → Expense: Cost of Goods Sold - [Product Category]
- `sales_revenue_account_id` → Income: Sales Revenue - [Product Category]

**Costing Method**:
- `valuation_method` → FIFO/LIFO/Average/Standard
- `cost_price` → Current average/standard cost

This allows different product categories to post to different GL accounts automatically.

---

### Customer & Supplier Accounting

**Customers** (`customers` table):
- `receivable_account_id` → Sub-account under Accounts Receivable for this customer
- `current_balance` → Outstanding AR balance (updated by sales & payments)

**Suppliers** (`companies` table with supplier role):
- `default_payable_account_id` → Sub-account under Accounts Payable for this supplier
- Multiple default accounts for different transaction types

---

### Perpetual Inventory Tracking

**Table**: `inventory_transactions`

Maintains running balance of inventory quantities and values per product per warehouse.

**Transaction Types**:
- `receipt` → Stock received from supplier
- `sale` → Stock sold to customer
- `adjustment` → Manual inventory adjustment
- `transfer_in` → Stock transferred from another warehouse
- `transfer_out` → Stock transferred to another warehouse

**Fields**:
- `quantity_in` → Units added
- `quantity_out` → Units removed
- `balance` → Running quantity balance
- `unit_cost` → Cost per unit for this transaction
- `total_cost` → Cost value of this transaction
- `balance_value` → Running inventory value
- `transactionable_type/id` → Polymorphic link to source (StockReceipt, Sale, etc.)
- `journal_entry_id` → Links to accounting entry

**Indexes for Reporting**:
- Product + Warehouse + Date (for inventory reports by location)
- Transaction Type + Date (for movement analysis)
- Source document lookup (transactionable)

---

## Accounting Workflow

### 1. Stock Receipt Workflow
1. Create stock receipt in 'draft' status
2. Add line items with quantities and purchase costs
3. Calculate total_amount
4. When ready: **Post to Accounting**
   - Create journal entry with status='Posted'
   - Create journal_entry_details:
     - Debit: Product's inventory_account_id (per line item)
     - Credit: Supplier's default_payable_account_id
   - Create inventory_transactions records (type='receipt')
   - Update product.cost_price (if using Average costing)
   - Set stock_receipt.posting_status = 'posted'
   - Link stock_receipt.journal_entry_id

### 2. Sales Workflow
1. Create sale in 'draft' status
2. Add sale_items with quantities and selling prices
3. Calculate each line's cost (unit_cost, total_cost) based on inventory valuation method
4. Calculate sale totals: total_amount (revenue), cost_of_goods_sold (COGS)
5. When ready: **Post to Accounting**
   - Create journal entry
   - Create journal_entry_details:
     - Debit: Cash or Customer's receivable_account_id (based on payment_type)
     - Credit: Product's sales_revenue_account_id (per line item)
     - Debit: Product's cogs_account_id (per line item)
     - Credit: Product's inventory_account_id (per line item)
   - Create inventory_transactions records (type='sale')
   - Update customer.current_balance (if credit sale)
   - Set sales.posting_status = 'posted'
   - Link sales.journal_entry_id

### 3. Payment Receipt Workflow
1. Create payment in 'draft' status
2. Link to sale and customer
3. Specify payment_method (determines cash/bank account)
4. When ready: **Post to Accounting**
   - Create journal entry
   - Create journal_entry_details:
     - Debit: Cash/Bank account (based on payment_method)
     - Credit: Customer's receivable_account_id
   - Update sale.paid_amount and balance_amount
   - Update sale.payment_status
   - Update customer.current_balance
   - Set payment.posting_status = 'posted'
   - Link payment.journal_entry_id

### 4. Vehicle Expense Workflow
1. Create vehicle_expense in 'draft' status
2. Select expense_type (determines GL account)
3. When ready: **Post to Accounting**
   - Create journal entry
   - Create journal_entry_details:
     - Debit: Expense account (based on expense_type)
     - Credit: Cash/Bank account
   - Set vehicle_expense.posting_status = 'posted'
   - Link vehicle_expense.journal_entry_id

---

## Reporting Capabilities

### Financial Reports
All standard financial reports work automatically:
- **Income Statement** → Sales Revenue, COGS, Vehicle Expenses
- **Balance Sheet** → Inventory, AR, AP, Cash
- **Cash Flow Statement** → All cash movements
- **Trial Balance** → Complete double-entry verification

### Operational Reports
- **Inventory Valuation** → From inventory_transactions (balance_value)
- **Stock Movement** → From inventory_transactions (by type, date, warehouse)
- **Customer Aging** → From sales + payments (unpaid balances by date)
- **Supplier Payables** → From stock_receipts (unpaid balances)
- **Gross Profit Analysis** → sales.total_amount - sales.cost_of_goods_sold
- **Vehicle Expense Analysis** → By vehicle, by type, by date
- **Sales by Customer** → Grouped sales data
- **Purchase by Supplier** → Grouped stock receipt data

### Inventory Reports
- **Stock Ledger** → inventory_transactions (complete movement history)
- **Stock Balance** → Current balance per product per warehouse
- **Inventory Value** → balance_value from inventory_transactions
- **Slow Moving** → Products with low sale frequency
- **Reorder Report** → Products below reorder_level

### Audit Trail
- Every transaction links to journal_entry_id
- posting_status tracks lifecycle (draft → posted → cancelled)
- All tables use soft deletes
- activity_log tracks user actions
- Timestamps on all records

---

## Chart of Accounts Structure

### Recommended Account Setup

**Assets**
- 1100 - Cash and Bank Accounts
  - 1110 - Cash in Hand
  - 1120 - Bank Account - [Bank Name]
- 1200 - Accounts Receivable
  - 1210 - AR - [Customer Name] (created per customer)
- 1300 - Inventory
  - 1310 - Inventory - [Product Category]

**Liabilities**
- 2100 - Accounts Payable
  - 2110 - AP - [Supplier Name] (created per supplier)

**Income**
- 4100 - Sales Revenue
  - 4110 - Sales Revenue - [Product Category]

**Expenses**
- 5100 - Cost of Goods Sold
  - 5110 - COGS - [Product Category]
- 5200 - Operating Expenses
  - 5210 - Vehicle Fuel Expense
  - 5211 - Toll Expense
  - 5212 - Vehicle Tax Expense
  - 5213 - Vehicle Maintenance Expense
  - 5214 - Miscellaneous Vehicle Expense

---

## Implementation Notes

### Automatic vs Manual Posting
- **Draft Mode**: Transactions saved but not affecting accounting
- **Post Button**: Triggers journal entry creation and account updates
- **Cancel/Void**: Reverses journal entry (or creates reversal entry)

### Cost Calculation Methods
Products can use different valuation methods:
- **FIFO**: First-in, First-out
- **LIFO**: Last-in, First-out
- **Average**: Weighted average cost
- **Standard**: Fixed standard cost

### Multi-Warehouse Support
- inventory_transactions tracks per-warehouse balances
- Transfers between warehouses create paired transactions
- Reports can filter by warehouse

### Currency Support
- Journal entries use currency_id
- Support for multi-currency transactions
- Exchange rate handling in journal_entry_details

---

## Database Constraints & Triggers

### Existing Double-Entry Constraints
(From migration: 2025_10_30_180910_add_double_entry_constraints_to_journal_entries.php)
- Journal entries must balance (SUM(debit) = SUM(credit))
- Status transitions validated
- Date constraints enforced

### Recommended Additional Triggers

**On Stock Receipt Posting**:
```sql
- Validate supplier exists and is active
- Validate warehouse exists and is active
- Validate products exist and are active
- Calculate total_amount from line items
- Prevent editing after posting
```

**On Sale Posting**:
```sql
- Validate customer exists and is active
- Check credit limit (if credit sale)
- Validate sufficient inventory in warehouse
- Calculate COGS based on valuation method
- Update inventory_transactions
- Prevent editing after posting
```

**On Payment Posting**:
```sql
- Validate payment <= sale balance
- Update sale payment_status
- Update customer current_balance
- Prevent overpayment
```

---

## Testing Scenarios

### 1. Complete Sales Cycle
1. Receive stock from supplier (10 units @ $50 = $500)
2. Sell to customer (5 units @ $80 = $400 revenue, $250 COGS)
3. Receive payment from customer ($400)
4. Verify:
   - Inventory value = $250 (5 units @ $50)
   - Customer balance = $0
   - Gross profit = $150 ($400 - $250)
   - All journal entries balance

### 2. Multi-Product Sale
1. Sale with 3 different products
2. Each product uses different GL accounts
3. Verify journal entry has correct number of lines
4. Verify each product's inventory updated

### 3. Credit Sale with Partial Payments
1. Credit sale $1,000
2. Customer pays $400
3. Customer pays $600
4. Verify payment_status transitions: unpaid → partial → paid
5. Verify customer balance updates correctly

### 4. Vehicle Expense Types
1. Create fuel expense
2. Create toll expense
3. Create maintenance expense
4. Verify each posts to correct expense account
5. Verify cash/bank reduced correctly

---

## Migration Order

Migrations are ordered to satisfy foreign key dependencies:
1. Core accounting (chart_of_accounts, journal_entries, etc.)
2. Companies, warehouses
3. Employees, vehicles
4. Products (with account mappings)
5. Customers (with receivable accounts)
6. Stock receipts and items
7. Sales and items
8. Payments
9. Vehicle expenses
10. Inventory transactions

All foreign keys properly constrained with appropriate cascade/restrict rules.

---

## Next Steps

### Phase 1: Model Relationships
- Add relationships to all models
- Define fillable/guarded properties
- Add casting for enums and decimals

### Phase 2: Business Logic
- Create service classes for posting logic
- Implement COGS calculation methods
- Build inventory transaction triggers

### Phase 3: Controllers & Views
- CRUD interfaces for all entities
- Post/Cancel buttons on transactions
- Real-time inventory checks

### Phase 4: Reporting
- Build query classes for reports
- Create report views
- Export to PDF/Excel

### Phase 5: Testing
- Unit tests for accounting logic
- Feature tests for workflows
- Integration tests for double-entry validation
