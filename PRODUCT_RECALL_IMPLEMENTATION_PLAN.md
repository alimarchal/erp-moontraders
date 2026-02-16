# Product Recall/Return Management Implementation Plan

## Context

### Problem Statement
The MoonTraders application currently lacks a mechanism to handle **product recalls** (when suppliers request products be returned). The business need is:

- **Supplier-Initiated Recalls**: Supplier company sometimes recalls products due to quality issues, expiry concerns, or manufacturing defects
- **Batch-Specific Returns**: Need to return specific batches (not entire GRNs) based on:
  - Specific batch codes
  - Manufacturing date ranges
  - Expiry date ranges
  - Manual selection
- **Partial GRN Returns**: Sometimes only 1-2 products from a multi-product GRN need to be recalled
- **Production Safety**: Must not break existing functionality or reporting
- **Audit Trail**: Complete traceability from recall → stock adjustment → supplier claim

### Current System Strengths

The application already has excellent foundation:

1. ✅ **Batch Tracking**: `StockBatch` model with `batch_code`, `expiry_date`, `manufacturing_date`, `supplier_batch_number`
2. ✅ **Status Support**: `StockBatch.status` enum already includes `'recalled'` option (unused)
3. ✅ **Stock Adjustment Tables**: Migrations exist for `stock_adjustments` and `stock_adjustment_items` (NO models/controllers yet)
4. ✅ **ClaimRegister**: Existing model for supplier claims/credit notes
5. ✅ **Immutable Ledger**: `StockLedgerEntry` and `InventoryLedgerEntry` provide complete audit trail
6. ✅ **Inventory Services**: `InventoryService`, `InventoryLedgerService` with proven patterns
7. ✅ **Journal Entry Integration**: Automatic GL posting for inventory transactions

### Implementation Strategy

**Use Stock Adjustments as the foundation**, with Product Recalls as a specialized workflow:

```
ProductRecall (User-facing document)
    ↓ (on posting)
StockAdjustment (created automatically with adjustment_type='recall')
    ↓ (on posting)
Inventory Updates (stock ledgers, current stock, journal entries)
```

This approach:
- Leverages existing infrastructure
- Maintains backward compatibility
- Keeps recall-specific metadata separate
- Allows manual stock adjustments to coexist

---

## Critical Files for Implementation

### Services (Core Business Logic)
1. **`app/Services/ProductRecallService.php`** (NEW) - Recall workflow orchestration
2. **`app/Services/StockAdjustmentService.php`** (NEW) - Foundation for all adjustments
3. **`app/Services/InventoryService.php`** (REFERENCE) - Existing patterns at lines 655-710 (`syncCurrentStockFromValuationLayers`)
4. **`app/Services/InventoryLedgerService.php`** (REFERENCE) - Line 212-236 (`recordAdjustment`)

### Models
5. **`app/Models/StockAdjustment.php`** (NEW) - Complete the existing migration
6. **`app/Models/StockAdjustmentItem.php`** (NEW)
7. **`app/Models/ProductRecall.php`** (NEW)
8. **`app/Models/ProductRecallItem.php`** (NEW)

### Controllers
9. **`app/Http/Controllers/ProductRecallController.php`** (NEW)
10. **`app/Http/Controllers/StockAdjustmentController.php`** (NEW)

### Migrations
11. **`database/migrations/YYYY_MM_DD_extend_stock_adjustments.php`** (NEW) - Add `recall` to enum, add `product_recall_id`
12. **`database/migrations/YYYY_MM_DD_extend_stock_adjustment_items.php`** (NEW) - Add `stock_batch_id`, `grn_item_id`
13. **`database/migrations/YYYY_MM_DD_create_product_recalls.php`** (NEW)
14. **`database/migrations/YYYY_MM_DD_create_product_recall_items.php`** (NEW)

---

## Implementation Phases

### Phase 1: Stock Adjustment Foundation (Week 1)

**Goal**: Complete the incomplete stock adjustment feature

#### Step 1.1: Create Models

**File**: `app/Models/StockAdjustment.php`
```php
<?php
namespace App\Models;

use App\Traits\UserTracking;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use HasFactory, SoftDeletes, UserTracking;

    protected $fillable = [
        'adjustment_number', 'adjustment_date', 'warehouse_id',
        'adjustment_type', 'status', 'product_recall_id',
        'created_by', 'posted_by', 'posted_at', 'journal_entry_id',
        'reason', 'notes',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'posted_at' => 'datetime',
    ];

    // Relationships
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function items(): HasMany { return $this->hasMany(StockAdjustmentItem::class); }
    public function productRecall(): BelongsTo { return $this->belongsTo(ProductRecall::class); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function postedBy(): BelongsTo { return $this->belongsTo(User::class, 'posted_by'); }

    // Status helpers
    public function isDraft(): bool { return $this->status === 'draft'; }
    public function isPosted(): bool { return $this->status === 'posted'; }
}
```

**File**: `app/Models/StockAdjustmentItem.php`
```php
<?php
namespace App\Models;

class StockAdjustmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_adjustment_id', 'product_id', 'stock_batch_id', 'grn_item_id',
        'system_quantity', 'actual_quantity', 'adjustment_quantity',
        'unit_cost', 'adjustment_value', 'uom_id', 'notes',
    ];

    protected $casts = [
        'system_quantity' => 'decimal:3',
        'actual_quantity' => 'decimal:3',
        'adjustment_quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'adjustment_value' => 'decimal:2',
    ];

    public function stockAdjustment(): BelongsTo { return $this->belongsTo(StockAdjustment::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function stockBatch(): BelongsTo { return $this->belongsTo(StockBatch::class); }
    public function grnItem(): BelongsTo { return $this->belongsTo(GoodsReceiptNoteItem::class); }
    public function uom(): BelongsTo { return $this->belongsTo(Uom::class); }
}
```

#### Step 1.2: Extend Existing Migrations

**File**: `database/migrations/2026_02_XX_extend_stock_adjustments.php`
```php
public function up()
{
    // Add 'recall' to adjustment_type enum
    DB::statement("ALTER TABLE stock_adjustments
        MODIFY adjustment_type ENUM('damage', 'theft', 'count_variance', 'expiry', 'recall', 'other')");

    Schema::table('stock_adjustments', function (Blueprint $table) {
        $table->foreignId('product_recall_id')->nullable()->after('warehouse_id')
            ->constrained('product_recalls')->nullOnDelete();
        $table->timestamp('posted_at')->nullable()->after('status');
    });
}
```

**File**: `database/migrations/2026_02_XX_extend_stock_adjustment_items.php`
```php
public function up()
{
    Schema::table('stock_adjustment_items', function (Blueprint $table) {
        $table->foreignId('stock_batch_id')->nullable()->after('product_id')
            ->constrained('stock_batches')->nullOnDelete();
        $table->foreignId('grn_item_id')->nullable()->after('stock_batch_id')
            ->constrained('goods_receipt_note_items')->nullOnDelete();

        $table->index('stock_batch_id');
        $table->index('grn_item_id');
    });
}
```

#### Step 1.3: Create StockAdjustmentService

**File**: `app/Services/StockAdjustmentService.php`

**Key Methods**:
- `createAdjustment(array $data): array`
- `postAdjustment(StockAdjustment $adjustment): array`
- `generateAdjustmentNumber(): string` → "SA-2026-0001"

**Posting Logic** (follow InventoryService pattern):
```php
public function postAdjustment(StockAdjustment $adjustment): array
{
    DB::beginTransaction();

    foreach ($adjustment->items as $item) {
        // 1. Create InventoryLedgerEntry using existing service
        $ledgerService = app(InventoryLedgerService::class);
        $ledgerService->recordAdjustment(
            productId: $item->product_id,
            warehouseId: $adjustment->warehouse_id,
            vehicleId: null,
            debitQty: $item->adjustment_quantity > 0 ? $item->adjustment_quantity : 0,
            creditQty: $item->adjustment_quantity < 0 ? abs($item->adjustment_quantity) : 0,
            unitCost: $item->unit_cost,
            date: $adjustment->adjustment_date,
            notes: "{$adjustment->adjustment_type} - {$adjustment->reason}",
            batchId: $item->stock_batch_id
        );

        // 2. Update CurrentStockByBatch
        $stockByBatch = CurrentStockByBatch::where('stock_batch_id', $item->stock_batch_id)
            ->where('warehouse_id', $adjustment->warehouse_id)
            ->lockForUpdate()
            ->first();

        if ($stockByBatch) {
            $stockByBatch->quantity_on_hand += $item->adjustment_quantity;
            if ($stockByBatch->quantity_on_hand <= 0) {
                $stockByBatch->quantity_on_hand = 0;
                $stockByBatch->status = 'depleted';
            }
            $stockByBatch->total_value = $stockByBatch->quantity_on_hand * $stockByBatch->unit_cost;
            $stockByBatch->save();
        }

        // 3. Sync CurrentStock (use existing method from InventoryService)
        $inventoryService = app(InventoryService::class);
        $inventoryService->syncCurrentStockFromValuationLayers($item->product_id, $adjustment->warehouse_id);

        // 4. Update StockBatch status if fully depleted/recalled
        if ($item->adjustment_quantity < 0) {
            $remainingQty = CurrentStockByBatch::where('stock_batch_id', $item->stock_batch_id)
                ->sum('quantity_on_hand');

            if ($remainingQty <= 0) {
                $batch = StockBatch::find($item->stock_batch_id);
                $batch->status = $adjustment->adjustment_type === 'recall' ? 'recalled' : 'depleted';
                $batch->is_active = false;
                $batch->save();
            }
        }

        // 5. Create StockLedgerEntry (immutable audit trail)
        // 6. Update StockValuationLayer
    }

    // 7. Create Journal Entry
    $journalEntry = $this->createAdjustmentJournalEntry($adjustment);

    // 8. Update status
    $adjustment->update([
        'status' => 'posted',
        'posted_at' => now(),
        'posted_by' => auth()->id(),
        'journal_entry_id' => $journalEntry?->id,
    ]);

    DB::commit();

    return ['success' => true, 'message' => "Adjustment {$adjustment->adjustment_number} posted"];
}
```

**GL Posting Pattern** (follow InventoryService::createGrnJournalEntry at lines 133-307):
```php
protected function createAdjustmentJournalEntry(StockAdjustment $adjustment): ?JournalEntry
{
    $inventoryAccount = ChartOfAccount::where('account_code', '1151')->first(); // Stock In Hand
    $warehouseCostCenter = CostCenter::where('code', 'CC006')->first();

    // Different expense accounts per adjustment type
    $expenseAccount = match($adjustment->adjustment_type) {
        'recall' => ChartOfAccount::where('account_code', '5280')->first(),  // Stock Loss on Recalls
        'damage' => ChartOfAccount::where('account_code', '5281')->first(),  // Stock Loss - Damage
        'theft' => ChartOfAccount::where('account_code', '5282')->first(),   // Stock Loss - Theft
        'expiry' => ChartOfAccount::where('account_code', '5283')->first(),  // Stock Loss - Expiry
        default => ChartOfAccount::where('account_code', '5284')->first(),   // Stock Loss - Other
    };

    if (!$inventoryAccount || !$expenseAccount) {
        Log::warning("Required accounts not found for adjustment {$adjustment->id}");
        return null;
    }

    $totalValue = $adjustment->items->sum('adjustment_value');
    $isNegativeAdjustment = $totalValue < 0;
    $absValue = abs($totalValue);

    if ($absValue == 0) return null;

    $journalLines = [];

    if ($isNegativeAdjustment) {
        // Stock reduction: Dr. Expense, Cr. Inventory
        $journalLines[] = [
            'line_no' => 1,
            'account_id' => $expenseAccount->id,
            'debit' => $absValue,
            'credit' => 0,
            'description' => ucfirst($adjustment->adjustment_type) . " - {$adjustment->reason}",
            'cost_center_id' => $warehouseCostCenter->id,
        ];
        $journalLines[] = [
            'line_no' => 2,
            'account_id' => $inventoryAccount->id,
            'debit' => 0,
            'credit' => $absValue,
            'description' => "Inventory reduction - {$adjustment->adjustment_number}",
            'cost_center_id' => $warehouseCostCenter->id,
        ];
    }

    $journalEntryData = [
        'entry_date' => $adjustment->adjustment_date->toDateString(),
        'reference' => $adjustment->adjustment_number,
        'description' => "Stock Adjustment - " . ucfirst($adjustment->adjustment_type),
        'lines' => $journalLines,
        'auto_post' => true,
    ];

    $accountingService = app(AccountingService::class);
    $result = $accountingService->createJournalEntry($journalEntryData);

    return $result['success'] ? $result['data'] : null;
}
```

#### Step 1.4: Create StockAdjustmentController

**File**: `app/Http/Controllers/StockAdjustmentController.php`

**Methods**:
- `index()` - List with filters
- `create()` - Form
- `store(StoreStockAdjustmentRequest)` - Save draft
- `show($id)` - View
- `edit($id)` - Edit draft
- `update(UpdateStockAdjustmentRequest, $id)` - Update
- `destroy($id)` - Delete draft
- `post($id)` - Post adjustment (calls service)

**Permissions**:
- `stock-adjustment-list`
- `stock-adjustment-create`
- `stock-adjustment-edit`
- `stock-adjustment-delete`
- `stock-adjustment-post`

**Pattern**: Follow `GoodsReceiptNoteController` structure (lines 21-100)

#### Step 1.5: Create Chart of Accounts

**Seeder**: `database/seeders/RecallAccountsSeeder.php`

Create GL accounts:
- **5280** - Stock Loss on Recalls
- **5281** - Stock Loss - Damage
- **5282** - Stock Loss - Theft
- **5283** - Stock Loss - Expiry
- **5284** - Stock Loss - Other

#### Step 1.6: Testing

**Tests**: `tests/Feature/StockAdjustmentTest.php`

```php
it('creates a stock adjustment successfully', function() {
    // Test draft creation
});

it('posts a stock adjustment and updates inventory', function() {
    // Test posting → stock reduced → ledger entry created
});

it('creates journal entry on posting', function() {
    // Test GL impact
});

it('validates stock availability before posting', function() {
    // Test cannot adjust more than available
});
```

**Manual Testing Checklist**:
- [ ] Create damage adjustment (10 units)
- [ ] Post adjustment
- [ ] Verify CurrentStock reduced by 10
- [ ] Verify InventoryLedgerEntry created with TYPE_ADJUSTMENT
- [ ] Verify JournalEntry posted (Dr. 5281, Cr. 1151)
- [ ] Check DailyStockRegister shows correctly
- [ ] Check InventoryLedgerReport shows adjustment

---

### Phase 2: Product Recall Feature (Week 2)

**Goal**: Add recall-specific workflow on top of stock adjustments

#### Step 2.1: Create Models

**File**: `app/Models/ProductRecall.php`
```php
<?php
namespace App\Models;

class ProductRecall extends Model
{
    use HasFactory, SoftDeletes, UserTracking;

    protected $fillable = [
        'recall_number', 'recall_date', 'supplier_id', 'warehouse_id', 'grn_id',
        'recall_type', 'status', 'total_quantity_recalled', 'total_value',
        'reason', 'supplier_notification_sent_at', 'claim_register_id',
        'stock_adjustment_id', 'posted_by', 'posted_at', 'notes',
    ];

    protected $casts = [
        'recall_date' => 'date',
        'total_quantity_recalled' => 'decimal:3',
        'total_value' => 'decimal:2',
        'supplier_notification_sent_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function grn(): BelongsTo { return $this->belongsTo(GoodsReceiptNote::class); }
    public function items(): HasMany { return $this->hasMany(ProductRecallItem::class); }
    public function claimRegister(): BelongsTo { return $this->belongsTo(ClaimRegister::class); }
    public function stockAdjustment(): HasOne { return $this->hasOne(StockAdjustment::class); }
    public function postedBy(): BelongsTo { return $this->belongsTo(User::class, 'posted_by'); }

    public function isDraft(): bool { return $this->status === 'draft'; }
    public function isPosted(): bool { return $this->status === 'posted'; }
}
```

**File**: `app/Models/ProductRecallItem.php`
```php
<?php
namespace App\Models;

class ProductRecallItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_recall_id', 'product_id', 'stock_batch_id', 'grn_item_id',
        'quantity_recalled', 'unit_cost', 'total_value', 'reason', 'notes',
    ];

    protected $casts = [
        'quantity_recalled' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_value' => 'decimal:2',
    ];

    public function productRecall(): BelongsTo { return $this->belongsTo(ProductRecall::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function stockBatch(): BelongsTo { return $this->belongsTo(StockBatch::class); }
    public function grnItem(): BelongsTo { return $this->belongsTo(GoodsReceiptNoteItem::class); }
}
```

#### Step 2.2: Create Migrations

**File**: `database/migrations/2026_02_XX_create_product_recalls.php`
```php
Schema::create('product_recalls', function (Blueprint $table) {
    $table->id();
    $table->string('recall_number')->unique();
    $table->date('recall_date');
    $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
    $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
    $table->foreignId('grn_id')->nullable()->constrained('goods_receipt_notes')->nullOnDelete();
    $table->enum('recall_type', ['supplier_initiated', 'quality_issue', 'expiry', 'other'])->default('supplier_initiated');
    $table->enum('status', ['draft', 'posted', 'completed', 'cancelled'])->default('draft');
    $table->decimal('total_quantity_recalled', 15, 3)->default(0);
    $table->decimal('total_value', 15, 2)->default(0);
    $table->text('reason');
    $table->timestamp('supplier_notification_sent_at')->nullable();
    $table->foreignId('claim_register_id')->nullable()->constrained('claim_registers')->nullOnDelete();
    $table->foreignId('stock_adjustment_id')->nullable()->constrained('stock_adjustments')->nullOnDelete();
    $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('posted_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['supplier_id', 'recall_date']);
    $table->index('status');
});
```

**File**: `database/migrations/2026_02_XX_create_product_recall_items.php`
```php
Schema::create('product_recall_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_recall_id')->constrained('product_recalls')->cascadeOnDelete();
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->foreignId('stock_batch_id')->constrained('stock_batches')->cascadeOnDelete();
    $table->foreignId('grn_item_id')->nullable()->constrained('goods_receipt_note_items')->nullOnDelete();
    $table->decimal('quantity_recalled', 15, 3);
    $table->decimal('unit_cost', 15, 2);
    $table->decimal('total_value', 15, 2);
    $table->text('reason')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();

    $table->index('product_recall_id');
    $table->index('stock_batch_id');
});
```

#### Step 2.3: Create ProductRecallService

**File**: `app/Services/ProductRecallService.php`

**Key Methods**:

```php
public function postRecall(ProductRecall $recall): array
{
    DB::beginTransaction();

    // 1. Validate stock availability
    $this->validateStockAvailability($recall);

    // 2. Create StockAdjustment from recall
    $adjustment = StockAdjustment::create([
        'adjustment_number' => app(StockAdjustmentService::class)->generateAdjustmentNumber(),
        'adjustment_date' => $recall->recall_date,
        'warehouse_id' => $recall->warehouse_id,
        'adjustment_type' => 'recall',
        'product_recall_id' => $recall->id,
        'reason' => $recall->reason,
        'status' => 'draft',
    ]);

    foreach ($recall->items as $recallItem) {
        $currentQty = CurrentStockByBatch::where('stock_batch_id', $recallItem->stock_batch_id)
            ->where('warehouse_id', $recall->warehouse_id)
            ->value('quantity_on_hand') ?? 0;

        StockAdjustmentItem::create([
            'stock_adjustment_id' => $adjustment->id,
            'product_id' => $recallItem->product_id,
            'stock_batch_id' => $recallItem->stock_batch_id,
            'grn_item_id' => $recallItem->grn_item_id,
            'system_quantity' => $currentQty,
            'actual_quantity' => 0, // Recalled = gone
            'adjustment_quantity' => -$recallItem->quantity_recalled, // Negative
            'unit_cost' => $recallItem->unit_cost,
            'adjustment_value' => $recallItem->quantity_recalled * $recallItem->unit_cost,
        ]);
    }

    // 3. Post the stock adjustment (delegates to StockAdjustmentService)
    $adjustmentService = app(StockAdjustmentService::class);
    $result = $adjustmentService->postAdjustment($adjustment);

    if (!$result['success']) {
        DB::rollBack();
        return $result;
    }

    // 4. Update recall status
    $recall->update([
        'status' => 'posted',
        'stock_adjustment_id' => $adjustment->id,
        'posted_at' => now(),
        'posted_by' => auth()->id(),
    ]);

    DB::commit();

    return ['success' => true, 'message' => "Recall {$recall->recall_number} posted"];
}

protected function validateStockAvailability(ProductRecall $recall): void
{
    foreach ($recall->items as $item) {
        $availableQty = CurrentStockByBatch::where('stock_batch_id', $item->stock_batch_id)
            ->where('warehouse_id', $recall->warehouse_id)
            ->value('quantity_on_hand') ?? 0;

        if ($item->quantity_recalled > $availableQty) {
            throw new \Exception(
                "Batch {$item->stockBatch->batch_code}: Recall qty ({$item->quantity_recalled}) exceeds available ({$availableQty})"
            );
        }

        // Prevent recall if batch has been issued to vans
        $issuedQty = InventoryLedgerEntry::where('stock_batch_id', $item->stock_batch_id)
            ->whereNotNull('vehicle_id')
            ->where('transaction_type', 'transfer_in')
            ->sum('debit_qty');

        if ($issuedQty > 0) {
            throw new \Exception(
                "Batch {$item->stockBatch->batch_code} has been issued to vans. Cannot recall from warehouse."
            );
        }

        // Prevent recall if batch has sales
        $soldQty = InventoryLedgerEntry::where('stock_batch_id', $item->stock_batch_id)
            ->where('transaction_type', 'sale')
            ->sum('credit_qty');

        if ($soldQty > 0) {
            throw new \Exception(
                "Batch {$item->stockBatch->batch_code} has sales ({$soldQty} units). Cannot recall."
            );
        }
    }
}

public function getAvailableBatches(int $supplierId, int $warehouseId, ?array $filters = []): Collection
{
    $query = StockBatch::where('supplier_id', $supplierId)
        ->where('status', 'active')
        ->with(['product', 'currentStockByBatch' => function($q) use ($warehouseId) {
            $q->where('warehouse_id', $warehouseId)->where('quantity_on_hand', '>', 0);
        }]);

    // Filter by batch code
    if (!empty($filters['batch_code'])) {
        $query->where('batch_code', 'like', "%{$filters['batch_code']}%");
    }

    // Filter by expiry date range
    if (!empty($filters['expiry_from']) && !empty($filters['expiry_to'])) {
        $query->whereBetween('expiry_date', [$filters['expiry_from'], $filters['expiry_to']]);
    }

    // Filter by manufacturing date
    if (!empty($filters['mfg_date'])) {
        $query->whereDate('manufacturing_date', $filters['mfg_date']);
    }

    return $query->get();
}

public function generateRecallNumber(): string
{
    $year = now()->year;
    $prefix = "RCL-{$year}-";

    $lastRecall = ProductRecall::where('recall_number', 'like', "{$prefix}%")
        ->orderByRaw('CAST(SUBSTRING(recall_number, ?) AS UNSIGNED) DESC', [strlen($prefix) + 1])
        ->first();

    $nextNumber = $lastRecall
        ? (int) substr($lastRecall->recall_number, strlen($prefix)) + 1
        : 1;

    return sprintf('%s%04d', $prefix, $nextNumber);
}
```

#### Step 2.4: Create ProductRecallController

**File**: `app/Http/Controllers/ProductRecallController.php`

**Methods**:
- `index()` - List with filters (supplier, date range, status, recall type)
- `create()` - Form
- `store(StoreProductRecallRequest)` - Save draft
- `show($id)` - View details
- `edit($id)` - Edit draft
- `update(UpdateProductRecallRequest, $id)` - Update draft
- `destroy($id)` - Delete draft
- `post($id)` - Post recall (password confirmation)
- `createClaim($id)` - Generate ClaimRegister entry
- `getBatchesForSupplier(Request)` - AJAX endpoint

**Permissions**:
- `product-recall-list`
- `product-recall-create`
- `product-recall-edit`
- `product-recall-delete`
- `product-recall-post`
- `product-recall-cancel`

#### Step 2.5: Create Form Requests

**File**: `app/Http/Requests/StoreProductRecallRequest.php`

**Validation**:
```php
[
    'recall_date' => 'required|date|before_or_equal:today',
    'supplier_id' => 'required|exists:suppliers,id',
    'warehouse_id' => 'required|exists:warehouses,id',
    'grn_id' => 'nullable|exists:goods_receipt_notes,id',
    'recall_type' => 'required|in:supplier_initiated,quality_issue,expiry,other',
    'reason' => 'required|string|max:1000',
    'items' => 'required|array|min:1',
    'items.*.product_id' => 'required|exists:products,id',
    'items.*.stock_batch_id' => 'required|exists:stock_batches,id',
    'items.*.quantity_recalled' => 'required|numeric|min:0.001',
    'items.*.unit_cost' => 'required|numeric|min:0',
]
```

**Custom Validation**:
```php
public function withValidator($validator)
{
    $validator->after(function ($validator) {
        foreach ($this->items as $index => $item) {
            $availableQty = CurrentStockByBatch::where('stock_batch_id', $item['stock_batch_id'])
                ->where('warehouse_id', $this->warehouse_id)
                ->value('quantity_on_hand') ?? 0;

            if ($item['quantity_recalled'] > $availableQty) {
                $validator->errors()->add(
                    "items.{$index}.quantity_recalled",
                    "Quantity exceeds available stock ({$availableQty} available)"
                );
            }
        }
    });
}
```

#### Step 2.6: Create Views

**Views to Create**:
- `resources/views/product-recalls/index.blade.php` - List view
- `resources/views/product-recalls/create.blade.php` - Create form
- `resources/views/product-recalls/edit.blade.php` - Edit form
- `resources/views/product-recalls/show.blade.php` - Detail view
- `resources/views/product-recalls/_batch-selector.blade.php` - Livewire component for batch selection

**Batch Selection UI** (3 modes):
1. **By Batch Code**: Enter batch code(s) directly
2. **By Expiry Date Range**: Select date range, show matching batches
3. **Manual Selection**: Grid view of all batches, multi-select checkboxes

#### Step 2.7: Routes

**File**: `routes/web.php`

```php
// Product Recalls
Route::resource('product-recalls', ProductRecallController::class)
    ->names('product-recalls');
Route::post('product-recalls/{productRecall}/post', [ProductRecallController::class, 'post'])
    ->name('product-recalls.post');
Route::post('product-recalls/{productRecall}/create-claim', [ProductRecallController::class, 'createClaim'])
    ->name('product-recalls.create-claim');
Route::get('product-recalls/batches/{supplierId}', [ProductRecallController::class, 'getBatchesForSupplier'])
    ->name('product-recalls.batches');

// Stock Adjustments
Route::resource('stock-adjustments', StockAdjustmentController::class)
    ->names('stock-adjustments');
Route::post('stock-adjustments/{stockAdjustment}/post', [StockAdjustmentController::class, 'post'])
    ->name('stock-adjustments.post');
```

#### Step 2.8: Testing

**Tests**: `tests/Feature/ProductRecallTest.php`

```php
it('creates a product recall successfully', function() {
    // Setup: Supplier, Warehouse, StockBatch, CurrentStockByBatch
    // Action: POST to product-recalls.store
    // Assert: Database has product_recalls and product_recall_items
});

it('posts a recall and creates stock adjustment', function() {
    // Setup: Draft recall with items
    // Action: POST to product-recalls.post
    // Assert: StockAdjustment created with adjustment_type='recall'
    // Assert: CurrentStock reduced
    // Assert: StockBatch.status = 'recalled'
    // Assert: InventoryLedgerEntry created
});

it('validates stock availability before posting', function() {
    // Setup: Recall with qty > available
    // Action: POST to product-recalls.post
    // Assert: Exception thrown
});

it('prevents recall if batch issued to vans', function() {
    // Setup: Batch with vehicle transfers
    // Action: POST to product-recalls.post
    // Assert: Exception thrown
});

it('creates claim register from recall', function() {
    // Setup: Posted recall
    // Action: POST to product-recalls.create-claim
    // Assert: ClaimRegister created with transaction_type='claim'
    // Assert: Recall.claim_register_id updated
});
```

**Manual Testing Checklist**:
- [ ] Create recall for single batch
- [ ] Create recall for multiple batches from different GRNs
- [ ] Select batches by expiry date range
- [ ] Post recall
- [ ] Verify stock reduced (CurrentStock, CurrentStockByBatch)
- [ ] Verify StockBatch.status = 'recalled'
- [ ] Verify InventoryLedgerEntry created
- [ ] Verify JournalEntry posted (Dr. 5280, Cr. 1151)
- [ ] Create ClaimRegister from recall
- [ ] Post claim
- [ ] Verify all 5 critical reports still work correctly

---

## Report Impact Analysis

### No Changes Required (Using Existing Transaction Types)

All 5 critical reports will continue working **without modification**:

**1. DailyStockRegisterController**
- Recalls appear as `transaction_type = 'adjustment'` with `credit_qty` (stock out)
- In Hand calculation correctly reduced via `SUM(debit_qty - credit_qty)`
- **Action**: None. Optionally add "Adjustments" column to show recalls separately

**2. InventoryLedgerReportController**
- Recalls appear as normal ledger entries
- `notes` field identifies recall number
- Running balance correctly decrements
- **Action**: None. Optionally filter by `notes LIKE '%Recall%'`

**3. GoodsIssueReportController**
- No impact (recalls are warehouse-level, not van-level)
- **Action**: None

**4. SalesmanStockRegisterController**
- No impact (recalls from warehouse, not vans)
- **Action**: None (Phase 1 only)
- **Future**: Phase 2 could add van recalls if needed

**5. RoiReportController**
- Recall losses appear in expenses (via GL account 5280)
- COGS unaffected (separate from sales)
- Profit correctly reduced
- **Action**: None

---

## Edge Cases Handled

### 1. Partial Batch Recalls
- Allow recalling 30 out of 100 units
- StockBatch.status remains 'active' if quantity_on_hand > 0
- Only mark 'recalled' when fully depleted
- **Implementation**: Check in StockAdjustmentService::postAdjustment

### 2. Multiple Recalls from Same GRN
- Each recall is independent
- Link via `ProductRecallItem.grn_item_id` (granular)
- **Implementation**: Allow multiple recalls per GRN

### 3. Recalls After Issued to Vans
- **Phase 1**: PREVENT via validation
- **Phase 2**: Allow van recalls (future enhancement)
- **Implementation**: Check in ProductRecallService::validateStockAvailability

### 4. Recalls After Stock Sold
- **PREVENT** via validation
- Show error: "Create customer return first"
- **Implementation**: Check in ProductRecallService::validateStockAvailability

### 5. Financial Period Closing
- Allow posting in current period even if stock received in closed period
- Journal entry posts to current period (recall_date)
- **Implementation**: No special handling needed (AccountingService handles it)

### 6. Batch Partially Recalled
- Track partial recalls in StockBatch.notes
- Keep status 'active' if stock remains
- **Implementation**: Conditional status update in StockAdjustmentService

---

## Verification Strategy

### Automated Tests
- Unit tests for services (number generation, validation)
- Feature tests for workflows (create → post → claim)
- Integration test for full recall → claim → recovery workflow
- Report tests to ensure no breaking changes

### Manual Testing
1. **Stock Adjustment Workflow**:
   - Create manual adjustment (damage, theft, expiry)
   - Post adjustment
   - Verify stock updated
   - Verify GL entry correct

2. **Product Recall Workflow**:
   - Create recall by batch code
   - Create recall by expiry range
   - Post recall
   - Verify stock adjustment created
   - Verify batch status updated
   - Create claim from recall
   - Post claim
   - Verify GL impact

3. **Report Validation**:
   - Run DailyStockRegister before/after recall
   - Run InventoryLedgerReport and verify entries
   - Run GoodsIssueReport (should be unchanged)
   - Run RoiReport and verify loss appears

### Production Deployment Checklist
- [ ] Run migrations in staging
- [ ] Run account seeder (create GL accounts 5280-5284)
- [ ] Assign permissions to roles
- [ ] Train warehouse staff on recall workflow
- [ ] Train accountants on GL impact
- [ ] Test recall workflow end-to-end in staging
- [ ] Monitor first production recall closely
- [ ] Validate reports after first recall

---

## Rollback Plan

If critical issues arise:

1. **Disable Routes**: Comment out product-recalls routes in `routes/web.php`
2. **Restore Code**: `git revert <commit-hash>`
3. **Database Rollback**: `php artisan migrate:rollback --step=4`
4. **Data Preservation**: Export `product_recalls` and `product_recall_items` to CSV
5. **Communication**: Notify users of reversion

---

## Future Enhancements (Phase 3+)

1. **Van Recalls**: Allow recalling stock from salesman vans
2. **Approval Workflow**: Add manager approval before posting
3. **Excel Import**: Bulk recall creation via Excel file
4. **Email Notifications**: Auto-send email to supplier on recall
5. **Customer Returns**: Link recalls to customer return workflow
6. **Recall Reversal**: Automated reversal if posted by mistake
7. **Batch Quarantine**: Separate status for quarantined (not yet recalled)

---

## Summary

This implementation plan provides:

✅ **Backward Compatibility**: Uses existing `InventoryLedgerEntry.TYPE_ADJUSTMENT`, no report changes needed
✅ **Production Safety**: Phased rollout, comprehensive testing, rollback plan
✅ **Reporting Accuracy**: All 5 critical reports continue working correctly
✅ **Audit Trail**: Complete traceability via immutable ledgers
✅ **Extensibility**: Easy to add approval workflow, van recalls, Excel import later
✅ **User Experience**: Intuitive batch selection (3 modes), clear workflow (draft → post → claim)

**Total Implementation Time**: 2 weeks
- Week 1: Stock Adjustment foundation + testing
- Week 2: Product Recall feature + integration testing

**Risk Level**: Low
- Builds on proven patterns from `InventoryService` and `GoodsReceiptNoteController`
- Uses existing infrastructure (ledgers, journal entries, batch tracking)
- No breaking changes to existing functionality
