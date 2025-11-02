# Accounting Transactions API Documentation

## Overview
This API provides transactional endpoints for double-entry accounting operations with automatic rollback on errors. All operations are wrapped in database transactions for data integrity.

**Base URL:** `/api/transactions`

**Response Format:**
```json
{
    "success": true|false,
    "message": "Description of result",
    "data": { /* Result object */ }
}
```

**Error Response:**
```json
{
    "success": false,
    "message": "Error description",
    "errors": { /* Validation errors if applicable */ }
}
```

---

## Endpoints

### 1. Create Journal Entry
**Endpoint:** `POST /api/transactions/journal-entry`

**Description:** Create a general journal entry with multiple debit/credit lines.

**Request Body:**
```json
{
    "entry_date": "2025-01-15",
    "reference": "JV-001",
    "description": "January 2025 salary payment",
    "currency_id": 1,
    "fx_rate": 1.0,
    "cost_center_id": null,
    "auto_post": true,
    "lines": [
        {
            "account_id": 58,
            "debit": 50000,
            "credit": 0,
            "description": "Salary expense",
            "cost_center_id": null
        },
        {
            "account_id": 7,
            "debit": 0,
            "credit": 50000,
            "description": "Cash payment",
            "cost_center_id": null
        }
    ]
}
```

**Validations:**
- `entry_date`: Required, valid date
- `reference`: Optional, max 50 characters
- `description`: Required, max 500 characters
- `currency_id`: Optional, must exist in currencies table
- `fx_rate`: Optional, numeric, min 0.000001
- `cost_center_id`: Optional, must exist in cost_centers table
- `auto_post`: Optional, boolean (default: false)
- `lines`: Required, array, min 2 lines
- `lines.*.account_id`: Required, must exist and be a leaf account
- `lines.*.debit`: Required, numeric, min 0
- `lines.*.credit`: Required, numeric, min 0
- `lines.*.description`: Optional, max 255 characters
- **Business Rule:** Total debits MUST equal total credits
- **Business Rule:** Each line must have debit OR credit (not both, not neither)

**Success Response (201):**
```json
{
    "success": true,
    "message": "Journal entry created and posted successfully",
    "data": {
        "id": 123,
        "entry_date": "2025-01-15",
        "reference": "JV-001",
        "status": "posted",
        "accounting_period_id": 1,
        "lines": [...]
    }
}
```

**cURL Example:**
```bash
curl -X POST http://localhost/api/transactions/journal-entry \
  -H "Content-Type: application/json" \
  -d '{
    "entry_date": "2025-01-15",
    "description": "Salary payment",
    "auto_post": true,
    "lines": [
        {"account_id": 58, "debit": 50000, "credit": 0},
        {"account_id": 7, "debit": 0, "credit": 50000}
    ]
}'
```

---

### 2. Post Journal Entry
**Endpoint:** `POST /api/transactions/{id}/post`

**Description:** Change a draft journal entry status to "posted". Posted entries become immutable.

**Path Parameters:**
- `id`: Journal entry ID

**Success Response (200):**
```json
{
    "success": true,
    "message": "Journal entry posted successfully",
    "data": {
        "id": 123,
        "status": "posted",
        "updated_at": "2025-01-15 10:30:00"
    }
}
```

**cURL Example:**
```bash
curl -X POST http://localhost/api/transactions/123/post
```

---

### 3. Reverse Journal Entry
**Endpoint:** `POST /api/transactions/{id}/reverse`

**Description:** Create a reversing entry for corrections. Debits become credits and vice versa.

**Path Parameters:**
- `id`: Original journal entry ID (must be posted)

**Request Body:**
```json
{
    "description": "Reversing entry for incorrect salary posting"
}
```

**Success Response (201):**
```json
{
    "success": true,
    "message": "Reversing entry created successfully",
    "data": {
        "id": 124,
        "entry_date": "2025-01-15",
        "reference": "REV-JV-001",
        "status": "draft",
        "original_entry_id": 123
    }
}
```

**cURL Example:**
```bash
curl -X POST http://localhost/api/transactions/123/reverse \
  -H "Content-Type: application/json" \
  -d '{"description": "Reversing entry for correction"}'
```

---

### 4. Record Opening Balance
**Endpoint:** `POST /api/transactions/opening-balance`

**Description:** Quick helper to record opening balance (owner capital).

**Request Body:**
```json
{
    "amount": 500000,
    "description": "Opening balance - Initial capital",
    "entry_date": "2025-01-01",
    "reference": "OB-001",
    "auto_post": true
}
```

**Validations:**
- `amount`: Required, numeric, min 0.01
- `description`: Optional, max 500 characters (default: "Opening balance - Owner capital")
- `entry_date`: Optional, valid date (default: today)
- `reference`: Optional, max 50 characters
- `auto_post`: Optional, boolean (default: true)

**What it does:**
```
Dr. Cash (Account #7)              ₨500,000
    Cr. Capital Stock (Account #29)           ₨500,000
```

**Success Response (201):**
```json
{
    "success": true,
    "message": "Opening balance recorded successfully",
    "data": {
        "id": 125,
        "amount": 500000,
        "status": "posted"
    }
}
```

**cURL Example:**
```bash
curl -X POST http://localhost/api/transactions/opening-balance \
  -H "Content-Type: application/json" \
  -d '{"amount": 500000, "auto_post": true}'
```

---

### 5. Record Cash Receipt
**Endpoint:** `POST /api/transactions/cash-receipt`

**Description:** Record cash received for revenue (e.g., service income, sales).

**Request Body:**
```json
{
    "amount": 100000,
    "revenue_account_id": 67,
    "description": "Service income - Web development project",
    "reference": "INV-001",
    "cost_center_id": null,
    "auto_post": true
}
```

**Validations:**
- `amount`: Required, numeric, min 0.01
- `revenue_account_id`: Required, must exist in chart_of_accounts
- `description`: Required, max 500 characters
- `reference`: Optional, max 50 characters
- `cost_center_id`: Optional, must exist in cost_centers
- `auto_post`: Optional, boolean (default: true)

**What it does:**
```
Dr. Cash (Account #7)              ₨100,000
    Cr. Service (Account #67)                 ₨100,000
```

**Success Response (201):**
```json
{
    "success": true,
    "message": "Cash receipt recorded successfully",
    "data": {
        "id": 126,
        "amount": 100000,
        "status": "posted"
    }
}
```

**cURL Example:**
```bash
curl -X POST http://localhost/api/transactions/cash-receipt \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 100000,
    "revenue_account_id": 67,
    "description": "Web development service",
    "auto_post": true
}'
```

---

### 6. Record Cash Payment
**Endpoint:** `POST /api/transactions/cash-payment`

**Description:** Record cash paid for expenses (e.g., rent, utilities, supplies).

**Request Body:**
```json
{
    "amount": 25000,
    "expense_account_id": 54,
    "description": "Monthly office rent",
    "reference": "RENT-JAN-2025",
    "cost_center_id": null,
    "auto_post": true
}
```

**Validations:**
- `amount`: Required, numeric, min 0.01
- `expense_account_id`: Required, must exist in chart_of_accounts
- `description`: Required, max 500 characters
- `reference`: Optional, max 50 characters
- `cost_center_id`: Optional, must exist in cost_centers
- `auto_post`: Optional, boolean (default: true)

**What it does:**
```
Dr. Office Rent (Account #54)      ₨25,000
    Cr. Cash (Account #7)                     ₨25,000
```

**Success Response (201):**
```json
{
    "success": true,
    "message": "Cash payment recorded successfully",
    "data": {
        "id": 127,
        "amount": 25000,
        "status": "posted"
    }
}
```

**cURL Example:**
```bash
curl -X POST http://localhost/api/transactions/cash-payment \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 25000,
    "expense_account_id": 54,
    "description": "Office rent payment",
    "auto_post": true
}'
```

---

### 7. Record Credit Sale
**Endpoint:** `POST /api/transactions/credit-sale`

**Description:** Record revenue on account (accounts receivable - customer owes us).

**Request Body:**
```json
{
    "amount": 150000,
    "revenue_account_id": 66,
    "customer_reference": "CUST-001 - ABC Company",
    "description": "Product sale on credit - Invoice #INV-202501",
    "reference": "INV-202501",
    "cost_center_id": null,
    "auto_post": true
}
```

**Validations:**
- `amount`: Required, numeric, min 0.01
- `revenue_account_id`: Required, must exist in chart_of_accounts
- `customer_reference`: Required, max 255 characters
- `description`: Required, max 500 characters
- `reference`: Optional, max 50 characters
- `cost_center_id`: Optional, must exist in cost_centers
- `auto_post`: Optional, boolean (default: true)

**What it does:**
```
Dr. Debtors (Account #4)           ₨150,000
    Cr. Sales (Account #66)                   ₨150,000
```

**Success Response (201):**
```json
{
    "success": true,
    "message": "Credit sale recorded successfully",
    "data": {
        "id": 128,
        "amount": 150000,
        "customer": "CUST-001 - ABC Company",
        "status": "posted"
    }
}
```

**cURL Example:**
```bash
curl -X POST http://localhost/api/transactions/credit-sale \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 150000,
    "revenue_account_id": 66,
    "customer_reference": "ABC Company",
    "description": "Product sale on credit",
    "auto_post": true
}'
```

---

### 8. Record Payment Received
**Endpoint:** `POST /api/transactions/payment-received`

**Description:** Record customer payment (reduces accounts receivable).

**Request Body:**
```json
{
    "amount": 150000,
    "customer_reference": "CUST-001 - ABC Company",
    "reference": "RCPT-001",
    "invoice_ref": "INV-202501",
    "auto_post": true
}
```

**Validations:**
- `amount`: Required, numeric, min 0.01
- `customer_reference`: Required, max 255 characters
- `reference`: Optional, max 50 characters
- `invoice_ref`: Optional, max 50 characters
- `auto_post`: Optional, boolean (default: true)

**What it does:**
```
Dr. Cash (Account #7)              ₨150,000
    Cr. Debtors (Account #4)                  ₨150,000
```

**Success Response (201):**
```json
{
    "success": true,
    "message": "Payment received recorded successfully",
    "data": {
        "id": 129,
        "amount": 150000,
        "customer": "CUST-001 - ABC Company",
        "status": "posted"
    }
}
```

**cURL Example:**
```bash
curl -X POST http://localhost/api/transactions/payment-received \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 150000,
    "customer_reference": "ABC Company",
    "reference": "RCPT-001",
    "auto_post": true
}'
```

---

## Common Account IDs Reference

These are the default account IDs from the seeder. Query your database for actual IDs:

```sql
SELECT id, account_code, account_name, account_type_id FROM chart_of_accounts ORDER BY account_code;
```

| ID | Code | Account Name | Type | Usage |
|----|------|--------------|------|-------|
| 7 | 1131 | Cash | Asset | Cash receipts/payments |
| 5 | 1120 | Bank Accounts | Asset | Bank transactions |
| 4 | 1111 | Debtors | Asset | Credit sales/payments (A/R) |
| 29 | 3100 | Capital Stock | Equity | Opening balance |
| 67 | 4120 | Service | Revenue | Service income |
| 66 | 4110 | Sales | Revenue | Product sales |
| 54 | 52130 | Office Rent | Expense | Rent payments |
| 58 | 52170 | Salary | Expense | Salary payments |
| 62 | 52210 | Utility Expenses | Expense | Utility bills |

---

## Transaction Safety

### Automatic Rollback
All operations use `DB::transaction()`:
- Any error triggers automatic rollback
- No partial data is saved
- Database constraints are checked
- Posted entries are protected by triggers

### Database Constraints
1. **Debit XOR Credit**: Each line must have debit OR credit (not both)
2. **Balance Check**: Total debits MUST equal total credits
3. **Leaf Accounts Only**: Can only post to leaf accounts (no parent accounts)
4. **Immutability**: Posted entries cannot be updated/deleted
5. **Period Auto-Set**: Accounting period automatically set from entry_date
6. **Positive Values**: Foreign exchange rate must be positive

### Error Handling
```json
{
    "success": false,
    "message": "Entry does not balance. Debits: 100000, Credits: 95000"
}
```

Common errors:
- `Entry does not balance`
- `Cannot update posted entry` (from database trigger)
- `Account is not a leaf account`
- `Line X must have debit OR credit, not both`
- `Accounting period not found for date`

---

## Testing Examples

### Complete Accounting Cycle

```bash
# 1. Record opening balance (₨500,000)
curl -X POST http://localhost/api/transactions/opening-balance \
  -H "Content-Type: application/json" \
  -d '{"amount": 500000, "auto_post": true}'

# 2. Record service income (₨100,000)
curl -X POST http://localhost/api/transactions/cash-receipt \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 100000,
    "revenue_account_id": 67,
    "description": "Web development service",
    "auto_post": true
}'

# 3. Record rent payment (₨25,000)
curl -X POST http://localhost/api/transactions/cash-payment \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 25000,
    "expense_account_id": 54,
    "description": "Office rent - January 2025",
    "auto_post": true
}'

# 4. Record credit sale (₨150,000)
curl -X POST http://localhost/api/transactions/credit-sale \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 150000,
    "revenue_account_id": 66,
    "customer_reference": "ABC Company",
    "description": "Product sale on credit",
    "auto_post": true
}'

# 5. Record customer payment (₨150,000)
curl -X POST http://localhost/api/transactions/payment-received \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 150000,
    "customer_reference": "ABC Company",
    "auto_post": true
}'
```

After these transactions:
- **Cash Balance**: ₨500,000 + ₨100,000 - ₨25,000 + ₨150,000 = **₨725,000**
- **Total Revenue**: ₨100,000 + ₨150,000 = **₨250,000**
- **Total Expenses**: ₨25,000 = **₨25,000**
- **Net Income**: ₨250,000 - ₨25,000 = **₨225,000**

---

## Postman Collection

Import this collection for easy testing:

```json
{
  "info": {
    "name": "Accounting Transactions API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Opening Balance",
      "request": {
        "method": "POST",
        "url": "{{base_url}}/api/transactions/opening-balance",
        "body": {
          "mode": "raw",
          "raw": "{\"amount\": 500000, \"auto_post\": true}"
        }
      }
    },
    {
      "name": "Cash Receipt",
      "request": {
        "method": "POST",
        "url": "{{base_url}}/api/transactions/cash-receipt",
        "body": {
          "mode": "raw",
          "raw": "{\"amount\": 100000, \"revenue_account_id\": 67, \"description\": \"Service income\", \"auto_post\": true}"
        }
      }
    }
  ],
  "variable": [
    {
      "key": "base_url",
      "value": "http://localhost"
    }
  ]
}
```

---

## Production Checklist

Before deploying to production:

- [ ] Enable authentication middleware on routes
- [ ] Add rate limiting to prevent abuse
- [ ] Set up proper error logging (already integrated)
- [ ] Configure CORS headers for frontend
- [ ] Add API versioning (e.g., `/api/v1/transactions`)
- [ ] Implement soft deletes for journal_entries
- [ ] Add user_id tracking for audit trail
- [ ] Set up backup automation for accounting data
- [ ] Configure database connection pooling
- [ ] Test all edge cases with QA team

---

## Support

For issues or questions:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Review database constraints: `SHOW TRIGGERS` and `SHOW CREATE TABLE`
3. Verify trial balance: `SELECT * FROM trial_balance_view`
4. Test balance sheet: `SELECT * FROM balance_sheet_view`

**System Requirements:**
- Laravel 12.x
- MariaDB 10.4+ or MySQL 8.0+
- PHP 8.2+
