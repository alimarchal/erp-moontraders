# 🚀 Quick Start Guide - Sales Distribution System

## 🎯 Your Complete Workflow (From GRN to Sales)

### Step 1: GRN Received ✅ (Already Done!)
- Goods received from supplier
- Stock in warehouse
- Ready for distribution

---

### Step 2: Morning - Issue Goods to Salesman

**URL:** `/goods-issues/create`

```
1. Fill Form:
   ├─ Issue Date: 2025-11-17
   ├─ Select Warehouse: Main Warehouse
   ├─ Select Vehicle: VAN-001 (Salesman's vehicle)
   ├─ Select Salesman: John Doe
   └─ Add Products:
      ├─ Product A: Qty 100, Cost 85.99
      ├─ Product B: Qty 50, Cost 120.00
      └─ Product C: Qty 75, Cost 65.50

2. Click "Create Goods Issue"
   └─ Status: Draft

3. Click "Post Issue"
   └─ Status: Issued ✅
   └─ Stock transferred from warehouse to vehicle
```

---

### Step 3: Evening - Record Sales Settlement

**URL:** `/sales-settlements/create`

```
1. Select Goods Issue:
   └─ Choose: GI-2025-0001 (from morning)
   └─ Products auto-load

2. Fill Sales Summary:
   ├─ Cash Sales: 8,500.00
   ├─ Credit Sales: 2,500.00
   └─ Total Sales: 11,000.00

3. Fill Collection:
   ├─ Cash Collected: 8,300.00
   ├─ Expenses Claimed: 200.00 (fuel)
   └─ Cash to Deposit: 8,100.00 (auto-calculated)

4. Fill Product Details:
   
   Product A:
   ├─ Issued: 100 (pre-filled)
   ├─ Sold: 85
   ├─ Returned: 10
   ├─ Shortage: 0
   ├─ Closing: 5 (carried forward)
   ├─ Selling Price: 95.00
   └─ Sales Value: 8,075.00

   Product B:
   ├─ Issued: 50
   ├─ Sold: 40
   ├─ Returned: 8
   ├─ Shortage: 2 (damaged)
   └─ Closing: 0

   Product C:
   ├─ Issued: 75
   ├─ Sold: 70
   ├─ Returned: 5
   └─ Closing: 0

5. Add Credit Sales (if any):
   └─ Add Credit Sale:
      ├─ Customer: ABC Mart
      ├─ Amount: 2,500.00
      └─ Invoice: INV-001

6. Click "Create Settlement"
   └─ Status: Draft

7. Review and Click "Post Settlement"
   └─ Status: Posted ✅
   └─ System automatically:
      ├─ Records sales
      ├─ Updates van stock
      ├─ Returns 23 items to warehouse
      ├─ Creates accounting entries:
      │  ├─ Dr. Cash 8,300
      │  ├─ Dr. A/R 2,500
      │  ├─ Cr. Sales 11,000
      │  ├─ Dr. COGS (calculated)
      │  └─ Cr. Inventory (calculated)
      └─ Done!
```

---

## 📊 What Happens Automatically

### When You Post Goods Issue:
```
✅ Warehouse stock: -225 units
✅ Van stock: +225 units
✅ Stock movements recorded
✅ Van opening balance set
✅ Status: Draft → Issued
```

### When You Post Sales Settlement:
```
✅ Van stock: -195 units (sold)
✅ Warehouse stock: +23 units (returned)
✅ Sales recorded: 11,000
✅ COGS calculated automatically
✅ Journal entry created
✅ Credit to customer: 2,500
✅ Shortage recorded: 2 units
✅ Closing stock in van: 5 units (Product A)
✅ Status: Draft → Posted
```

---

## 🎓 Key Concepts

### Van Stock Balance
- **Opening Balance:** Previous day closing
- **Today Issued:** From goods issue
- **Sold:** Reduces van stock
- **Returned:** Goes back to warehouse
- **Closing Balance:** Carried to next day opening

### Formula:
```
Closing Stock = Opening + Issued - Sold - Returned - Shortage
```

### Example:
```
Product A:
Opening:  0
Issued:   100
Sold:     85
Returned: 10
Shortage: 0
-----------
Closing:  5  ← Tomorrow's opening balance
```

---

## 📱 Daily Routine

### Every Morning:
1. Go to `/goods-issues/create`
2. Create goods issue for each salesman
3. Post all goods issues
4. Salesmen receive inventory

### Every Evening:
1. Go to `/sales-settlements/create`
2. Create settlement for each salesman
3. Record their sales, returns, expenses
4. Post all settlements
5. Done! Accounting auto-updated

---

## 🔍 How to Check Everything is Working

### After Posting Goods Issue:
```bash
# Check van stock
SELECT * FROM van_stock_balances WHERE vehicle_id = 1;

# Expected: All issued products with quantities

# Check warehouse stock (should be reduced)
SELECT * FROM current_stock WHERE warehouse_id = 1;
```

### After Posting Settlement:
```bash
# Check journal entry was created
SELECT * FROM journal_entries WHERE reference LIKE 'SETTLE%' ORDER BY id DESC LIMIT 1;

# Check van stock reduced
SELECT * FROM van_stock_balances WHERE vehicle_id = 1;

# Check warehouse stock increased (returns)
SELECT * FROM current_stock WHERE warehouse_id = 1;
```

---

## 📈 Reports You Can Build

1. **Daily Sales by Salesman:**
   - Total sales per salesman
   - Cash vs Credit breakdown
   - Product-wise sales

2. **Van Stock Report:**
   - Current stock in each vehicle
   - Opening, issued, sold, closing

3. **Outstanding Credit:**
   - Customer-wise credit
   - Aging analysis
   - Collection follow-up

4. **Product Performance:**
   - Best selling products
   - Slow moving items
   - Profitability analysis

---

## ⚠️ Important Notes

1. **Can't Edit After Posting:**
   - Once posted, goods issues and settlements are locked
   - This ensures data integrity
   - Create a new one if needed

2. **Stock Availability:**
   - System checks warehouse stock before posting goods issue
   - Error if insufficient stock

3. **Van Stock Tracking:**
   - Opening balance auto-set from previous closing
   - Closing becomes next day's opening

4. **Credit Sales:**
   - Tracked customer-wise
   - Creates Accounts Receivable
   - Ready for collection follow-up

5. **Shortages:**
   - Record damaged/missing items
   - Reduces van stock
   - Can be investigated later

---

## 🎊 You're Ready!

Your system is fully operational. Start with:

1. **Test Run:** Create one goods issue + settlement with small quantities
2. **Verify:** Check all tables updated correctly
3. **Train Team:** Show warehouse managers and salesmen the process
4. **Go Live:** Start daily operations

**Questions?** Everything is working and ready to use! 🚀

---

## 📞 Quick Reference

- **Goods Issues:** `/goods-issues`
- **Sales Settlements:** `/sales-settlements`
- **GRN:** `/goods-receipt-notes`
- **Products:** `/products`
- **Warehouses:** `/warehouses`
- **Vehicles:** `/vehicles`
- **Employees:** `/employees`
- **Customers:** `/customers`

All routes are working! ✅
