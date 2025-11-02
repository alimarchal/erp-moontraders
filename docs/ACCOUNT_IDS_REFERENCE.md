# Account IDs Quick Reference

**Generated from your database on October 31, 2025**

Use these **ID numbers** (not codes) when creating journal entries.

---

## ⚠️ IMPORTANT: Use ID, Not Code!

```php
// ❌ WRONG - This will fail with foreign key error
'account_id' => 1131  // This is the CODE, not the ID

// ✅ CORRECT - Use the database ID
'account_id' => 7     // This is the ID (which has code 1131)
```

---

## 📊 Common Accounts

### Assets (Type 1)
| ID | Code | Account Name |
|----|------|--------------|
| **7** | **1131** | **Cash** ← Most used |
| 4 | 1111 | Debtors (Accounts Receivable) |
| 5 | 1120 | Bank Accounts |
| 9 | 1141 | Employee Advances |
| 13 | 1161 | Stock In Hand (Inventory) |
| 22 | 1270 | Office Equipments |

### Liabilities (Type 2)
| ID | Code | Account Name |
|----|------|--------------|
| 72 | 2111 | Creditors (Accounts Payable) |
| 73 | 2112 | Payroll Payable |
| 78 | 2132 | Secured Loans |
| 79 | 2133 | Unsecured Loans |

### Equity (Type 3)
| ID | Code | Account Name |
|----|------|--------------|
| **29** | **3100** | **Capital Stock** ← For opening balance |
| 30 | 3200 | Dividends Paid |
| 32 | 3400 | Retained Earnings |

### Revenue/Income (Type 4)
| ID | Code | Account Name |
|----|------|--------------|
| **66** | **4110** | **Sales** |
| **67** | **4120** | **Service** ← Most used |

### Expenses (Type 5)
| ID | Code | Account Name |
|----|------|--------------|
| 37 | 5111 | Cost of Goods Sold |
| **54** | **52130** | **Office Rent** |
| **58** | **52170** | **Salary** |
| 60 | 52190 | Telephone Expenses |
| **62** | **52210** | **Utility Expenses** |
| 44 | 5230 | Depreciation |

---

## 🔥 Quick Copy-Paste Examples

### Opening Balance (₨500,000)
```php
'lines' => [
    ['account_id' => 7, 'debit' => 500000, 'credit' => 0],   // Cash
    ['account_id' => 29, 'debit' => 0, 'credit' => 500000],  // Capital Stock
]
```

### Cash Receipt - Service Income (₨100,000)
```php
'lines' => [
    ['account_id' => 7, 'debit' => 100000, 'credit' => 0],   // Cash
    ['account_id' => 67, 'debit' => 0, 'credit' => 100000],  // Service
]
```

### Cash Payment - Rent (₨25,000)
```php
'lines' => [
    ['account_id' => 54, 'debit' => 25000, 'credit' => 0],   // Office Rent
    ['account_id' => 7, 'debit' => 0, 'credit' => 25000],    // Cash
]
```

### Credit Sale (₨150,000)
```php
'lines' => [
    ['account_id' => 4, 'debit' => 150000, 'credit' => 0],   // Debtors
    ['account_id' => 66, 'debit' => 0, 'credit' => 150000],  // Sales
]
```

### Payment Received from Customer (₨150,000)
```php
'lines' => [
    ['account_id' => 7, 'debit' => 150000, 'credit' => 0],   // Cash
    ['account_id' => 4, 'debit' => 0, 'credit' => 150000],   // Debtors
]
```

### Salary Payment (₨50,000)
```php
'lines' => [
    ['account_id' => 58, 'debit' => 50000, 'credit' => 0],   // Salary
    ['account_id' => 7, 'debit' => 0, 'credit' => 50000],    // Cash
]
```

---

## 📋 Full Account List

To see ALL accounts in your system:

```bash
mysql -u root moontrader -e "SELECT id, account_code, account_name FROM chart_of_accounts ORDER BY account_code;"
```

Or in Laravel:

```php
DB::table('chart_of_accounts')
    ->select('id', 'account_code', 'account_name')
    ->orderBy('account_code')
    ->get();
```

---

## 🛠️ Using in AccountingService

The `AccountingService` already uses the correct IDs:

```php
// Opening balance
$accountingService->recordOpeningBalance(500000);
// Uses: Cash (ID 7) and Capital Stock (ID 29)

// Cash receipt
$accountingService->recordCashReceipt(100000, 67, 'Service income');
// Uses: Cash (ID 7) and Service (ID 67)

// Cash payment
$accountingService->recordCashPayment(25000, 54, 'Rent payment');
// Uses: Office Rent (ID 54) and Cash (ID 7)

// Credit sale
$accountingService->recordCreditSale(150000, 66, 'ABC Company', 'Product sale');
// Uses: Debtors (ID 4) and Sales (ID 66)

// Payment received
$accountingService->recordPaymentReceived(150000, 'ABC Company');
// Uses: Cash (ID 7) and Debtors (ID 4)
```

---

## ✅ Testing Your Setup

Run this to verify your accounts exist:

```bash
cd /Users/alirazamarchal/Herd/moontrader
php artisan tinker
```

Then:

```php
// Check Cash account
\DB::table('chart_of_accounts')->where('id', 7)->first();
// Should show: id=7, code=1131, name=Cash

// Check Capital Stock
\DB::table('chart_of_accounts')->where('id', 29)->first();
// Should show: id=29, code=3100, name=Capital Stock

// Check Service
\DB::table('chart_of_accounts')->where('id', 67)->first();
// Should show: id=67, code=4120, name=Service
```

---

## 🚨 Common Mistakes

### ❌ Using Code Instead of ID
```php
// WRONG - Will cause foreign key error
'account_id' => 1131  // This is a code, not an ID
```

### ✅ Correct Usage
```php
// CORRECT - Use the actual database ID
'account_id' => 7  // ID for Cash (which has code 1131)
```

---

**Remember:** Always use the **ID column**, not the **account_code column**!
