# Quick Reference Guide - Delivery & Creditor Systems

## 🚚 Vehicle Delivery System

### Key Tables
```
delivery_notes
├── delivery_note_number (DN-2025-001)
├── vehicle_id, driver_id
├── status: draft/in_transit/delivered/partially_delivered/returned/cancelled
├── source_warehouse_id, transit_warehouse_id
└── journal_entry_id, posting_status

delivery_note_items
├── loaded_qty, delivered_qty, returned_qty
├── cost_per_unit, cogs_amount
└── sale_item_id (reference)

warehouses (enhanced)
├── is_transit_warehouse (boolean)
└── linked_vehicle_id
```

### Workflow

```
CREATE → DISPATCH → IN_TRANSIT → DELIVER → POST
draft     draft→in_transit  monitoring  in_transit→delivered  delivered→posted
```

### Stock Movement

```
Physical Warehouse (100 units)
        ↓ [Load 20 units]
Transit Warehouse / Vehicle (20 units)
        ↓ [Deliver 18, Return 2]
Customer (18 units delivered)
Physical Warehouse (82 units) ← 2 returned
```

### Journal Entry (On Delivery)

```
DR: Cost of Goods Sold (5000)         PKR [COGS]
    CR: Transit Warehouse Inv (1420)           PKR [COGS]
```

---

## 💰 Creditor Management

### How To Track Creditors

**Creditors = Accounts Payable (Account Code: 2100)**

Track through `journal_entries` table with `creditor_id` field.

### Purchase on Credit

```
DR: Purchases (5100)                  PKR 500,000
    CR: Accounts Payable (2100)                   PKR 500,000
    [creditor_id = 15]
```

### Pay Creditor

```
DR: Accounts Payable (2100)           PKR 500,000
    CR: Bank Account (1200)                       PKR 500,000
    [creditor_id = 15]
```

### Get Outstanding Balance

```sql
SELECT 
    c.creditor_name,
    SUM(jed.credit - jed.debit) as outstanding
FROM journal_entries je
JOIN journal_entry_details jed ON je.id = jed.journal_entry_id
LEFT JOIN creditors c ON je.creditor_id = c.id
WHERE jed.account_id = (SELECT id FROM chart_of_accounts WHERE account_code = '2100')
  AND je.posting_status = 'posted'
GROUP BY je.creditor_id
HAVING outstanding > 0;
```

### Aging Report

```
0-30 days    : Current
31-60 days   : Slightly overdue
61-90 days   : Overdue
90+ days     : Significantly overdue
```

---

## 📊 Common Reports

### In-Transit Inventory

```sql
SELECT 
    p.product_name,
    v.vehicle_number,
    SUM(it.balance) as qty_in_transit,
    SUM(it.balance_value) as value
FROM inventory_transactions it
JOIN warehouses w ON it.warehouse_id = w.id
JOIN vehicles v ON w.linked_vehicle_id = v.id
JOIN products p ON it.product_id = p.id
WHERE w.is_transit_warehouse = true
  AND it.balance > 0
GROUP BY p.id, v.id;
```

### Vehicle Performance

```sql
SELECT 
    v.vehicle_number,
    COUNT(dn.id) as total_deliveries,
    SUM(dn.total_amount) as revenue,
    AVG(TIMESTAMPDIFF(HOUR, dn.departure_time, dn.actual_arrival)) as avg_hours
FROM vehicles v
JOIN delivery_notes dn ON v.id = dn.vehicle_id
WHERE dn.delivery_date BETWEEN '2025-01-01' AND '2025-12-31'
GROUP BY v.id;
```

### Creditor Aging

```sql
SELECT 
    c.creditor_name,
    SUM(CASE WHEN days <= 30 THEN amt ELSE 0 END) as current,
    SUM(CASE WHEN days BETWEEN 31 AND 60 THEN amt ELSE 0 END) as days_31_60,
    SUM(CASE WHEN days > 90 THEN amt ELSE 0 END) as days_90_plus,
    SUM(amt) as total
FROM (
    SELECT 
        je.creditor_id,
        DATEDIFF(CURDATE(), je.entry_date) as days,
        SUM(jed.credit - jed.debit) as amt
    FROM journal_entries je
    JOIN journal_entry_details jed ON je.id = jed.journal_entry_id
    WHERE jed.account_id = (SELECT id FROM chart_of_accounts WHERE account_code = '2100')
    GROUP BY je.id
) aged
LEFT JOIN creditors c ON aged.creditor_id = c.id
GROUP BY c.id
HAVING total > 0;
```

---

## 🔑 API Endpoints

### Delivery Notes

```
POST   /api/delivery-notes              Create new
GET    /api/delivery-notes              List all
GET    /api/delivery-notes/{id}         Get one
PUT    /api/delivery-notes/{id}         Update
DELETE /api/delivery-notes/{id}         Delete

POST   /api/delivery-notes/{id}/dispatch            Start delivery
POST   /api/delivery-notes/{id}/confirm-delivery    Mark delivered
POST   /api/delivery-notes/{id}/post                Post to accounting

GET    /api/delivery-notes?status=in_transit        Filter by status
GET    /api/delivery-notes?vehicle_id=5             Filter by vehicle
GET    /api/delivery-notes?driver_id=12             Filter by driver
```

### Creditors

```
GET    /api/creditors/outstanding       List with balances
GET    /api/creditors/{id}/statement    Get transaction history
POST   /api/creditors/{id}/payment      Record payment
GET    /api/creditors/aging             Aging report
```

---

## 📋 Business Rules

### Delivery Notes

1. **Draft**: Can modify everything
2. **In Transit**: Cannot change vehicle/driver, stock locked
3. **Delivered**: Cannot modify quantities
4. **Posted**: Cannot delete, only cancel with reversal

### Stock Validation

```php
// Before dispatch
$available = Inventory::getBalance($productId, $sourceWarehouseId);
if ($available < $requiredQty) {
    throw new InsufficientStockException();
}
```

### COGS Calculation

```php
$cogs = $deliveryNote->items->sum(function($item) {
    return $item->delivered_qty * $item->cost_per_unit;
});
```

---

## 🎯 Quick Setup

### 1. Create Transit Warehouse for Vehicle

```sql
INSERT INTO warehouses (
    warehouse_name,
    is_transit_warehouse,
    linked_vehicle_id,
    company_id
) VALUES (
    'Vehicle ABC-123 Transit',
    true,
    5,
    1
);
```

### 2. Create Accounts Payable Account (if not exists)

```sql
INSERT INTO chart_of_accounts (
    account_name,
    account_code,
    account_type_id,
    is_group
) VALUES (
    'Accounts Payable',
    '2100',
    (SELECT id FROM account_types WHERE type_name = 'Liability'),
    false
);
```

### 3. Create Transit Warehouse Inventory Account

```sql
INSERT INTO chart_of_accounts (
    account_name,
    account_code,
    account_type_id,
    parent_account_id
) VALUES (
    'Transit Warehouse Inventory',
    '1420',
    (SELECT id FROM account_types WHERE type_name = 'Asset'),
    (SELECT id FROM chart_of_accounts WHERE account_code = '1400')
);
```

---

## 🔍 Troubleshooting

### Issue: Stock discrepancy in transit warehouse

**Check:**
```sql
SELECT * FROM inventory_transactions
WHERE warehouse_id = [transit_warehouse_id]
ORDER BY transaction_date DESC;
```

### Issue: Delivery note won't post

**Verify:**
- Status = 'delivered'
- posting_status = 'draft'
- All items have delivered_qty set
- Transit warehouse linked to GL account

### Issue: Creditor balance doesn't match

**Query:**
```sql
-- Get all transactions for creditor
SELECT 
    je.entry_date,
    je.reference,
    jed.debit,
    jed.credit,
    @balance := @balance + (jed.credit - jed.debit) as balance
FROM journal_entries je
JOIN journal_entry_details jed ON je.id = jed.journal_entry_id
CROSS JOIN (SELECT @balance := 0) vars
WHERE je.creditor_id = ?
  AND jed.account_id = (SELECT id FROM chart_of_accounts WHERE account_code = '2100')
ORDER BY je.entry_date;
```

---

## 📞 Support

For detailed documentation, see:
- `docs/DELIVERY_SYSTEM.md` - Complete delivery guide
- `docs/CREDITOR_MANAGEMENT.md` - Creditor tracking guide
- `docs/IMPLEMENTATION_SUMMARY.md` - Full implementation details
- `docs/ACCOUNTING_USAGE_GUIDE.md` - Double-entry accounting

---

**Last Updated:** November 2, 2025  
**Version:** 1.0
