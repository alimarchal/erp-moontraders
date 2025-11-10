# Inventory Management System - User Guide

## Overview
The Moontrader ERP system now includes a complete **batch-tracked inventory management** system with:
- Goods Receipt Notes (GRN) with promotional pricing support
- Stock batch tracking with FIFO/LIFO/Priority costing
- Real-time inventory levels
- Promotional campaigns management
- Stock movements audit trail

---

## How to Use the System

### 1. **Creating a Goods Receipt Note (GRN)**

**Location:** Main Menu → Inventory → Goods Receipt Notes → Create

**Steps:**
1. Select **Supplier** from dropdown
2. Select **Warehouse** (destination for stock)
3. Enter **Receipt Date**
4. Optional: Enter supplier invoice number and date
5. Click **"Add Line Item"** to add products:
   - Select **Product** (filtered by supplier)
   - Select **UOM** (Unit of Measure)
   - Enter **Quantity Received**
   - Enter **Quantity Accepted** (after QC)
   - Enter **Unit Cost** (purchase price)
   - Enter **Selling Price** (retail price)
   - **Optional Promotional Fields:**
     - Select **Promotional Campaign**
     - Enter **Promotional Price**
     - Enter **Must Sell Before Date**
     - Set **Priority Order** (1=highest, 99=normal)
6. Add more line items as needed
7. Click **"Save GRN"**

**Status:** GRN is created in **DRAFT** status

---

### 2. **Posting GRN to Inventory**

**Location:** View GRN → Click "Post to Inventory" button

**What Happens:**
1. GRN status changes from **draft → posted**
2. System creates **Stock Batches** for each line item
3. Creates **Stock Movements** (audit trail)
4. Creates **Stock Ledger Entries** (running balance)
5. Creates **Valuation Layers** (for FIFO costing)
6. Updates **Current Stock** summary tables

**Important:** Once posted, GRN **cannot be edited or deleted**

---

### 3. **Viewing Current Stock**

**Location:** Main Menu → Inventory → Current Stock

**What You See:**
- **Product Name** and **Code**
- **Warehouse** location
- **Quantity On Hand** - Total physical stock
- **Quantity Available** - (On Hand - Reserved)
- **Average Cost** - Weighted average purchase cost
- **Total Value** - Total inventory value
- **Batches Count:**
  - Total batches with stock
  - Promotional batches (marked with "P")
  - Priority batches (marked with "!")

**Filters Available:**
- By Product
- By Warehouse
- Promotional stock only
- Priority stock only

**Click "View Batches"** to see detailed batch breakdown

---

### 4. **Viewing Stock by Batch**

**Location:** Current Stock → Click "View Batches"

**What You See:**
- **Batch Code** (auto-generated: BATCH-2025-0001)
- **Receipt Date**
- **Expiry Date** (if applicable)
- **Quantity in Batch**
- **Unit Cost**
- **Priority Order** (lower = sell first)
- **Promotional Status**
- **Promotional Price** (if applicable)
- **Must Sell Before Date** (supplier condition)

**Color Codes:**
- 🟢 Green = Normal FIFO stock
- 🟠 Orange = Promotional stock
- 🔴 Red = Priority/urgent stock
- ⚠️ Yellow = Expiring soon

---

### 5. **Managing Promotional Campaigns**

**Location:** Settings → Promotional Campaigns

**To Create Campaign:**
1. Go to Promotional Campaigns → Create
2. Enter **Campaign Code** (e.g., EID-2025, RAMADAN-2025)
3. Enter **Campaign Name** and description
4. Set **Start Date** and **End Date**
5. Choose **Discount Type:**
   - Percentage (e.g., 20% off)
   - Fixed Amount (e.g., ₨100 off)
   - Special Price (e.g., ₨999 flat)
6. Set minimum quantity required
7. Save

**To Apply Campaign to Stock:**
- When creating GRN, select the campaign from dropdown
- Enter promotional price for that batch
- Stock will be flagged as promotional in inventory

---

### 6. **Stock Movement Tracking**

**Location:** Inventory → Stock Movements

**Movement Types:**
- **GRN** - Goods Receipt (inward)
- **Goods Issue** - Issue to vehicle/customer (outward)
- **Goods Return** - Return from vehicle (inward)
- **Transfer** - Warehouse to warehouse
- **Adjustment** - Stock correction
- **Damage** - Damaged/expired items
- **Theft** - Lost/stolen items

**Each movement records:**
- Date and time
- Product and batch
- Quantity (positive=IN, negative=OUT)
- Source/destination warehouse
- User who created movement
- Reference document

---

## Database Schema Summary

### Core Tables

1. **promotional_campaigns**
   - Campaign details and discount rules
   - Status: active/inactive

2. **stock_batches**
   - One batch per GRN line item
   - Contains: unit cost, expiry date, promotional info
   - Tracks batch status (active/depleted/expired/recalled)

3. **goods_receipt_notes** & **goods_receipt_note_items**
   - Purchase documentation
   - Status: draft/received/posted/cancelled

4. **stock_movements**
   - Every stock transaction (IN/OUT)
   - Links to source documents (GRN, Sales, etc.)

5. **stock_ledger_entries**
   - Running balance by product/warehouse/batch
   - Complete audit trail

6. **stock_valuation_layers**
   - FIFO/LIFO cost tracking
   - Quantity remaining per layer

7. **current_stock**
   - Real-time stock summary by product+warehouse
   - Aggregated from all batches

8. **current_stock_by_batch**
   - Real-time stock by individual batch
   - Includes promotional flags and priorities

---

## Priority & FIFO Logic

### How Stock is Issued (Auto-calculated):

1. **Priority Order 1-49** = Urgent (sell first)
   - E.g., near-expiry items, supplier conditions

2. **Priority Order 50-98** = Medium priority
   - E.g., promotional stock with time limits

3. **Priority Order 99** = Normal FIFO
   - Standard first-in-first-out

**Within same priority:**
- Oldest receipt date first
- Nearest expiry date first (if expiry tracking enabled)

---

## Common Workflows

### Workflow 1: Receive Regular Stock
1. Create GRN → Select supplier → Add products
2. Post GRN → Stock appears in Current Stock
3. System auto-creates batch with FIFO priority

### Workflow 2: Receive Promotional Stock
1. Create Promotional Campaign (if not exists)
2. Create GRN → Select supplier → Add products
3. For each promotional line:
   - Select promotional campaign
   - Enter promotional price
   - Set "Must Sell Before" date
   - Set priority order (e.g., 10 for urgent)
4. Post GRN
5. Stock flagged as promotional in inventory
6. System prioritizes this stock for sales

### Workflow 3: Check Available Stock
1. Go to Current Stock page
2. Filter by product/warehouse
3. See total available quantity
4. Click "View Batches" to see:
   - Which batches have stock
   - Which are promotional
   - Which have priority

---

## Access Paths

### Quick Navigation:

**GRN Management:**
- Create: `/goods-receipt-notes/create`
- List: `/goods-receipt-notes`
- View: `/goods-receipt-notes/{id}`
- Post: Click "Post to Inventory" button on GRN show page

**Inventory Views:**
- Current Stock: `/inventory/current-stock`
- Stock by Batch: `/inventory/current-stock/by-batch?product_id=X&warehouse_id=Y`

**Settings:**
- Promotional Campaigns: `/settings/promotional-campaigns` (to be created)
- Products: `/settings/products`
- Warehouses: `/settings/warehouses`
- Suppliers: `/settings/suppliers`

---

## Tips & Best Practices

✅ **DO:**
- Post GRNs immediately after receiving goods
- Set realistic "Must Sell Before" dates for promotional stock
- Use priority orders for urgent stock (near expiry, special deals)
- Review stock by batch regularly to identify slow-moving items

❌ **DON'T:**
- Don't edit GRN after posting (immutable by design)
- Don't manually adjust database - use stock adjustment movements
- Don't skip QC - enter correct accepted/rejected quantities

---

## Troubleshooting

**Q: Can't post GRN?**
- Check all required fields are filled
- Ensure quantities are valid (accepted <= received)
- Verify selling price is entered

**Q: Stock not appearing in Current Stock?**
- Check if GRN status is "posted" (not draft)
- Verify warehouse filter matches GRN warehouse
- Check quantity_accepted > 0

**Q: How to handle returns?**
- Use "Goods Return" stock movement (feature to be implemented)
- For now, create negative adjustment

**Q: How to check stock history?**
- View Stock Ledger Entries for complete audit trail
- Filter by product/warehouse/date range

---

## Future Enhancements (Planned)

- Goods Issue (for sales/deliveries)
- Stock Transfer between warehouses
- Stock Adjustments (cycle counts)
- Expiry alerts and notifications
- Reorder point management
- Stock aging report
- Promotional campaign performance report

---

## Support

For questions or issues:
1. Check this documentation
2. Review Laravel logs: `storage/logs/laravel.log`
3. Check database audit log: `accounting_audit_log` table
4. Contact system administrator

---

**Last Updated:** November 10, 2025
**Version:** 1.0
