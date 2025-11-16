# MoonTrader - Complete Schema Implementation Plan

**Generated**: November 15, 2025
**Based on**: Migration Analysis (2025_10_28 to 2025_11_11)
**Current Tables**: 44
**Missing Tables**: 27+ critical tables
**Estimated Implementation**: 4 Phases over 6-8 weeks

---

## Executive Summary

### Current State
- ✅ **Strong**: Accounting foundation, inventory tracking, van sales
- ⚠️ **Gaps**: Sales invoicing, purchase orders, returns, pricing, tax
- 📊 **Score**: 7.5/10 database maturity

### Target State
- 🎯 Complete ERP system with full order-to-cash and procure-to-pay cycles
- 🎯 Comprehensive pricing and tax management
- 🎯 Returns and quality management
- 🎯 Enhanced CRM and reporting capabilities

### Business Impact
- **Revenue**: Enable credit sales and formal AR management
- **Cost Control**: Purchase order system with 3-way matching
- **Compliance**: Tax management and audit trails
- **Customer Service**: Returns management and CRM features

---

## Implementation Phases

```
Phase 1: Critical Sales & AR (2 weeks)
├── Sales Orders
├── Sales Invoices
├── Customer Receipts
└── Revenue Recognition

Phase 2: Purchase Orders & Returns (2 weeks)
├── Purchase Orders
├── Purchase Requisitions
├── Customer Returns
└── Supplier Returns

Phase 3: Pricing & Tax (1.5 weeks)
├── Price Lists
├── Tax Management
├── Customer Pricing
└── Promotional Pricing Enhancement

Phase 4: Performance & Enhancement (2.5 weeks)
├── Indexes & Constraints
├── CRM Features
├── Reporting Tables
└── Advanced Features
```

---

# PHASE 1: CRITICAL SALES & ACCOUNTS RECEIVABLE
**Duration**: 2 weeks
**Priority**: CRITICAL
**Business Impact**: Enable credit sales, AR management, revenue tracking

## 1.1 Sales Orders Module

### Table: sales_orders
**Migration**: `2025_11_16_000001_create_sales_orders_table.php`

```php
Schema::create('sales_orders', function (Blueprint $table) {
    $table->id();
    $table->string('order_number', 50)->unique();
    $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
    $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
    $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
    $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();

    $table->date('order_date');
    $table->date('required_delivery_date')->nullable();
    $table->date('promised_delivery_date')->nullable();

    $table->enum('order_type', ['standard', 'direct_delivery', 'van_sale', 'online'])->default('standard');
    $table->enum('status', ['draft', 'confirmed', 'approved', 'processing', 'partially_fulfilled', 'fulfilled', 'cancelled'])->default('draft');
    $table->enum('payment_terms', ['cash', 'credit', 'cod', 'advance'])->default('credit');

    $table->decimal('subtotal_amount', 15, 2)->default(0);
    $table->decimal('discount_percent', 5, 2)->default(0);
    $table->decimal('discount_amount', 15, 2)->default(0);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('freight_charges', 15, 2)->default(0);
    $table->decimal('other_charges', 15, 2)->default(0);
    $table->decimal('total_amount', 15, 2)->default(0);

    $table->text('customer_notes')->nullable();
    $table->text('internal_notes')->nullable();
    $table->string('customer_po_number', 100)->nullable();

    $table->string('delivery_address')->nullable();
    $table->string('delivery_city', 100)->nullable();
    $table->string('delivery_contact_person', 100)->nullable();
    $table->string('delivery_contact_phone', 20)->nullable();

    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('approved_at')->nullable();
    $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('cancelled_at')->nullable();
    $table->text('cancellation_reason')->nullable();

    $table->softDeletes();
    $table->timestamps();

    // Indexes
    $table->index(['customer_id', 'order_date']);
    $table->index(['status', 'order_date']);
    $table->index(['employee_id', 'order_date']);
    $table->index('customer_po_number');
});
```

**Model Features**:
- Custom ID: `SO-YYYYMMDD-####`
- Statuses with workflow validation
- Audit trail (created_by, approved_by, cancelled_by)
- Soft deletes for data preservation

---

### Table: sales_order_items
**Migration**: `2025_11_16_000002_create_sales_order_items_table.php`

```php
Schema::create('sales_order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
    $table->integer('line_no')->default(1);

    $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
    $table->foreignId('uom_id')->constrained('uoms')->restrictOnDelete();

    $table->decimal('quantity_ordered', 15, 2);
    $table->decimal('quantity_fulfilled', 15, 2)->default(0);
    $table->decimal('quantity_cancelled', 15, 2)->default(0);
    $table->decimal('quantity_reserved', 15, 2)->default(0);

    $table->decimal('unit_price', 15, 2);
    $table->decimal('discount_percent', 5, 2)->default(0);
    $table->decimal('discount_amount', 15, 2)->default(0);
    $table->decimal('tax_percent', 5, 2)->default(0);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('line_total', 15, 2);

    $table->date('requested_delivery_date')->nullable();
    $table->text('line_notes')->nullable();

    $table->enum('fulfillment_status', ['pending', 'reserved', 'partially_fulfilled', 'fulfilled', 'cancelled'])->default('pending');

    $table->timestamps();

    // Constraints
    $table->unique(['sales_order_id', 'line_no']);
    $table->index(['product_id', 'fulfillment_status']);
});
```

---

### Table: stock_reservations
**Migration**: `2025_11_16_000003_create_stock_reservations_table.php`

```php
Schema::create('stock_reservations', function (Blueprint $table) {
    $table->id();

    // Polymorphic source (sales_order, goods_issue, etc.)
    $table->string('reservable_type'); // App\Models\SalesOrder
    $table->unsignedBigInteger('reservable_id');

    $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
    $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
    $table->foreignId('stock_batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();

    $table->decimal('quantity_reserved', 15, 2);
    $table->decimal('quantity_fulfilled', 15, 2)->default(0);
    $table->decimal('quantity_remaining', 15, 2);

    $table->date('reservation_date');
    $table->date('expiry_date')->nullable(); // Auto-release if not fulfilled
    $table->enum('status', ['active', 'partially_fulfilled', 'fulfilled', 'expired', 'cancelled'])->default('active');

    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    // Indexes
    $table->index(['reservable_type', 'reservable_id']);
    $table->index(['product_id', 'warehouse_id', 'status']);
    $table->index(['stock_batch_id', 'status']);
});
```

**Trigger Required**: Update `current_stock.quantity_reserved` when reservations change

---

## 1.2 Sales Invoicing Module

### Table: sales_invoices
**Migration**: `2025_11_16_000004_create_sales_invoices_table.php`

```php
Schema::create('sales_invoices', function (Blueprint $table) {
    $table->id();
    $table->string('invoice_number', 50)->unique();
    $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
    $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
    $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
    $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
    $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();

    $table->date('invoice_date');
    $table->date('due_date');
    $table->date('delivery_date')->nullable();

    $table->enum('invoice_type', ['sales', 'credit_note', 'debit_note'])->default('sales');
    $table->enum('status', ['draft', 'posted', 'partially_paid', 'paid', 'overdue', 'cancelled', 'reversed'])->default('draft');

    $table->decimal('subtotal_amount', 15, 2)->default(0);
    $table->decimal('discount_amount', 15, 2)->default(0);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('freight_charges', 15, 2)->default(0);
    $table->decimal('other_charges', 15, 2)->default(0);
    $table->decimal('total_amount', 15, 2)->default(0);
    $table->decimal('amount_paid', 15, 2)->default(0);
    $table->decimal('amount_outstanding', 15, 2)->default(0);

    $table->text('notes')->nullable();
    $table->string('customer_po_number', 100)->nullable();
    $table->string('delivery_note_number', 100)->nullable();

    $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('posted_at')->nullable();
    $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('reversed_at')->nullable();
    $table->text('reversal_reason')->nullable();

    $table->softDeletes();
    $table->timestamps();

    // Indexes
    $table->index(['customer_id', 'invoice_date']);
    $table->index(['status', 'due_date']);
    $table->index(['posted_at', 'status']);
    $table->index('amount_outstanding');
});
```

**Model Features**:
- Custom ID: `INV-YYYYMMDD-####`
- Immutable after posting (like GRN)
- Auto-creates journal entry when posted
- Tracks payment status

---

### Table: sales_invoice_items
**Migration**: `2025_11_16_000005_create_sales_invoice_items_table.php`

```php
Schema::create('sales_invoice_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
    $table->integer('line_no')->default(1);

    $table->foreignId('sales_order_item_id')->nullable()->constrained('sales_order_items')->nullOnDelete();
    $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
    $table->foreignId('uom_id')->constrained('uoms')->restrictOnDelete();
    $table->foreignId('stock_batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();

    $table->decimal('quantity', 15, 2);
    $table->decimal('unit_price', 15, 2);
    $table->decimal('unit_cost', 15, 2)->default(0); // For COGS calculation

    $table->decimal('discount_percent', 5, 2)->default(0);
    $table->decimal('discount_amount', 15, 2)->default(0);
    $table->decimal('tax_percent', 5, 2)->default(0);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('line_total', 15, 2);
    $table->decimal('cogs_amount', 15, 2)->default(0); // Cost of Goods Sold

    $table->text('description')->nullable();

    $table->timestamps();

    // Constraints
    $table->unique(['sales_invoice_id', 'line_no']);
    $table->index('product_id');
    $table->index('stock_batch_id');
});
```

---

## 1.3 Customer Receipts Module

### Table: customer_receipts
**Migration**: `2025_11_16_000006_create_customer_receipts_table.php`

```php
Schema::create('customer_receipts', function (Blueprint $table) {
    $table->id();
    $table->string('receipt_number', 50)->unique();
    $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
    $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
    $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();

    $table->date('receipt_date');
    $table->enum('payment_method', ['cash', 'cheque', 'bank_transfer', 'credit_card', 'mobile_payment'])->default('cash');

    $table->decimal('amount', 15, 2);
    $table->decimal('allocated_amount', 15, 2)->default(0);
    $table->decimal('unallocated_amount', 15, 2);

    // Cheque details
    $table->string('cheque_number', 50)->nullable();
    $table->date('cheque_date')->nullable();
    $table->string('cheque_bank', 100)->nullable();
    $table->enum('cheque_status', ['received', 'deposited', 'cleared', 'bounced'])->nullable();

    // Bank transfer details
    $table->string('transfer_reference', 100)->nullable();

    $table->text('notes')->nullable();
    $table->enum('status', ['draft', 'posted', 'allocated', 'bounced', 'cancelled'])->default('draft');

    $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('posted_at')->nullable();

    $table->softDeletes();
    $table->timestamps();

    // Indexes
    $table->index(['customer_id', 'receipt_date']);
    $table->index(['status', 'receipt_date']);
    $table->index('cheque_number');
    $table->index('unallocated_amount');
});
```

**Model Features**:
- Custom ID: `RCP-YYYYMMDD-####`
- Auto-allocates to oldest invoices (FIFO)
- Cheque tracking with status
- Creates journal entry: Dr Cash/Bank, Cr AR

---

### Table: receipt_invoice_allocations
**Migration**: `2025_11_16_000007_create_receipt_invoice_allocations_table.php`

```php
Schema::create('receipt_invoice_allocations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_receipt_id')->constrained('customer_receipts')->cascadeOnDelete();
    $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->restrictOnDelete();

    $table->decimal('allocated_amount', 15, 2);
    $table->date('allocation_date');
    $table->text('notes')->nullable();

    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    // Prevent duplicate allocations
    $table->unique(['customer_receipt_id', 'sales_invoice_id']);
    $table->index(['sales_invoice_id', 'allocation_date']);
});
```

**Validation**: Sum of allocations ≤ receipt amount

---

## 1.4 Supporting Tables

### Table: customer_credit_limits_history
**Migration**: `2025_11_16_000008_create_customer_credit_limits_history_table.php`

```php
Schema::create('customer_credit_limits_history', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

    $table->decimal('old_credit_limit', 15, 2);
    $table->decimal('new_credit_limit', 15, 2);
    $table->date('effective_date');
    $table->text('reason')->nullable();

    $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index(['customer_id', 'effective_date']);
});
```

---

### Table: customer_aging_snapshots
**Migration**: `2025_11_16_000009_create_customer_aging_snapshots_table.php`

```php
Schema::create('customer_aging_snapshots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
    $table->date('snapshot_date');

    $table->decimal('current_amount', 15, 2)->default(0);      // 0-30 days
    $table->decimal('days_30_amount', 15, 2)->default(0);      // 31-60 days
    $table->decimal('days_60_amount', 15, 2)->default(0);      // 61-90 days
    $table->decimal('days_90_amount', 15, 2)->default(0);      // 91-120 days
    $table->decimal('days_120_plus_amount', 15, 2)->default(0); // 120+ days
    $table->decimal('total_outstanding', 15, 2)->default(0);

    $table->integer('invoice_count')->default(0);
    $table->date('oldest_invoice_date')->nullable();

    $table->timestamps();

    $table->unique(['customer_id', 'snapshot_date']);
    $table->index('snapshot_date');
});
```

**Scheduled Job**: Generate daily/weekly aging snapshots

---

# PHASE 2: PURCHASE ORDERS & RETURNS MANAGEMENT
**Duration**: 2 weeks
**Priority**: HIGH
**Business Impact**: Procurement control, 3-way matching, returns processing

## 2.1 Purchase Order Module

### Table: purchase_orders
**Migration**: `2025_11_18_000001_create_purchase_orders_table.php`

```php
Schema::create('purchase_orders', function (Blueprint $table) {
    $table->id();
    $table->string('po_number', 50)->unique();
    $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
    $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
    $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
    $table->foreignId('purchase_requisition_id')->nullable()->constrained('purchase_requisitions')->nullOnDelete();

    $table->date('po_date');
    $table->date('expected_delivery_date')->nullable();
    $table->enum('delivery_terms', ['fob', 'cif', 'exw', 'ddp'])->nullable();
    $table->enum('payment_terms', ['cod', 'net30', 'net60', 'net90', 'advance'])->default('net30');

    $table->enum('status', ['draft', 'submitted', 'approved', 'sent_to_supplier', 'acknowledged', 'partially_received', 'received', 'cancelled'])->default('draft');

    $table->decimal('subtotal_amount', 15, 2)->default(0);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('freight_charges', 15, 2)->default(0);
    $table->decimal('other_charges', 15, 2)->default(0);
    $table->decimal('total_amount', 15, 2)->default(0);

    $table->text('notes')->nullable();
    $table->text('terms_conditions')->nullable();

    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('approved_at')->nullable();
    $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('cancelled_at')->nullable();
    $table->text('cancellation_reason')->nullable();

    $table->softDeletes();
    $table->timestamps();

    // Indexes
    $table->index(['supplier_id', 'po_date']);
    $table->index(['status', 'expected_delivery_date']);
});
```

**Model Features**:
- Custom ID: `PO-YYYYMMDD-####`
- Approval workflow
- Links to GRNs for 3-way matching

---

### Table: purchase_order_items
**Migration**: `2025_11_18_000002_create_purchase_order_items_table.php`

```php
Schema::create('purchase_order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
    $table->integer('line_no')->default(1);

    $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
    $table->foreignId('uom_id')->constrained('uoms')->restrictOnDelete();

    $table->decimal('quantity_ordered', 15, 2);
    $table->decimal('quantity_received', 15, 2)->default(0);
    $table->decimal('quantity_cancelled', 15, 2)->default(0);
    $table->decimal('quantity_outstanding', 15, 2);

    $table->decimal('unit_cost', 15, 2);
    $table->decimal('tax_percent', 5, 2)->default(0);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('line_total', 15, 2);

    $table->date('required_date')->nullable();
    $table->text('specifications')->nullable();

    $table->enum('status', ['pending', 'partially_received', 'received', 'cancelled'])->default('pending');

    $table->timestamps();

    $table->unique(['purchase_order_id', 'line_no']);
    $table->index('product_id');
});
```

---

### Table: purchase_requisitions
**Migration**: `2025_11_18_000003_create_purchase_requisitions_table.php`

```php
Schema::create('purchase_requisitions', function (Blueprint $table) {
    $table->id();
    $table->string('requisition_number', 50)->unique();
    $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
    $table->foreignId('department_id')->nullable()->constrained('cost_centers')->nullOnDelete();
    $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();

    $table->date('requisition_date');
    $table->date('required_date')->nullable();
    $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

    $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'converted_to_po', 'cancelled'])->default('draft');

    $table->text('justification')->nullable();
    $table->text('notes')->nullable();

    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('approved_at')->nullable();
    $table->text('rejection_reason')->nullable();

    $table->softDeletes();
    $table->timestamps();

    $table->index(['status', 'requisition_date']);
    $table->index('requested_by');
});
```

---

### Table: purchase_requisition_items
**Migration**: `2025_11_18_000004_create_purchase_requisition_items_table.php`

```php
Schema::create('purchase_requisition_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('purchase_requisition_id')->constrained('purchase_requisitions')->cascadeOnDelete();
    $table->integer('line_no')->default(1);

    $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
    $table->foreignId('uom_id')->constrained('uoms')->restrictOnDelete();

    $table->decimal('quantity_requested', 15, 2);
    $table->decimal('estimated_unit_cost', 15, 2)->nullable();
    $table->decimal('estimated_total', 15, 2)->nullable();

    $table->text('purpose')->nullable();

    $table->timestamps();

    $table->unique(['purchase_requisition_id', 'line_no']);
});
```

---

## 2.2 Linking GRN to PO

### Migration: Update GRN table
**Migration**: `2025_11_18_000005_add_po_link_to_grn_table.php`

```php
Schema::table('goods_receipt_notes', function (Blueprint $table) {
    // Change from nullable string to foreign key
    $table->dropColumn('purchase_order_id');
});

Schema::table('goods_receipt_notes', function (Blueprint $table) {
    $table->foreignId('purchase_order_id')
          ->nullable()
          ->after('supplier_id')
          ->constrained('purchase_orders')
          ->nullOnDelete();
});
```

### Migration: Update GRN Items
**Migration**: `2025_11_18_000006_add_po_item_link_to_grn_items_table.php`

```php
Schema::table('goods_receipt_note_items', function (Blueprint $table) {
    $table->foreignId('purchase_order_item_id')
          ->nullable()
          ->after('grn_id')
          ->constrained('purchase_order_items')
          ->nullOnDelete();
});
```

---

## 2.3 Returns Management

### Table: supplier_returns
**Migration**: `2025_11_18_000007_create_supplier_returns_table.php`

```php
Schema::create('supplier_returns', function (Blueprint $table) {
    $table->id();
    $table->string('return_number', 50)->unique();
    $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
    $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
    $table->foreignId('goods_receipt_note_id')->nullable()->constrained('goods_receipt_notes')->nullOnDelete();

    $table->date('return_date');
    $table->enum('return_type', ['defective', 'overshipment', 'wrong_item', 'damaged', 'expired', 'other'])->default('defective');
    $table->enum('status', ['draft', 'approved', 'shipped', 'received_by_supplier', 'credit_received', 'cancelled'])->default('draft');

    $table->decimal('total_amount', 15, 2)->default(0);
    $table->text('reason')->nullable();
    $table->text('notes')->nullable();

    $table->string('courier_name', 100)->nullable();
    $table->string('tracking_number', 100)->nullable();
    $table->date('shipped_date')->nullable();

    $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('approved_at')->nullable();

    $table->softDeletes();
    $table->timestamps();

    $table->index(['supplier_id', 'return_date']);
    $table->index('status');
});
```

**Model Features**:
- Custom ID: `SRN-YYYYMMDD-####` (Supplier Return Note)
- Creates negative stock movement
- Journal entry: Dr AP, Cr Inventory

---

### Table: supplier_return_items
**Migration**: `2025_11_18_000008_create_supplier_return_items_table.php`

```php
Schema::create('supplier_return_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('supplier_return_id')->constrained('supplier_returns')->cascadeOnDelete();
    $table->integer('line_no')->default(1);

    $table->foreignId('grn_item_id')->nullable()->constrained('goods_receipt_note_items')->nullOnDelete();
    $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
    $table->foreignId('stock_batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();
    $table->foreignId('uom_id')->constrained('uoms')->restrictOnDelete();

    $table->decimal('quantity_returned', 15, 2);
    $table->decimal('unit_cost', 15, 2);
    $table->decimal('total_amount', 15, 2);

    $table->text('defect_description')->nullable();

    $table->timestamps();

    $table->unique(['supplier_return_id', 'line_no']);
});
```

---

### Table: customer_returns
**Migration**: `2025_11_18_000009_create_customer_returns_table.php`

```php
Schema::create('customer_returns', function (Blueprint $table) {
    $table->id();
    $table->string('return_number', 50)->unique();
    $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
    $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
    $table->foreignId('sales_invoice_id')->nullable()->constrained('sales_invoices')->nullOnDelete();
    $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();

    $table->date('return_date');
    $table->enum('return_type', ['defective', 'wrong_item', 'damaged', 'expired', 'customer_mistake', 'other'])->default('defective');
    $table->enum('status', ['draft', 'received', 'inspected', 'approved', 'rejected', 'refunded', 'replaced'])->default('draft');

    $table->enum('resolution', ['refund', 'replacement', 'credit_note', 'repair'])->nullable();

    $table->decimal('total_amount', 15, 2)->default(0);
    $table->decimal('refund_amount', 15, 2)->default(0);

    $table->text('reason')->nullable();
    $table->text('inspection_notes')->nullable();
    $table->text('resolution_notes')->nullable();

    $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('inspected_at')->nullable();
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('approved_at')->nullable();

    $table->softDeletes();
    $table->timestamps();

    $table->index(['customer_id', 'return_date']);
    $table->index('status');
});
```

**Model Features**:
- Custom ID: `CRN-YYYYMMDD-####` (Customer Return Note)
- Quality inspection workflow
- Multiple resolution options
- Creates positive stock movement (back to inventory)

---

### Table: customer_return_items
**Migration**: `2025_11_18_000010_create_customer_return_items_table.php`

```php
Schema::create('customer_return_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_return_id')->constrained('customer_returns')->cascadeOnDelete();
    $table->integer('line_no')->default(1);

    $table->foreignId('sales_invoice_item_id')->nullable()->constrained('sales_invoice_items')->nullOnDelete();
    $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
    $table->foreignId('stock_batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();
    $table->foreignId('uom_id')->constrained('uoms')->restrictOnDelete();

    $table->decimal('quantity_returned', 15, 2);
    $table->decimal('quantity_accepted', 15, 2)->default(0);
    $table->decimal('quantity_rejected', 15, 2)->default(0);

    $table->decimal('unit_price', 15, 2);
    $table->decimal('line_total', 15, 2);

    $table->enum('condition', ['good', 'damaged', 'defective', 'expired'])->default('good');
    $table->enum('disposition', ['restock', 'scrap', 'repair', 'return_to_supplier'])->nullable();

    $table->text('defect_description')->nullable();

    $table->timestamps();

    $table->unique(['customer_return_id', 'line_no']);
});
```

---

# PHASE 3: PRICING & TAX MANAGEMENT
**Duration**: 1.5 weeks
**Priority**: HIGH
**Business Impact**: Flexible pricing, tax compliance, promotional management

## 3.1 Price List Management

### Table: price_lists
**Migration**: `2025_11_20_000001_create_price_lists_table.php`

```php
Schema::create('price_lists', function (Blueprint $table) {
    $table->id();
    $table->string('price_list_code', 50)->unique();
    $table->string('name', 100);
    $table->text('description')->nullable();

    $table->enum('type', ['standard', 'wholesale', 'retail', 'distributor', 'promotional', 'customer_specific'])->default('standard');
    $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();

    $table->date('effective_from');
    $table->date('effective_to')->nullable();
    $table->boolean('is_active')->default(true);
    $table->boolean('is_default')->default(false);

    $table->integer('priority')->default(100); // Lower = higher priority

    $table->softDeletes();
    $table->timestamps();

    $table->index(['is_active', 'effective_from', 'effective_to']);
});
```

---

### Table: product_prices
**Migration**: `2025_11_20_000002_create_product_prices_table.php`

```php
Schema::create('product_prices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->foreignId('uom_id')->constrained('uoms')->restrictOnDelete();

    $table->decimal('unit_price', 15, 2);
    $table->decimal('discount_percent', 5, 2)->default(0);
    $table->decimal('minimum_quantity', 15, 2)->default(1); // For tier pricing
    $table->decimal('maximum_quantity', 15, 2)->nullable();

    $table->date('effective_from')->nullable();
    $table->date('effective_to')->nullable();

    $table->timestamps();

    // Prevent duplicate price entries
    $table->unique(['price_list_id', 'product_id', 'uom_id', 'minimum_quantity'], 'uk_price_tier');
    $table->index(['product_id', 'price_list_id']);
});
```

**Features**:
- Tier pricing (quantity breaks)
- Date effectivity
- Multi-UOM pricing

---

### Table: customer_price_lists
**Migration**: `2025_11_20_000003_create_customer_price_lists_table.php`

```php
Schema::create('customer_price_lists', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
    $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();

    $table->date('effective_from');
    $table->date('effective_to')->nullable();
    $table->boolean('is_active')->default(true);

    $table->timestamps();

    $table->unique(['customer_id', 'price_list_id', 'effective_from'], 'uk_customer_price_list');
    $table->index(['customer_id', 'is_active']);
});
```

---

### Table: product_price_history
**Migration**: `2025_11_20_000004_create_product_price_history_table.php`

```php
Schema::create('product_price_history', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

    $table->decimal('old_cost_price', 15, 2);
    $table->decimal('new_cost_price', 15, 2);
    $table->decimal('old_sell_price', 15, 2);
    $table->decimal('new_sell_price', 15, 2);

    $table->date('effective_date');
    $table->text('reason')->nullable();

    $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index(['product_id', 'effective_date']);
});
```

---

### Migration: Update suppliers table
**Migration**: `2025_11_20_000005_update_suppliers_price_list_column.php`

```php
Schema::table('suppliers', function (Blueprint $table) {
    $table->dropColumn('default_price_list');
});

Schema::table('suppliers', function (Blueprint $table) {
    $table->foreignId('default_price_list_id')
          ->nullable()
          ->after('payment_terms')
          ->constrained('price_lists')
          ->nullOnDelete();
});
```

---

## 3.2 Tax Management

### Table: tax_codes
**Migration**: `2025_11_20_000006_create_tax_codes_table.php`

```php
Schema::create('tax_codes', function (Blueprint $table) {
    $table->id();
    $table->string('tax_code', 20)->unique();
    $table->string('name', 100);
    $table->text('description')->nullable();

    $table->enum('tax_type', ['sales_tax', 'gst', 'vat', 'withholding_tax', 'excise', 'customs_duty'])->default('sales_tax');
    $table->enum('calculation_method', ['percentage', 'fixed_amount'])->default('percentage');

    // Accounting integration
    $table->foreignId('tax_payable_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
    $table->foreignId('tax_receivable_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();

    $table->boolean('is_active')->default(true);
    $table->boolean('is_compound')->default(false); // Tax on tax
    $table->boolean('included_in_price')->default(false); // Tax inclusive pricing

    $table->softDeletes();
    $table->timestamps();

    $table->index('is_active');
});
```

---

### Table: tax_rates
**Migration**: `2025_11_20_000007_create_tax_rates_table.php`

```php
Schema::create('tax_rates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tax_code_id')->constrained('tax_codes')->cascadeOnDelete();

    $table->decimal('rate', 5, 2); // Percentage or fixed amount
    $table->date('effective_from');
    $table->date('effective_to')->nullable();

    $table->string('region', 100)->nullable(); // For regional tax variations
    $table->boolean('is_active')->default(true);

    $table->timestamps();

    $table->index(['tax_code_id', 'effective_from', 'effective_to']);
    $table->index(['is_active', 'effective_from']);
});
```

---

### Table: tax_transactions
**Migration**: `2025_11_20_000008_create_tax_transactions_table.php`

```php
Schema::create('tax_transactions', function (Blueprint $table) {
    $table->id();

    // Polymorphic source (sales_invoice, purchase_invoice, etc.)
    $table->string('taxable_type'); // App\Models\SalesInvoice
    $table->unsignedBigInteger('taxable_id');

    $table->foreignId('tax_code_id')->constrained('tax_codes')->restrictOnDelete();
    $table->foreignId('tax_rate_id')->constrained('tax_rates')->restrictOnDelete();

    $table->date('transaction_date');
    $table->decimal('taxable_amount', 15, 2);
    $table->decimal('tax_rate', 5, 2);
    $table->decimal('tax_amount', 15, 2);

    $table->enum('tax_direction', ['payable', 'receivable'])->default('payable');

    $table->timestamps();

    $table->index(['taxable_type', 'taxable_id']);
    $table->index(['tax_code_id', 'transaction_date']);
    $table->index('transaction_date');
});
```

---

### Table: product_tax_mappings
**Migration**: `2025_11_20_000009_create_product_tax_mappings_table.php`

```php
Schema::create('product_tax_mappings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->foreignId('tax_code_id')->constrained('tax_codes')->restrictOnDelete();

    $table->enum('transaction_type', ['sales', 'purchase', 'both'])->default('both');
    $table->boolean('is_active')->default(true);

    $table->timestamps();

    $table->unique(['product_id', 'tax_code_id', 'transaction_type'], 'uk_product_tax');
});
```

---

# PHASE 4: PERFORMANCE, CRM & ENHANCEMENTS
**Duration**: 2.5 weeks
**Priority**: MEDIUM
**Business Impact**: Performance optimization, customer relationships, reporting

## 4.1 Performance Optimization

### Migration: Add Missing Indexes
**Migration**: `2025_11_22_000001_add_performance_indexes.php`

```php
// Journal Entries
Schema::table('journal_entries', function (Blueprint $table) {
    $table->index('posted_at');
    $table->index(['accounting_period_id', 'status']);
    $table->index(['entry_date', 'status']);
});

// Products
Schema::table('products', function (Blueprint $table) {
    $table->index(['supplier_id', 'is_active']);
    $table->index('is_active');
});

// Customers
Schema::table('customers', function (Blueprint $table) {
    $table->index(['credit_limit', 'credit_used']);
    $table->index('receivable_balance');
    $table->index('is_active');
});

// Stock Batches
Schema::table('stock_batches', function (Blueprint $table) {
    $table->index(['expiry_date', 'status']);
    $table->index(['is_promotional', 'promotional_campaign_id']);
    $table->index(['product_id', 'status']);
    $table->index('priority_order');
});

// Stock Movements
Schema::table('stock_movements', function (Blueprint $table) {
    $table->index(['movement_date', 'movement_type']);
    $table->index(['stock_batch_id', 'movement_date']);
    $table->index(['product_id', 'warehouse_id', 'movement_date']);
});

// GRN
Schema::table('goods_receipt_notes', function (Blueprint $table) {
    $table->index(['supplier_id', 'receipt_date']);
    $table->index(['status', 'receipt_date']);
    $table->index(['warehouse_id', 'receipt_date']);
});

// Sales Settlements
Schema::table('sales_settlements', function (Blueprint $table) {
    $table->index(['employee_id', 'settlement_date']);
    $table->index(['status', 'settlement_date']);
});

// Supplier Payments
Schema::table('supplier_payments', function (Blueprint $table) {
    $table->index(['supplier_id', 'payment_date']);
    $table->index(['status', 'payment_date']);
});
```

---

### Migration: Add Check Constraints
**Migration**: `2025_11_22_000002_add_data_integrity_constraints.php`

```php
// Customers: Credit limit validation
Schema::table('customers', function (Blueprint $table) {
    DB::statement('ALTER TABLE customers ADD CONSTRAINT chk_credit_limit CHECK (credit_used <= credit_limit)');
});

// Current Stock: Positive quantities
Schema::table('current_stock', function (Blueprint $table) {
    DB::statement('ALTER TABLE current_stock ADD CONSTRAINT chk_stock_positive CHECK (quantity_on_hand >= 0)');
});

// Products: Pricing validation
Schema::table('products', function (Blueprint $table) {
    DB::statement('ALTER TABLE products ADD CONSTRAINT chk_product_pricing CHECK (unit_sell_price >= cost_price OR cost_price = 0)');
});

// Sales Orders: Amount validations
Schema::table('sales_orders', function (Blueprint $table) {
    DB::statement('ALTER TABLE sales_orders ADD CONSTRAINT chk_order_amounts CHECK (total_amount >= 0 AND subtotal_amount >= 0)');
});

// Stock Batches: Quantity validation
Schema::table('stock_batches', function (Blueprint $table) {
    DB::statement('ALTER TABLE stock_batches ADD CONSTRAINT chk_batch_priority CHECK (priority_order >= 1 AND priority_order <= 999)');
});
```

---

## 4.2 CRM Features

### Table: customer_contacts
**Migration**: `2025_11_22_000003_create_customer_contacts_table.php`

```php
Schema::create('customer_contacts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

    $table->string('contact_name', 100);
    $table->string('designation', 100)->nullable();
    $table->string('email', 100)->nullable();
    $table->string('phone', 20)->nullable();
    $table->string('mobile', 20)->nullable();
    $table->boolean('is_primary')->default(false);
    $table->boolean('is_active')->default(true);

    $table->timestamps();

    $table->index(['customer_id', 'is_primary']);
});
```

---

### Table: customer_visits
**Migration**: `2025_11_22_000004_create_customer_visits_table.php`

```php
Schema::create('customer_visits', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
    $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();

    $table->date('visit_date');
    $table->time('check_in_time')->nullable();
    $table->time('check_out_time')->nullable();

    $table->enum('visit_type', ['routine', 'complaint', 'sales', 'collection', 'survey'])->default('routine');
    $table->enum('visit_status', ['planned', 'completed', 'cancelled', 'no_show'])->default('planned');

    $table->text('purpose')->nullable();
    $table->text('notes')->nullable();
    $table->text('outcome')->nullable();

    // Location tracking
    $table->decimal('check_in_latitude', 10, 8)->nullable();
    $table->decimal('check_in_longitude', 11, 8)->nullable();

    $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();

    $table->timestamps();

    $table->index(['customer_id', 'visit_date']);
    $table->index(['employee_id', 'visit_date']);
});
```

---

### Table: customer_complaints
**Migration**: `2025_11_22_000005_create_customer_complaints_table.php`

```php
Schema::create('customer_complaints', function (Blueprint $table) {
    $table->id();
    $table->string('complaint_number', 50)->unique();
    $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
    $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
    $table->foreignId('sales_invoice_id')->nullable()->constrained('sales_invoices')->nullOnDelete();

    $table->date('complaint_date');
    $table->enum('category', ['product_quality', 'delivery', 'pricing', 'service', 'documentation', 'other'])->default('product_quality');
    $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
    $table->enum('status', ['open', 'acknowledged', 'investigating', 'resolved', 'closed', 'escalated'])->default('open');

    $table->text('description');
    $table->text('investigation_notes')->nullable();
    $table->text('resolution')->nullable();

    $table->foreignId('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('resolved_at')->nullable();

    $table->integer('resolution_days')->nullable();

    $table->softDeletes();
    $table->timestamps();

    $table->index(['customer_id', 'complaint_date']);
    $table->index(['status', 'priority']);
});
```

---

### Table: sales_targets
**Migration**: `2025_11_22_000006_create_sales_targets_table.php`

```php
Schema::create('sales_targets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

    $table->integer('year');
    $table->integer('month')->nullable(); // Null = annual target
    $table->integer('quarter')->nullable(); // 1-4

    $table->decimal('target_amount', 15, 2)->default(0);
    $table->decimal('achieved_amount', 15, 2)->default(0);
    $table->decimal('achievement_percent', 5, 2)->default(0);

    $table->integer('target_orders')->default(0);
    $table->integer('achieved_orders')->default(0);

    $table->integer('target_new_customers')->default(0);
    $table->integer('achieved_new_customers')->default(0);

    $table->timestamps();

    $table->unique(['employee_id', 'year', 'month'], 'uk_employee_target');
    $table->index(['year', 'month']);
});
```

---

### Table: sales_routes
**Migration**: `2025_11_22_000007_create_sales_routes_table.php`

```php
Schema::create('sales_routes', function (Blueprint $table) {
    $table->id();
    $table->string('route_code', 50)->unique();
    $table->string('name', 100);
    $table->text('description')->nullable();

    $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
    $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();

    $table->string('area', 100)->nullable();
    $table->string('city', 100)->nullable();

    $table->enum('frequency', ['daily', 'weekly', 'biweekly', 'monthly'])->default('weekly');
    $table->set('visit_days', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])->nullable();

    $table->integer('estimated_duration_minutes')->nullable();
    $table->decimal('estimated_distance_km', 10, 2)->nullable();

    $table->boolean('is_active')->default(true);

    $table->softDeletes();
    $table->timestamps();

    $table->index(['employee_id', 'is_active']);
});
```

---

### Table: route_customers
**Migration**: `2025_11_22_000008_create_route_customers_table.php`

```php
Schema::create('route_customers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('route_id')->constrained('sales_routes')->cascadeOnDelete();
    $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

    $table->integer('sequence_order')->default(1);
    $table->integer('estimated_visit_duration_minutes')->default(30);

    $table->boolean('is_active')->default(true);

    $table->timestamps();

    $table->unique(['route_id', 'customer_id']);
    $table->index(['route_id', 'sequence_order']);
});
```

---

## 4.3 Reporting & Analytics Tables

### Table: sales_summary_daily
**Migration**: `2025_11_22_000009_create_sales_summary_daily_table.php`

```php
Schema::create('sales_summary_daily', function (Blueprint $table) {
    $table->id();
    $table->date('summary_date');
    $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
    $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();

    $table->integer('total_orders')->default(0);
    $table->integer('total_invoices')->default(0);
    $table->integer('total_customers')->default(0);

    $table->decimal('total_sales_amount', 15, 2)->default(0);
    $table->decimal('total_cogs', 15, 2)->default(0);
    $table->decimal('total_margin', 15, 2)->default(0);
    $table->decimal('margin_percent', 5, 2)->default(0);

    $table->decimal('cash_sales', 15, 2)->default(0);
    $table->decimal('credit_sales', 15, 2)->default(0);

    $table->integer('returns_count')->default(0);
    $table->decimal('returns_amount', 15, 2)->default(0);

    $table->timestamp('last_updated')->nullable();

    $table->unique(['summary_date', 'warehouse_id', 'employee_id'], 'uk_daily_summary');
    $table->index('summary_date');
});
```

**Scheduled Job**: Generate daily at end of day

---

### Table: inventory_valuation_monthly
**Migration**: `2025_11_22_000010_create_inventory_valuation_monthly_table.php`

```php
Schema::create('inventory_valuation_monthly', function (Blueprint $table) {
    $table->id();
    $table->date('month_year'); // First day of month
    $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

    $table->decimal('opening_quantity', 15, 2)->default(0);
    $table->decimal('opening_value', 15, 2)->default(0);

    $table->decimal('received_quantity', 15, 2)->default(0);
    $table->decimal('received_value', 15, 2)->default(0);

    $table->decimal('issued_quantity', 15, 2)->default(0);
    $table->decimal('issued_value', 15, 2)->default(0);

    $table->decimal('adjusted_quantity', 15, 2)->default(0);
    $table->decimal('adjusted_value', 15, 2)->default(0);

    $table->decimal('closing_quantity', 15, 2)->default(0);
    $table->decimal('closing_value', 15, 2)->default(0);
    $table->decimal('average_cost', 15, 2)->default(0);

    $table->timestamp('calculated_at')->nullable();

    $table->unique(['month_year', 'warehouse_id', 'product_id'], 'uk_monthly_valuation');
    $table->index('month_year');
});
```

**Scheduled Job**: Generate monthly

---

## 4.4 Advanced Features

### Table: product_suppliers (Multi-supplier support)
**Migration**: `2025_11_22_000011_create_product_suppliers_table.php`

```php
Schema::create('product_suppliers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();

    $table->string('supplier_sku', 100)->nullable();
    $table->decimal('supplier_unit_cost', 15, 2)->nullable();
    $table->foreignId('supplier_uom_id')->nullable()->constrained('uoms')->nullOnDelete();

    $table->integer('lead_time_days')->nullable();
    $table->decimal('minimum_order_qty', 15, 2)->nullable();
    $table->decimal('order_multiple', 15, 2)->default(1);

    $table->enum('preference', ['preferred', 'alternate', 'backup'])->default('alternate');
    $table->boolean('is_active')->default(true);

    $table->date('last_purchase_date')->nullable();
    $table->decimal('last_purchase_price', 15, 2)->nullable();

    $table->timestamps();

    $table->unique(['product_id', 'supplier_id']);
    $table->index(['supplier_id', 'is_active']);
});
```

---

### Table: product_warehouse_settings
**Migration**: `2025_11_22_000012_create_product_warehouse_settings_table.php`

```php
Schema::create('product_warehouse_settings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();

    $table->decimal('minimum_stock_level', 15, 2)->default(0);
    $table->decimal('maximum_stock_level', 15, 2)->default(0);
    $table->decimal('reorder_point', 15, 2)->default(0);
    $table->decimal('reorder_quantity', 15, 2)->default(0);

    $table->string('storage_bin', 50)->nullable();
    $table->string('storage_location', 100)->nullable();

    $table->boolean('allow_negative_stock')->default(false);
    $table->boolean('track_by_batch')->default(true);

    $table->timestamps();

    $table->unique(['product_id', 'warehouse_id']);
});
```

---

### Table: batch_traceability_log
**Migration**: `2025_11_22_000013_create_batch_traceability_log_table.php`

```php
Schema::create('batch_traceability_log', function (Blueprint $table) {
    $table->id();
    $table->foreignId('stock_batch_id')->constrained('stock_batches')->cascadeOnDelete();

    $table->string('event_type', 50); // 'received', 'issued', 'transferred', 'adjusted', 'returned'
    $table->string('reference_type')->nullable();
    $table->unsignedBigInteger('reference_id')->nullable();

    $table->date('event_date');
    $table->decimal('quantity', 15, 2);
    $table->decimal('balance_after', 15, 2);

    $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
    $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
    $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

    $table->text('notes')->nullable();
    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

    $table->timestamps();

    $table->index(['stock_batch_id', 'event_date']);
    $table->index(['reference_type', 'reference_id']);
});
```

---

# IMPLEMENTATION SCHEDULE

## Week 1-2: Phase 1 (Sales & AR)
```
Day 1-2:   Sales Orders + Items + Reservations
Day 3-4:   Sales Invoices + Items
Day 5-6:   Customer Receipts + Allocations
Day 7-8:   Supporting tables (credit limits, aging)
Day 9-10:  Testing, Services, Controllers
```

## Week 3-4: Phase 2 (PO & Returns)
```
Day 1-2:   Purchase Orders + Items + Requisitions
Day 3-4:   Link GRN to PO
Day 5-6:   Supplier Returns + Items
Day 7-8:   Customer Returns + Items
Day 9-10:  Testing, Services, Controllers
```

## Week 5: Phase 3 (Pricing & Tax)
```
Day 1-2:   Price Lists + Product Prices + Customer Prices
Day 3-4:   Tax Codes + Rates + Transactions
Day 5-7:   Testing, Integration, Tax Calculations
```

## Week 6-8: Phase 4 (Performance & CRM)
```
Week 6:    Indexes, Constraints, CRM tables
Week 7:    Reporting tables, Advanced features
Week 8:    Testing, Documentation, Deployment
```

---

# SERVICE LAYER UPDATES REQUIRED

## New Services to Create

### 1. SalesOrderService
```php
- createOrder(array $data): SalesOrder
- confirmOrder(SalesOrder $order): bool
- fulfillOrder(SalesOrder $order, array $items): GoodsIssue
- cancelOrder(SalesOrder $order, string $reason): bool
- reserveStock(SalesOrder $order): bool
- releaseReservation(SalesOrder $order): bool
```

### 2. SalesInvoiceService
```php
- createInvoice(SalesOrder $order): SalesInvoice
- postInvoice(SalesInvoice $invoice): array
- reverseInvoice(SalesInvoice $invoice, string $reason): SalesInvoice
- calculateCOGS(SalesInvoiceItem $item): decimal
- createJournalEntry(SalesInvoice $invoice): JournalEntry
```

### 3. CustomerReceiptService
```php
- recordReceipt(array $data): CustomerReceipt
- allocateReceipt(CustomerReceipt $receipt, array $allocations): bool
- autoAllocate(CustomerReceipt $receipt): array
- markChequeCleared(CustomerReceipt $receipt): bool
- markChequeBounced(CustomerReceipt $receipt): bool
```

### 4. PurchaseOrderService
```php
- createPO(array $data): PurchaseOrder
- approvePO(PurchaseOrder $po): bool
- convertToGRN(PurchaseOrder $po): GoodsReceiptNote
- cancelPO(PurchaseOrder $po, string $reason): bool
```

### 5. ReturnsService
```php
- createCustomerReturn(array $data): CustomerReturn
- inspectReturn(CustomerReturn $return, array $inspection): bool
- processRefund(CustomerReturn $return): CustomerReceipt
- createSupplierReturn(array $data): SupplierReturn
```

### 6. PricingService
```php
- getPrice(Product $product, Customer $customer, $quantity): decimal
- applyPriceList(Customer $customer, PriceList $priceList): bool
- calculateTierPrice(Product $product, $quantity): decimal
```

### 7. TaxService
```php
- calculateTax(decimal $amount, TaxCode $taxCode): decimal
- getCurrentRate(TaxCode $taxCode): TaxRate
- recordTaxTransaction(Model $source, array $taxes): void
```

---

# CONTROLLER UPDATES REQUIRED

## New Controllers to Create

1. **SalesOrderController** - CRUD + confirm, fulfill, cancel
2. **SalesInvoiceController** - CRUD + post, reverse
3. **CustomerReceiptController** - CRUD + allocate, clear cheque
4. **PurchaseOrderController** - CRUD + approve, send to supplier
5. **PurchaseRequisitionController** - CRUD + approve, convert to PO
6. **CustomerReturnController** - CRUD + inspect, refund
7. **SupplierReturnController** - CRUD + approve, ship
8. **PriceListController** - CRUD + assign to customers
9. **TaxCodeController** - CRUD + manage rates
10. **CustomerVisitController** - CRUD + check-in/out
11. **SalesTargetController** - CRUD + achievement tracking

---

# BLADE VIEWS REQUIRED

## New View Directories

```
resources/views/
├── sales-orders/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── edit.blade.php
│   ├── show.blade.php
│   └── partials/form-fields.blade.php
├── sales-invoices/
├── customer-receipts/
├── purchase-orders/
├── purchase-requisitions/
├── customer-returns/
├── supplier-returns/
├── price-lists/
├── tax-codes/
├── customer-visits/
└── sales-targets/
```

---

# ROUTES TO ADD

```php
// Sales Orders
Route::resource('sales-orders', SalesOrderController::class);
Route::post('sales-orders/{order}/confirm', [SalesOrderController::class, 'confirm']);
Route::post('sales-orders/{order}/fulfill', [SalesOrderController::class, 'fulfill']);
Route::post('sales-orders/{order}/cancel', [SalesOrderController::class, 'cancel']);

// Sales Invoices
Route::resource('sales-invoices', SalesInvoiceController::class);
Route::post('sales-invoices/{invoice}/post', [SalesInvoiceController::class, 'post']);
Route::post('sales-invoices/{invoice}/reverse', [SalesInvoiceController::class, 'reverse']);

// Customer Receipts
Route::resource('customer-receipts', CustomerReceiptController::class);
Route::post('customer-receipts/{receipt}/allocate', [CustomerReceiptController::class, 'allocate']);

// Purchase Orders
Route::resource('purchase-orders', PurchaseOrderController::class);
Route::post('purchase-orders/{po}/approve', [PurchaseOrderController::class, 'approve']);

// ... etc
```

---

# DATA MIGRATION CONSIDERATIONS

## Existing Data Updates

### 1. Update Products Table
- Add default tax code
- Link to price lists

### 2. Update Customers Table
- Assign default price lists
- Set tax preferences

### 3. Link Existing GRNs to POs (if applicable)
- Retroactive PO creation or leave null

---

# TESTING STRATEGY

## Unit Tests Required

1. **Service Tests** (40+ test files)
   - Each service method
   - Edge cases
   - Validation failures

2. **Model Tests**
   - Relationships
   - Scopes
   - Accessors/Mutators

3. **Integration Tests**
   - Complete workflows (Order → Invoice → Payment)
   - Stock reservation → fulfillment
   - Returns processing

## Feature Tests Required

1. **Order to Cash Cycle**
2. **Procure to Pay Cycle**
3. **Returns Processing**
4. **Tax Calculations**
5. **Price List Application**

---

# DOCUMENTATION UPDATES REQUIRED

1. Update **README.md** with new features
2. Create **SALES_ORDER_GUIDE.md**
3. Create **PURCHASE_ORDER_GUIDE.md**
4. Create **PRICING_GUIDE.md**
5. Create **TAX_CONFIGURATION_GUIDE.md**
6. Update **CLAUDE.md** with new schema
7. Create **API_DOCUMENTATION.md** (if building API)

---

# DEPLOYMENT CHECKLIST

## Pre-Deployment

- [ ] Backup production database
- [ ] Test all migrations in staging
- [ ] Review all foreign key constraints
- [ ] Verify index creation
- [ ] Test rollback procedures

## Deployment Order

1. Phase 1 migrations (Sales & AR)
2. Deploy services and controllers
3. Test in production
4. Phase 2 migrations (PO & Returns)
5. Test procurement workflow
6. Phase 3 migrations (Pricing & Tax)
7. Configure tax codes and price lists
8. Phase 4 migrations (Performance & CRM)
9. Final testing
10. User training

## Post-Deployment

- [ ] Monitor performance
- [ ] Check query execution times
- [ ] Verify data integrity
- [ ] User acceptance testing
- [ ] Update documentation

---

# RISK MITIGATION

## High Risk Areas

1. **Customer Credit Limits** - Enforce strictly to prevent overselling
2. **Stock Reservations** - Must be reliable for order fulfillment
3. **Tax Calculations** - Critical for compliance
4. **Payment Allocations** - Must match exactly
5. **Inventory Valuation** - FIFO/LIFO accuracy

## Mitigation Strategies

1. Comprehensive testing
2. Database constraints enforcement
3. Audit trails everywhere
4. Rollback procedures documented
5. Phased rollout (not big bang)

---

# SUCCESS METRICS

## Key Performance Indicators

1. **Order Processing Time** - Target: < 2 minutes
2. **Invoice Generation** - Target: < 30 seconds
3. **Payment Allocation** - Target: < 1 minute
4. **Tax Calculation Accuracy** - Target: 100%
5. **Reporting Speed** - Target: < 5 seconds

## Business Metrics

1. **AR Days Outstanding** - Reduce by 20%
2. **PO to GRN Variance** - < 5%
3. **Customer Complaints** - Track and reduce
4. **Sales Target Achievement** - Visible and actionable
5. **Inventory Accuracy** - > 98%

---

# CONCLUSION

This implementation plan provides a complete roadmap to transform MoonTrader from a 7.5/10 to a 9.5/10 database maturity level.

**Total Implementation Time**: 6-8 weeks
**Total New Tables**: 27+ tables
**Total New Indexes**: 50+ indexes
**Total New Services**: 7+ services
**Total New Controllers**: 11+ controllers

**Estimated Development Effort**: 320-400 hours

---

**Next Actions**:
1. Review and approve this plan
2. Set up development environment
3. Create Git feature branches
4. Begin Phase 1 implementation
5. Schedule testing milestones

---

*Generated for MoonTrader Inventory Management System*
*Last Updated: November 15, 2025*
