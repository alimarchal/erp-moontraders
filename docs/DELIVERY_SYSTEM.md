# Vehicle-Based Delivery System Documentation

## Overview
This document describes the comprehensive vehicle-based delivery tracking system implemented in MoonTrader, following ERPNext's industry-standard patterns for delivery notes, stock ledger entries, and transit warehouses.

---

## Table of Contents
1. [System Architecture](#system-architecture)
2. [Core Concepts](#core-concepts)
3. [Database Schema](#database-schema)
4. [Delivery Workflow](#delivery-workflow)
5. [Stock Movement](#stock-movement)
6. [Double-Entry Accounting](#double-entry-accounting)
7. [Reporting](#reporting)
8. [API Reference](#api-reference)

---

## 1. System Architecture

### Design Philosophy
The delivery system follows ERPNext's proven patterns:
- **Delivery Note**: Central document for tracking deliveries
- **Transit Warehouse**: Vehicles act as temporary warehouses
- **Stock Ledger Integration**: Real-time inventory tracking
- **COGS Recognition**: Automatic cost recognition on delivery

### System Flow
```
Sales Order → Delivery Note Created → Load Products → In Transit → Delivered → COGS Posted
     ↓              ↓                      ↓             ↓            ↓            ↓
  Customer      Assign Vehicle        Warehouse    Vehicle/Transit  Customer   GL Entry
                & Driver              Stock Out    Warehouse        Receives   Created
```

---

## 2. Core Concepts

### 2.1 Delivery Note
A delivery note represents the assignment of products to a vehicle/driver for customer delivery.

**Key Characteristics:**
- Unique delivery note number (e.g., DN-2025-001)
- Links to vehicle, driver, and customer
- Tracks loaded, delivered, and returned quantities
- Maintains delivery status lifecycle
- Integrates with accounting system

### 2.2 Transit Warehouse
Vehicles can act as transit warehouses, enabling real-time tracking of goods in transit.

**Concept:**
- Physical warehouse → Transit warehouse (vehicle) → Customer
- Inventory tracked at each stage
- Enables accurate stock reporting
- Supports route optimization

**Implementation:**
```
Warehouse A (Physical)     Warehouse B (Vehicle #123 Transit)     Customer
Stock: 100 units     →      Stock: 20 units (in transit)    →      Delivered: 20 units
Remaining: 80 units         Status: In Transit                      Status: Delivered
```

### 2.3 Stock Movement Tracking

#### Three-Stage Movement:
1. **Loading Stage**: Warehouse → Transit Warehouse (Vehicle)
   - Create stock transfer
   - Reduce physical warehouse stock
   - Increase transit warehouse stock
   - Status: `in_transit`

2. **Delivery Stage**: Transit Warehouse → Customer
   - Reduce transit warehouse stock
   - Record delivery confirmation
   - Status: `delivered`
   - Trigger COGS recognition

3. **Return Stage** (if applicable): Transit Warehouse → Warehouse
   - Handle undelivered/returned items
   - Transfer back to physical warehouse
   - Update delivery note
   - Status: `returned` or `partially_delivered`

---

## 3. Database Schema

### 3.1 Delivery Notes Table

```sql
delivery_notes
├── id
├── delivery_note_number (unique)
├── delivery_date
├── departure_time
├── estimated_arrival
├── actual_arrival
│
├── Vehicle & Driver
│   ├── vehicle_id → vehicles.id
│   ├── driver_id → employees.id
│
├── Customer & Location
│   ├── customer_id → customers.id
│   ├── sale_id → sales.id (optional)
│   ├── delivery_address
│   ├── contact_person
│   ├── contact_phone
│
├── Warehouses
│   ├── source_warehouse_id → warehouses.id
│   ├── transit_warehouse_id → warehouses.id
│
├── Status & Tracking
│   ├── status (enum: draft/in_transit/delivered/partially_delivered/returned/cancelled)
│   ├── delivery_notes (text)
│   ├── return_reason (text)
│
├── Financial
│   ├── total_amount (decimal 15,2)
│   ├── received_amount (decimal 15,2)
│   ├── payment_status (enum: unpaid/partial/paid)
│
├── Route
│   ├── distance_km (decimal 10,2)
│   ├── route (varchar)
│
├── Accounting
│   ├── journal_entry_id → journal_entries.id
│   ├── posting_status (enum: draft/posted/cancelled)
│
├── Approval
│   ├── approved_by → employees.id
│   ├── approved_at (timestamp)
│
├── timestamps, soft_deletes
```

**Indexes:**
- `delivery_date + status`
- `vehicle_id + delivery_date`
- `driver_id + delivery_date`
- `customer_id + delivery_date`
- `posting_status + delivery_date`

### 3.2 Delivery Note Items Table

```sql
delivery_note_items
├── id
├── delivery_note_id → delivery_notes.id
│
├── Product
│   ├── product_id → products.id
│   ├── uom_id → uoms.id
│
├── Quantity Tracking (ERPNext Pattern)
│   ├── loaded_qty (decimal 15,2) - Loaded onto vehicle
│   ├── delivered_qty (decimal 15,2) - Successfully delivered
│   ├── returned_qty (decimal 15,2) - Returned/undelivered
│   ├── pending_qty (computed: loaded_qty - delivered_qty - returned_qty)
│
├── Pricing
│   ├── rate (decimal 15,2) - Rate per unit
│   ├── amount (computed: loaded_qty * rate)
│   ├── delivered_amount (computed: delivered_qty * rate)
│
├── Cost Tracking (COGS)
│   ├── cost_per_unit (decimal 15,2)
│   ├── total_cost (computed: loaded_qty * cost_per_unit)
│   ├── cogs_amount (computed: delivered_qty * cost_per_unit)
│
├── References
│   ├── sale_item_id → sale_items.id
│   ├── stock_receipt_item_id → stock_receipt_items.id
│
├── Tracking
│   ├── item_status (enum: good/damaged/returned)
│   ├── batch_number (varchar)
│   ├── serial_numbers (text)
│   ├── notes (text)
│
├── timestamps
```

**Indexes:**
- `delivery_note_id + product_id`
- `sale_item_id`
- `product_id`

### 3.3 Warehouses Table Enhancement

**New Fields Added:**
```sql
warehouses (existing table enhanced)
├── is_transit_warehouse (boolean) - Flags vehicle-based transit warehouses
├── linked_vehicle_id → vehicles.id - Links transit warehouse to vehicle
```

**Transit Warehouse Setup:**
```
Warehouse Name: "Vehicle #ABC123 Transit"
Type: Transit
is_transit_warehouse: true
linked_vehicle_id: 5 (Vehicle ABC123)
```

---

## 4. Delivery Workflow

### 4.1 Complete Delivery Lifecycle

```
┌─────────────────────────────────────────────────────────────────────────┐
│ PHASE 1: PREPARATION (Status: draft)                                    │
├─────────────────────────────────────────────────────────────────────────┤
│ 1. Create Delivery Note                                                  │
│    - Generate delivery_note_number                                       │
│    - Assign vehicle and driver                                           │
│    - Set delivery_date                                                   │
│    - Link to customer and sales order (optional)                         │
│                                                                           │
│ 2. Add Delivery Items                                                    │
│    - Select products from sales order or manually                        │
│    - Set loaded_qty for each product                                     │
│    - Retrieve cost_per_unit from inventory                               │
│    - Calculate totals                                                    │
│                                                                           │
│ 3. Validation                                                            │
│    - Check stock availability in source warehouse                        │
│    - Verify vehicle capacity                                             │
│    - Confirm driver assignment                                           │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│ PHASE 2: LOADING (Status: draft → in_transit)                           │
├─────────────────────────────────────────────────────────────────────────┤
│ 1. Stock Transfer: Warehouse → Transit                                   │
│    - Create inventory_transactions entries                               │
│    - Reduce stock in source_warehouse_id                                 │
│    - Increase stock in transit_warehouse_id (vehicle)                    │
│                                                                           │
│    For each delivery_note_item:                                          │
│    Inventory Transaction #1:                                             │
│      product_id: [product]                                               │
│      warehouse_id: source_warehouse_id                                   │
│      transaction_type: 'transfer_out'                                    │
│      quantity_out: loaded_qty                                            │
│      unit_cost: cost_per_unit                                            │
│                                                                           │
│    Inventory Transaction #2:                                             │
│      product_id: [product]                                               │
│      warehouse_id: transit_warehouse_id                                  │
│      transaction_type: 'transfer_in'                                     │
│      quantity_in: loaded_qty                                             │
│      unit_cost: cost_per_unit                                            │
│                                                                           │
│ 2. Update Delivery Note                                                  │
│    - Set status = 'in_transit'                                           │
│    - Record departure_time                                               │
│    - Calculate estimated_arrival                                         │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│ PHASE 3: DELIVERY (Status: in_transit → delivered)                      │
├─────────────────────────────────────────────────────────────────────────┤
│ 1. Record Delivery Confirmation                                          │
│    - Driver confirms delivery via mobile app or system                   │
│    - Update delivered_qty for each item                                  │
│    - Capture actual_arrival time                                         │
│    - Record any returned_qty (if items not accepted)                     │
│    - Collect received_amount (if COD)                                    │
│                                                                           │
│ 2. Stock Movement: Transit → Customer                                    │
│    For each delivered item:                                              │
│    Inventory Transaction:                                                │
│      product_id: [product]                                               │
│      warehouse_id: transit_warehouse_id                                  │
│      transaction_type: 'sale'                                            │
│      quantity_out: delivered_qty                                         │
│      unit_cost: cost_per_unit                                            │
│      transactionable_type: 'DeliveryNote'                                │
│      transactionable_id: delivery_note.id                                │
│                                                                           │
│ 3. Update Delivery Note Status                                           │
│    - If all items delivered: status = 'delivered'                        │
│    - If some items returned: status = 'partially_delivered'              │
│    - Update payment_status if payment collected                          │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│ PHASE 4: ACCOUNTING (Status: delivered → posted)                        │
├─────────────────────────────────────────────────────────────────────────┤
│ 1. Post to Accounting (posting_status: draft → posted)                   │
│    - Calculate total COGS from delivered items                           │
│    - Create journal entry                                                │
│    - Update journal_entry_id                                             │
│                                                                           │
│ 2. Journal Entry Created:                                                │
│    Date: delivery_date                                                   │
│    Reference: delivery_note_number                                       │
│                                                                           │
│    DR: Cost of Goods Sold Account         PKR [COGS Amount]             │
│    CR: Inventory Account (Transit WH)                  PKR [COGS Amount] │
│                                                                           │
│    Narrative: "COGS for Delivery Note [DN-2025-001]"                     │
│                                                                           │
│ 3. Revenue Recognition (if linked to Sale)                               │
│    DR: Accounts Receivable                PKR [Sale Amount]              │
│    CR: Sales Revenue                                   PKR [Sale Amount] │
│                                                                           │
│ 4. Cash Collection (if COD)                                              │
│    DR: Cash in Hand                       PKR [Received Amount]          │
│    CR: Accounts Receivable                             PKR [Received]    │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│ PHASE 5: RETURNS (if applicable)                                         │
├─────────────────────────────────────────────────────────────────────────┤
│ 1. Handle Returned Items                                                 │
│    - Update returned_qty for items                                       │
│    - Set return_reason                                                   │
│                                                                           │
│ 2. Stock Transfer: Transit → Physical Warehouse                          │
│    Inventory Transaction:                                                │
│      transaction_type: 'transfer_in'                                     │
│      warehouse_id: source_warehouse_id                                   │
│      quantity_in: returned_qty                                           │
│                                                                           │
│ 3. Accounting Adjustment                                                 │
│    DR: Inventory Account (Physical WH)    PKR [Return Cost]              │
│    CR: Cost of Goods Sold                              PKR [Return Cost] │
└─────────────────────────────────────────────────────────────────────────┘
```

### 4.2 Status Transitions

```
draft
  ↓ (Load products onto vehicle)
in_transit
  ↓ (All items delivered)
delivered ←──────┐
  ↓              │
posted           │ (Some items returned)
                 │
partially_delivered
  ↓
returned (if all items returned)
  ↓
cancelled (if delivery abandoned)
```

### 4.3 Business Rules

1. **Draft Stage**
   - Can modify vehicle, driver, items, quantities
   - Must have at least one item with loaded_qty > 0
   - Source warehouse must have sufficient stock
   - Transit warehouse must be linked to the vehicle

2. **In Transit Stage**
   - Cannot modify vehicle or driver
   - Can update estimated_arrival
   - Can add delivery notes
   - Stock is locked in transit warehouse

3. **Delivered Stage**
   - delivered_qty cannot exceed loaded_qty
   - returned_qty + delivered_qty cannot exceed loaded_qty
   - Must record actual_arrival time
   - Automatically posts to accounting if posting_status = 'draft'

4. **Posted Stage**
   - Cannot modify quantities
   - Cannot delete delivery note
   - Can only cancel with reversal entry

---

## 5. Stock Movement

### 5.1 Inventory Transactions Integration

Every delivery note creates multiple inventory transactions:

**Loading Phase:**
```php
// Item 1: Product A, 50 units @ PKR 100/unit
[
    'product_id' => 1,
    'warehouse_id' => 5, // Physical Warehouse
    'transaction_type' => 'transfer_out',
    'quantity_out' => 50,
    'unit_cost' => 100,
    'balance' => 450, // Previous: 500, After: 450
    'transactionable_type' => 'DeliveryNote',
    'transactionable_id' => 1
],
[
    'product_id' => 1,
    'warehouse_id' => 12, // Transit Warehouse (Vehicle)
    'transaction_type' => 'transfer_in',
    'quantity_in' => 50,
    'unit_cost' => 100,
    'balance' => 50,
    'transactionable_type' => 'DeliveryNote',
    'transactionable_id' => 1
]
```

**Delivery Phase:**
```php
[
    'product_id' => 1,
    'warehouse_id' => 12, // Transit Warehouse
    'transaction_type' => 'sale',
    'quantity_out' => 45, // 45 delivered, 5 returned
    'unit_cost' => 100,
    'balance' => 5, // Remaining in transit
    'transactionable_type' => 'DeliveryNote',
    'transactionable_id' => 1
]
```

**Return Phase:**
```php
[
    'product_id' => 1,
    'warehouse_id' => 5, // Back to Physical Warehouse
    'transaction_type' => 'transfer_in',
    'quantity_in' => 5,
    'unit_cost' => 100,
    'balance' => 455, // Physical warehouse updated
    'transactionable_type' => 'DeliveryNote',
    'transactionable_id' => 1
]
```

### 5.2 Stock Balance Calculation

At any point in time, for Product A:
```
Total Stock = Physical Warehouse Stock + Transit Warehouse Stock + Other Warehouses
            = 455 + 0 + (other warehouses)
            = 455 units available
```

**Available for Sale:**
- Only stock in physical (non-transit) warehouses
- Transit stock is reserved for delivery

### 5.3 Real-Time Stock Updates

The `inventory_transactions` table maintains:
- Running balance for each product in each warehouse
- Complete audit trail
- Real-time valuation
- FIFO/Average cost calculation

---

## 6. Double-Entry Accounting

### 6.1 Chart of Accounts Structure

**Required Accounts:**
```
Assets
├── Current Assets
│   ├── Inventory (1400)
│   │   ├── Physical Warehouse Inventory (1410)
│   │   ├── Transit Warehouse Inventory (1420)
│   ├── Accounts Receivable (1300)
│   ├── Cash in Hand (1100)
│
Expenses
├── Cost of Goods Sold (5000)
│
Income
├── Sales Revenue (4000)
```

### 6.2 Journal Entries

**Entry 1: Loading (Transfer to Transit)**
*No accounting entry needed - it's an asset transfer*

**Entry 2: Delivery Confirmation (COGS Recognition)**
```
Date: 2025-11-02
Reference: DN-2025-001
Narration: Cost of Goods Sold for Delivery Note DN-2025-001

DR: Cost of Goods Sold (5000)                PKR 4,500
    CR: Transit Warehouse Inventory (1420)               PKR 4,500
```
*Recognizes COGS when products delivered to customer*

**Entry 3: Revenue Recognition (if linked to Sale)**
```
DR: Accounts Receivable (1300)               PKR 6,750
    CR: Sales Revenue (4000)                             PKR 6,750
```
*Records revenue from sale*

**Entry 4: Cash Collection (COD)**
```
DR: Cash in Hand (1100)                      PKR 6,750
    CR: Accounts Receivable (1300)                       PKR 6,750
```
*Records cash received from customer*

**Entry 5: Return Adjustment**
```
DR: Transit Warehouse Inventory (1420)       PKR 500
    CR: Cost of Goods Sold (5000)                        PKR 500
```
*Reverses COGS for returned items*

### 6.3 Posting Workflow

```php
// In DeliveryNotePostingService.php
public function postDeliveryNote(DeliveryNote $deliveryNote)
{
    // 1. Calculate total COGS
    $totalCOGS = $deliveryNote->items->sum('cogs_amount');
    
    // 2. Get transit warehouse inventory account
    $transitWarehouse = $deliveryNote->transitWarehouse;
    $inventoryAccount = $transitWarehouse->account_id;
    
    // 3. Get COGS account from company settings
    $cogsAccount = ChartOfAccount::where('account_code', '5000')->first();
    
    // 4. Create journal entry
    $journalEntry = JournalEntry::create([
        'entry_date' => $deliveryNote->delivery_date,
        'reference' => $deliveryNote->delivery_note_number,
        'narration' => "COGS for Delivery Note {$deliveryNote->delivery_note_number}",
        'total_debit' => $totalCOGS,
        'total_credit' => $totalCOGS
    ]);
    
    // 5. Create journal entry details
    JournalEntryDetail::create([
        'journal_entry_id' => $journalEntry->id,
        'account_id' => $cogsAccount->id,
        'debit' => $totalCOGS,
        'credit' => 0
    ]);
    
    JournalEntryDetail::create([
        'journal_entry_id' => $journalEntry->id,
        'account_id' => $inventoryAccount,
        'debit' => 0,
        'credit' => $totalCOGS
    ]);
    
    // 6. Update delivery note
    $deliveryNote->update([
        'journal_entry_id' => $journalEntry->id,
        'posting_status' => 'posted'
    ]);
    
    return $journalEntry;
}
```

---

## 7. Reporting

### 7.1 Vehicle-Wise Delivery Report

**Purpose:** Track deliveries by vehicle for performance analysis

**SQL Query:**
```sql
SELECT 
    v.vehicle_number,
    v.vehicle_type,
    COUNT(dn.id) as total_deliveries,
    SUM(dn.total_amount) as total_revenue,
    SUM(dn.distance_km) as total_distance,
    AVG(TIMESTAMPDIFF(HOUR, dn.departure_time, dn.actual_arrival)) as avg_delivery_time,
    SUM(CASE WHEN dn.status = 'delivered' THEN 1 ELSE 0 END) as successful_deliveries,
    SUM(CASE WHEN dn.status = 'returned' THEN 1 ELSE 0 END) as returned_deliveries
FROM vehicles v
LEFT JOIN delivery_notes dn ON v.id = dn.vehicle_id
WHERE dn.delivery_date BETWEEN '2025-01-01' AND '2025-12-31'
GROUP BY v.id
ORDER BY total_revenue DESC;
```

### 7.2 Driver Performance Report

**Purpose:** Evaluate driver efficiency and reliability

**Metrics:**
- Total deliveries completed
- On-time delivery percentage
- Average delivery time
- Customer feedback scores
- Total distance covered
- Fuel efficiency (if tracked)

**Query:**
```sql
SELECT 
    e.name as driver_name,
    COUNT(dn.id) as total_deliveries,
    SUM(dn.total_amount) as revenue_generated,
    SUM(dn.received_amount) as cash_collected,
    ROUND(
        (SUM(CASE WHEN dn.actual_arrival <= dn.estimated_arrival THEN 1 ELSE 0 END) / COUNT(dn.id)) * 100, 
        2
    ) as on_time_percentage,
    SUM(CASE WHEN dn.status = 'returned' THEN 1 ELSE 0 END) as returns_count
FROM employees e
LEFT JOIN delivery_notes dn ON e.id = dn.driver_id
WHERE dn.delivery_date BETWEEN '2025-01-01' AND '2025-12-31'
GROUP BY e.id
ORDER BY total_deliveries DESC;
```

### 7.3 In-Transit Inventory Report

**Purpose:** Real-time visibility of goods in transit

**Query:**
```sql
SELECT 
    p.product_name,
    w.warehouse_name as transit_warehouse,
    v.vehicle_number,
    SUM(it.balance) as quantity_in_transit,
    SUM(it.balance_value) as value_in_transit,
    dn.delivery_note_number,
    dn.driver_id,
    e.name as driver_name,
    dn.estimated_arrival
FROM inventory_transactions it
JOIN products p ON it.product_id = p.id
JOIN warehouses w ON it.warehouse_id = w.id
JOIN vehicles v ON w.linked_vehicle_id = v.id
LEFT JOIN delivery_notes dn ON w.id = dn.transit_warehouse_id AND dn.status = 'in_transit'
LEFT JOIN employees e ON dn.driver_id = e.id
WHERE w.is_transit_warehouse = true
  AND it.balance > 0
ORDER BY dn.estimated_arrival;
```

### 7.4 Delivery Status Dashboard

**Real-Time Metrics:**
- Total deliveries today
- In-transit deliveries
- Completed deliveries
- Pending deliveries
- Returned items
- Cash collected today

**Query:**
```sql
SELECT 
    COUNT(CASE WHEN status = 'in_transit' THEN 1 END) as in_transit,
    COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered,
    COUNT(CASE WHEN status = 'partially_delivered' THEN 1 END) as partial,
    COUNT(CASE WHEN status = 'returned' THEN 1 END) as returned,
    SUM(total_amount) as total_revenue,
    SUM(received_amount) as cash_collected,
    COUNT(DISTINCT vehicle_id) as active_vehicles,
    COUNT(DISTINCT driver_id) as active_drivers
FROM delivery_notes
WHERE delivery_date = CURDATE();
```

### 7.5 Customer Delivery History

**Purpose:** Track all deliveries to a specific customer

**Query:**
```sql
SELECT 
    dn.delivery_note_number,
    dn.delivery_date,
    dn.status,
    v.vehicle_number,
    e.name as driver_name,
    COUNT(dni.id) as items_count,
    SUM(dni.delivered_qty) as total_qty_delivered,
    SUM(dni.delivered_amount) as total_amount,
    dn.payment_status
FROM delivery_notes dn
JOIN delivery_note_items dni ON dn.id = dni.delivery_note_id
JOIN vehicles v ON dn.vehicle_id = v.id
JOIN employees e ON dn.driver_id = e.id
WHERE dn.customer_id = ?
ORDER BY dn.delivery_date DESC;
```

### 7.6 Route Optimization Data

**Purpose:** Analyze delivery routes for optimization

**Query:**
```sql
SELECT 
    dn.route,
    COUNT(dn.id) as delivery_count,
    AVG(dn.distance_km) as avg_distance,
    AVG(TIMESTAMPDIFF(HOUR, dn.departure_time, dn.actual_arrival)) as avg_time_hours,
    SUM(dn.total_amount) as total_revenue,
    SUM(dn.total_amount) / SUM(dn.distance_km) as revenue_per_km
FROM delivery_notes dn
WHERE dn.status IN ('delivered', 'partially_delivered')
  AND dn.route IS NOT NULL
GROUP BY dn.route
ORDER BY revenue_per_km DESC;
```

---

## 8. API Reference

### 8.1 Create Delivery Note

**Endpoint:** `POST /api/delivery-notes`

**Request Body:**
```json
{
  "delivery_date": "2025-11-02",
  "vehicle_id": 5,
  "driver_id": 12,
  "customer_id": 8,
  "sale_id": 23,
  "source_warehouse_id": 3,
  "transit_warehouse_id": 15,
  "delivery_address": "123 Main St, Karachi",
  "contact_person": "Ahmed Ali",
  "contact_phone": "0300-1234567",
  "estimated_arrival": "14:00:00",
  "items": [
    {
      "product_id": 10,
      "loaded_qty": 50,
      "rate": 150.00,
      "cost_per_unit": 100.00,
      "sale_item_id": 45
    },
    {
      "product_id": 11,
      "loaded_qty": 30,
      "rate": 200.00,
      "cost_per_unit": 120.00
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "delivery_note_number": "DN-2025-001",
    "status": "draft",
    "total_amount": 13500.00,
    "items_count": 2
  }
}
```

### 8.2 Start Delivery (Load & Dispatch)

**Endpoint:** `POST /api/delivery-notes/{id}/dispatch`

**Request Body:**
```json
{
  "departure_time": "09:30:00",
  "route": "Karachi → Clifton → DHA"
}
```

**Actions:**
- Validates stock availability
- Creates inventory transactions (warehouse → transit)
- Updates status to `in_transit`
- Records departure_time

### 8.3 Confirm Delivery

**Endpoint:** `POST /api/delivery-notes/{id}/confirm-delivery`

**Request Body:**
```json
{
  "actual_arrival": "13:45:00",
  "items": [
    {
      "delivery_note_item_id": 1,
      "delivered_qty": 45,
      "returned_qty": 5,
      "item_status": "good"
    },
    {
      "delivery_note_item_id": 2,
      "delivered_qty": 30,
      "returned_qty": 0,
      "item_status": "good"
    }
  ],
  "received_amount": 12150.00,
  "delivery_notes": "Customer satisfied, no issues"
}
```

**Actions:**
- Updates delivered_qty and returned_qty
- Creates inventory transactions (transit → customer)
- Updates status based on delivery completion
- Records actual_arrival and received_amount

### 8.4 Post to Accounting

**Endpoint:** `POST /api/delivery-notes/{id}/post`

**Actions:**
- Calculates total COGS
- Creates journal entry
- Updates posting_status to `posted`
- Links journal_entry_id

### 8.5 Get In-Transit Deliveries

**Endpoint:** `GET /api/delivery-notes?status=in_transit`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "delivery_note_number": "DN-2025-001",
      "vehicle": {
        "id": 5,
        "vehicle_number": "ABC-123",
        "vehicle_type": "Truck"
      },
      "driver": {
        "id": 12,
        "name": "Hassan Ahmed"
      },
      "departure_time": "09:30:00",
      "estimated_arrival": "14:00:00",
      "current_location": "En route to DHA",
      "items_count": 2,
      "total_amount": 13500.00
    }
  ]
}
```

---

## 9. Best Practices

### 9.1 Transit Warehouse Setup

1. **Create one transit warehouse per vehicle:**
   ```sql
   INSERT INTO warehouses (
       warehouse_name,
       is_transit_warehouse,
       linked_vehicle_id,
       company_id,
       warehouse_type_id
   ) VALUES (
       'Vehicle ABC-123 Transit',
       true,
       5,
       1,
       (SELECT id FROM warehouse_types WHERE type_name = 'Transit')
   );
   ```

2. **Link to appropriate GL account:**
   - Transit warehouse inventory should link to "Inventory in Transit" account
   - Separate from physical warehouse inventory for clear reporting

### 9.2 Stock Validation

Always validate before dispatch:
```php
// Check if source warehouse has sufficient stock
$availableStock = InventoryTransaction::where('product_id', $productId)
    ->where('warehouse_id', $sourceWarehouseId)
    ->orderBy('transaction_date', 'desc')
    ->value('balance');
    
if ($availableStock < $requiredQty) {
    throw new InsufficientStockException();
}
```

### 9.3 Error Handling

**Common Scenarios:**
- Vehicle breakdown → Transfer delivery to another vehicle
- Customer unavailable → Mark as returned, schedule redelivery
- Partial delivery → Update quantities, keep status as `partially_delivered`
- Wrong address → Cancel, create new delivery note

### 9.4 Performance Optimization

**Indexes are critical:**
- All foreign keys indexed
- Composite indexes for common queries (vehicle_id + delivery_date)
- Status field indexed for filtering

**Caching Strategy:**
- Cache in-transit inventory counts
- Cache daily delivery statistics
- Invalidate cache on delivery confirmation

---

## 10. Security Considerations

### 10.1 Access Control

**Roles:**
- **Warehouse Manager**: Create/edit delivery notes (draft status)
- **Driver**: View assigned deliveries, update delivery status
- **Accountant**: Post to accounting
- **Admin**: Full access

**Permissions:**
```php
Gate::define('dispatch-delivery', function ($user, $deliveryNote) {
    return $user->hasRole('Warehouse Manager') 
        && $deliveryNote->status === 'draft';
});

Gate::define('confirm-delivery', function ($user, $deliveryNote) {
    return $user->id === $deliveryNote->driver_id 
        && $deliveryNote->status === 'in_transit';
});

Gate::define('post-delivery', function ($user, $deliveryNote) {
    return $user->hasRole('Accountant') 
        && $deliveryNote->status === 'delivered'
        && $deliveryNote->posting_status === 'draft';
});
```

### 10.2 Audit Trail

Log all critical actions:
- Delivery note creation
- Status changes
- Quantity modifications
- Posting to accounting
- Cancellations

---

## 11. Future Enhancements

### Planned Features:
1. **GPS Tracking Integration**
   - Real-time vehicle location
   - Route deviation alerts
   - ETA updates

2. **Mobile App for Drivers**
   - Scan products during loading
   - Digital signatures from customers
   - Photo proof of delivery
   - Offline mode support

3. **Route Optimization AI**
   - Automatic route planning
   - Traffic-aware ETAs
   - Multi-stop optimization

4. **Customer Portal**
   - Track delivery status
   - Reschedule deliveries
   - Rate driver/service

5. **Advanced Analytics**
   - Predictive delivery times
   - Vehicle maintenance alerts
   - Driver performance scoring

---

## Conclusion

The Vehicle-Based Delivery System provides comprehensive tracking of goods from warehouse to customer, with real-time inventory visibility, integrated accounting, and detailed reporting. By following ERPNext's proven patterns, the system ensures scalability, accuracy, and ease of use for all stakeholders.

For technical support or questions, refer to the main documentation or contact the development team.
