# Employee Salary Module - How It Works

## Overview
A double-entry accounting module for tracking all employee-related financial transactions. Designed to prevent mistakes by requiring explicit POST approval before transactions hit the General Ledger.

## Key Concept: Two-Step Process

### ❌ OLD WAY (Complex & Risky)
Transaction created → Automatically posts to GL → Hard to undo mistakes

### ✅ NEW WAY (Simple & Safe)
1. **Create Transaction** (Status: Pending) → Review it
2. **Click POST button** → Now it hits GL (Status: Paid)

**Why?** You asked: *"make sure yeh itna complex nhi ho easy hoo aur is mein post ka option hona chieyay tak k directly post nhi ho"* - This prevents accidental GL entries!

---

## Module Structure

### 1. Employee Salaries (Salary Structure)
Defines the salary structure for each employee.

**Fields:**
- Employee, Supplier (auto-filled from employee)
- Basic Salary, Allowances, Deductions
- Net Salary (auto-calculated = basic + allowances - deductions)
- Effective From/To dates
- Active status

**Usage:**
- Create salary record for each employee
- Update when salary changes
- Track salary history with effective dates

---

### 2. Employee Salary Transactions (Ledger)
The main ledger tracking all money given to or recovered from employees.

**16 Transaction Types:**

| Type | Debit (Employee Gets) | Credit (Deducted From Employee) |
|------|-----|-------|
| **Salary** | ✓ Net salary | |
| **SalaryPayment** | | ✓ Amount paid |
| **Advance** | ✓ Advance given | |
| **AdvanceRecovery** | | ✓ Amount recovered |
| **Bonus** | ✓ Bonus amount | |
| **Incentive** | ✓ Incentive amount | |
| **OvertimePay** | ✓ Overtime amount | |
| **Loan** | ✓ Loan amount | |
| **LoanRecovery** | | ✓ Installment |
| **Deduction** | | ✓ Deduction amount |
| **FineDeduction** | | ✓ Fine amount |
| **Expense** | ✓ Expense amount | |
| **ExpenseReimbursement** | | ✓ Reimbursed |
| **Shortage** | ✓ Shortage amount | |
| **ShortageRecovery** | | ✓ Recovered |
| **Adjustment** | Either | Either |

---

## How To Use

### Creating a Transaction (Example: January Salary)

1. Go to **Employee Salary Transactions** → **Add Transaction**
2. Fill in:
   - Employee: Select employee (supplier auto-fills)
   - Transaction Date: 2026-01-31
   - Transaction Type: **Salary**
   - Salary Month: "January 2026"
   - Debit: 50,000 (amount employee receives)
   - Credit: 0
   - **Debit Account**: Select GL account (e.g., "Salary Expense")
   - **Credit Account**: Select GL account (e.g., "Cash" or "Bank")
   - Status: **Pending** (default)
3. Click **Create Transaction**

**Result:** Transaction is saved but NOT posted to GL yet!

---

### Posting to GL (Making it Official)

**Option 1: From Index Page**
1. Go to **Employee Salary Transactions**
2. Find your transaction (Status: Pending)
3. Click the **POST button** (blue checkmark icon)
4. Confirm

**Option 2: From Show/Detail Page**
1. Open the transaction
2. Click **Post Transaction** button at bottom
3. Confirm

**What Happens:**
- Status changes: Pending → **Paid**
- Journal Entry created in GL with 2 lines:
  - DR: Debit Account (50,000)
  - CR: Credit Account (50,000)
- Transaction is now **locked** (can't edit or delete)

---

## Important Rules

### ✅ CAN DO:
- Create transactions (always Pending)
- Edit Pending transactions
- Delete Pending transactions
- Post Pending or Approved transactions

### ❌ CANNOT DO:
- Edit Paid transactions (already in GL)
- Delete Paid transactions
- Post without GL accounts selected
- Post Cancelled transactions

---

## Workflow Examples

### Example 1: Monthly Salary
```
1. Create: Type=Salary, Debit=50,000, Status=Pending
2. Review: Check all details are correct
3. POST: Status=Paid, GL entry created
```

### Example 2: Advance + Recovery
```
Day 1: Give Advance
- Create: Type=Advance, Debit=10,000, Status=Pending
- POST: Status=Paid (Employee owes you 10,000)

Day 15: Deduct from Salary
- Create: Type=AdvanceRecovery, Credit=10,000, Status=Pending
- POST: Status=Paid (Recovery complete)
```

### Example 3: Made a Mistake
```
1. Create transaction with wrong amount
2. Notice error BEFORE posting
3. Edit transaction → Fix amount
4. POST when correct

OR if already posted:
1. Cannot edit Paid transaction
2. Create Adjustment transaction with opposite entry
3. POST the adjustment
```

---

## Balance Calculation

Each transaction shows a **Balance**:
- **Positive (Red)** = Employee owes you money (advances, loans not recovered)
- **Negative (Green)** = You owe employee money (salary not paid yet)
- **Balance = Debit - Credit**

---

## Permissions

| Permission | Who Has It | What It Does |
|------------|------------|--------------|
| `employee-salary-transaction-list` | All staff | View transactions |
| `employee-salary-transaction-create` | Accountant | Create transactions |
| `employee-salary-transaction-edit` | Accountant | Edit Pending transactions |
| `employee-salary-transaction-post` | Accountant | POST to GL |
| `employee-salary-transaction-delete` | Super Admin | Delete Pending transactions |

---

## GL Integration

When you POST a transaction, it creates a journal entry via `AccountingService`:

```
Entry Date: Transaction Date
Reference: Transaction Reference Number
Type: EmployeeSalaryTransaction
Status: Posted (auto_post: true)

Lines:
  1. DR Salary Expense    50,000
  2. CR Cash              50,000
```

The journal entry is **automatically posted** to GL when you click POST.

---

## Database Tables

### `employee_salaries`
- Salary structure records
- One active record per employee (usually)

### `employee_salary_transactions`
- All transactions (ledger)
- Links to `journal_entries` when posted

---

## Routes

```
GET    /employee-salaries              - List salary structures
POST   /employee-salaries              - Create salary structure
GET    /employee-salaries/{id}         - View salary structure
PUT    /employee-salaries/{id}         - Update salary structure
DELETE /employee-salaries/{id}         - Delete salary structure

GET    /employee-salary-transactions   - List transactions
POST   /employee-salary-transactions   - Create transaction
GET    /employee-salary-transactions/{id} - View transaction
PUT    /employee-salary-transactions/{id} - Update transaction
DELETE /employee-salary-transactions/{id} - Delete transaction
POST   /employee-salary-transactions/{id}/post - POST to GL ⭐
```

---

## Tips

1. **Always review before POST** - Once posted, you can't edit!
2. **Use Pending status** - Create transactions, review at month end, then bulk POST
3. **Check GL accounts** - Must select both debit and credit accounts before POST
4. **Reference numbers** - Use consistent format like "SAL-2026-001"
5. **Salary month** - Always fill this for easy reporting
6. **Balance report** - Filter by employee to see total outstanding balance

---

## Future Reporting Ideas

You mentioned using this for "full month settlements analysis". Here's what you can build:

### Monthly Salary Report
- Filter: Salary Month = "January 2026"
- Group by Employee
- Show: Basic, Advances, Recoveries, Net Paid

### Employee Outstanding Balance
- Filter: Status = Paid
- Group by Employee
- Sum: Debit - Credit per employee
- Shows who owes what

### Settlement Analysis
- Link `sales_settlement_id` to track salary vs sales performance
- Compare employee settlements with their salary costs

---

## Need Help?

- View existing Claim Registers module - similar pattern
- Check `app/Services/SalaryService.php` - all logic is here
- Tests in `tests/Feature/EmployeeSalaryTransactionTest.php` - shows all scenarios

---

**Remember:** Simple is better. Create → Review → POST. That's it! 🎉