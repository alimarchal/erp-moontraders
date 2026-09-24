<?php

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\CurrentStockByBatch;
use App\Models\InventoryLedgerEntry;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockValuationLayer;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['stock-adjustment-list', 'stock-adjustment-create', 'stock-adjustment-edit', 'stock-adjustment-delete', 'stock-adjustment-post'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['stock-adjustment-list', 'stock-adjustment-create', 'stock-adjustment-edit', 'stock-adjustment-delete', 'stock-adjustment-post']);
    $this->actingAs($this->user);

    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create();
    $this->uom = Uom::factory()->create();

    // Create required GL accounts for testing
    // The journal entry is written in the base currency; without one it cannot be created.
    $currency = Currency::factory()->base()->create();
    $accountType = AccountType::create(['type_name' => 'Expense', 'report_group' => 'IncomeStatement']);
    $assetType = AccountType::create(['type_name' => 'Asset', 'report_group' => 'BalanceSheet']);

    ChartOfAccount::create([
        'account_code' => '1151',
        'account_name' => 'Stock In Hand',
        'account_type_id' => $assetType->id,
        'currency_id' => $currency->id,
        'is_active' => true,
        'normal_balance' => 'debit',
    ]);

    ChartOfAccount::create([
        'account_code' => '5280',
        'account_name' => 'Stock Loss on Recalls',
        'account_type_id' => $accountType->id,
        'currency_id' => $currency->id,
        'is_active' => true,
        'normal_balance' => 'debit',
    ]);

    ChartOfAccount::create([
        'account_code' => '5281',
        'account_name' => 'Stock Loss - Damage',
        'account_type_id' => $accountType->id,
        'currency_id' => $currency->id,
        'is_active' => true,
        'normal_balance' => 'debit',
    ]);

    ChartOfAccount::create([
        'account_code' => '5282',
        'account_name' => 'Stock Loss - Theft',
        'account_type_id' => $accountType->id,
        'currency_id' => $currency->id,
        'is_active' => true,
        'normal_balance' => 'debit',
    ]);

    ChartOfAccount::create([
        'account_code' => '5283',
        'account_name' => 'Stock Loss - Expiry',
        'account_type_id' => $accountType->id,
        'currency_id' => $currency->id,
        'is_active' => true,
        'normal_balance' => 'debit',
    ]);

    ChartOfAccount::create([
        'account_code' => '5284',
        'account_name' => 'Stock Loss - Other',
        'account_type_id' => $accountType->id,
        'currency_id' => $currency->id,
        'is_active' => true,
        'normal_balance' => 'debit',
    ]);

    // Named as in production, so a lookup by name rather than code would fail here too.
    CostCenter::create(['code' => 'CC006', 'name' => 'Warehouse & Inventory', 'is_active' => true]);

    // Without an open period the journal entry cannot be written, and posting has to fail
    // rather than reduce stock with no GL entry behind it.
    AccountingPeriod::create([
        'name' => 'Test Period',
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
        'status' => 'open',
    ]);
});

/**
 * The valuation layer a GRN would have left behind for this batch.
 *
 * An adjustment moves its quantity through these layers, so a batch that has a
 * current_stock_by_batch row and no layer is not a state the application can
 * reach — and posting against one is refused.
 */
function layerFor(StockBatch $batch, float $quantity): StockValuationLayer
{
    $context = test();

    $receipt = StockMovement::create([
        'movement_type' => 'grn',
        'reference_type' => 'App\\Models\\GoodsReceiptNote',
        'reference_id' => $batch->id,
        'movement_date' => now()->toDateString(),
        'product_id' => $batch->product_id,
        'stock_batch_id' => $batch->id,
        'warehouse_id' => $context->warehouse->id,
        'quantity' => $quantity,
        'uom_id' => $context->uom->id,
        'unit_cost' => 50.00,
        'total_value' => $quantity * 50.00,
        'created_by' => $context->user->id,
    ]);

    return StockValuationLayer::create([
        'product_id' => $batch->product_id,
        'warehouse_id' => $context->warehouse->id,
        'stock_batch_id' => $batch->id,
        'stock_movement_id' => $receipt->id,
        'receipt_date' => now()->toDateString(),
        'quantity_received' => $quantity,
        'quantity_remaining' => $quantity,
        'unit_cost' => 50.00,
        'total_value' => $quantity * 50.00,
        'value_remaining' => $quantity * 50.00,
        'priority_order' => $batch->priority_order ?? 99,
    ]);
}

test('stock adjustment can be created as draft', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'status' => 'active',
    ]);

    layerFor($batch, 100);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 100,
        'unit_cost' => 50.00,
        'total_value' => 5000.00,
    ]);

    $data = [
        'adjustment_date' => now()->format('Y-m-d'),
        'warehouse_id' => $this->warehouse->id,
        'adjustment_type' => 'damage',
        'reason' => 'Product damaged during storage',
        'items' => [
            [
                'product_id' => $this->product->id,
                'stock_batch_id' => $batch->id,
                'system_quantity' => 100,
                'actual_quantity' => 90,
                'adjustment_quantity' => -10,
                'unit_cost' => 50.00,
                'adjustment_value' => -500.00,
                'uom_id' => $this->uom->id,
            ],
        ],
    ];

    $service = app(StockAdjustmentService::class);
    $result = $service->createAdjustment($data);

    expect($result['success'])->toBeTrue();
    expect($result['data'])->toBeInstanceOf(StockAdjustment::class);
    expect($result['data']->adjustment_number)->toStartWith('SA-');
    expect($result['data']->status)->toBe('draft');
    expect($result['data']->items)->toHaveCount(1);
});

test('updating a stock adjustment keeps each item independent', function () {
    $secondProduct = Product::factory()->create();

    $batchOne = StockBatch::factory()->create(['product_id' => $this->product->id, 'status' => 'active']);
    $batchTwo = StockBatch::factory()->create(['product_id' => $secondProduct->id, 'status' => 'active']);

    $adjustment = StockAdjustment::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'adjustment_type' => 'damage',
        'status' => 'draft',
    ]);

    $adjustment->items()->create([
        'product_id' => $this->product->id,
        'stock_batch_id' => $batchOne->id,
        'system_quantity' => 100,
        'actual_quantity' => 90,
        'adjustment_quantity' => -10,
        'unit_cost' => 50.00,
        'adjustment_value' => -500.00,
        'uom_id' => $this->uom->id,
    ]);

    $response = $this->put(route('stock-adjustments.update', $adjustment), [
        'adjustment_date' => now()->format('Y-m-d'),
        'warehouse_id' => $this->warehouse->id,
        'adjustment_type' => 'damage',
        'reason' => 'Recount after audit',
        'items' => [
            [
                'product_id' => $this->product->id,
                'stock_batch_id' => $batchOne->id,
                'system_quantity' => 100,
                'actual_quantity' => 90,
                'unit_cost' => 50.00,
                'uom_id' => $this->uom->id,
            ],
            [
                'product_id' => $secondProduct->id,
                'stock_batch_id' => $batchTwo->id,
                'system_quantity' => 200,
                'actual_quantity' => 150,
                'unit_cost' => 25.00,
                'uom_id' => $this->uom->id,
            ],
        ],
    ]);

    $response->assertRedirect(route('stock-adjustments.show', $adjustment));

    $items = $adjustment->items()->orderBy('id')->get();
    expect($items)->toHaveCount(2);
    expect($items[0]->product_id)->toBe($this->product->id);
    expect($items[0]->stock_batch_id)->toBe($batchOne->id);
    expect((float) $items[0]->adjustment_value)->toBe(-500.00);
    expect($items[1]->product_id)->toBe($secondProduct->id);
    expect($items[1]->stock_batch_id)->toBe($batchTwo->id);
    expect((float) $items[1]->adjustment_value)->toBe(-1250.00);
});

test('stock adjustment number is generated correctly', function () {
    $service = app(StockAdjustmentService::class);
    $number = $service->generateAdjustmentNumber();
    $year = now()->year;

    expect($number)->toBe("SA-{$year}-0001");
});

test('stock adjustment numbers include soft deleted adjustments', function () {
    $existingAdjustment = StockAdjustment::factory()->create([
        'adjustment_number' => 'SA-'.now()->year.'-0011',
    ]);
    $existingAdjustment->delete();

    $service = app(StockAdjustmentService::class);

    expect($service->generateAdjustmentNumber())->toBe('SA-'.now()->year.'-0012');
});

test('stock adjustment can be posted', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'status' => 'active',
        'is_active' => true,
    ]);

    layerFor($batch, 100);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 100,
        'unit_cost' => 50.00,
        'total_value' => 5000.00,
        'status' => 'active',
    ]);

    $adjustment = StockAdjustment::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'adjustment_type' => 'damage',
        'status' => 'draft',
    ]);

    StockAdjustmentItem::create([
        'stock_adjustment_id' => $adjustment->id,
        'product_id' => $this->product->id,
        'stock_batch_id' => $batch->id,
        'system_quantity' => 100,
        'actual_quantity' => 90,
        'adjustment_quantity' => -10,
        'unit_cost' => 50.00,
        'adjustment_value' => -500.00,
        'uom_id' => $this->uom->id,
    ]);

    $service = app(StockAdjustmentService::class);
    $result = $service->postAdjustment($adjustment);

    expect($result['success'])->toBeTrue();
    $adjustment->refresh();
    expect($adjustment->status)->toBe('posted');
    expect($adjustment->posted_by)->toBe($this->user->id);
    expect($adjustment->posted_at)->not->toBeNull();
});

test('stock adjustment updates inventory ledger', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'status' => 'active',
    ]);

    layerFor($batch, 100);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 100,
        'unit_cost' => 50.00,
        'total_value' => 5000.00,
    ]);

    $adjustment = StockAdjustment::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    StockAdjustmentItem::create([
        'stock_adjustment_id' => $adjustment->id,
        'product_id' => $this->product->id,
        'stock_batch_id' => $batch->id,
        'system_quantity' => 100,
        'actual_quantity' => 90,
        'adjustment_quantity' => -10,
        'unit_cost' => 50.00,
        'adjustment_value' => -500.00,
        'uom_id' => $this->uom->id,
    ]);

    $service = app(StockAdjustmentService::class);
    $service->postAdjustment($adjustment);

    $ledgerEntry = InventoryLedgerEntry::where('product_id', $this->product->id)
        ->where('transaction_type', 'adjustment')
        ->first();

    expect($ledgerEntry)->not->toBeNull();
    expect($ledgerEntry->credit_qty)->toBe('10.00');
    expect($ledgerEntry->warehouse_id)->toBe($this->warehouse->id);
});

test('stock adjustment reduces current stock', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'status' => 'active',
    ]);

    layerFor($batch, 100);

    $currentStock = CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 100,
        'unit_cost' => 50.00,
        'total_value' => 5000.00,
    ]);

    $adjustment = StockAdjustment::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    StockAdjustmentItem::create([
        'stock_adjustment_id' => $adjustment->id,
        'product_id' => $this->product->id,
        'stock_batch_id' => $batch->id,
        'system_quantity' => 100,
        'actual_quantity' => 85,
        'adjustment_quantity' => -15,
        'unit_cost' => 50.00,
        'adjustment_value' => -750.00,
        'uom_id' => $this->uom->id,
    ]);

    $service = app(StockAdjustmentService::class);
    $service->postAdjustment($adjustment);

    $currentStock->refresh();
    expect($currentStock->quantity_on_hand)->toBe('85.00');
});

test('only draft adjustments can be posted', function () {
    $adjustment = StockAdjustment::factory()->create([
        'status' => 'posted',
    ]);

    $service = app(StockAdjustmentService::class);
    $result = $service->postAdjustment($adjustment);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('Only draft adjustments can be posted');
});

test('stock adjustment controller index page renders', function () {
    $this->get(route('stock-adjustments.index'))
        ->assertSuccessful()
        ->assertViewIs('stock-adjustments.index')
        ->assertViewHas('adjustments');
});

test('stock adjustment controller create page renders', function () {
    $supplier = Supplier::factory()->create(['disabled' => false]);
    $this->user->update(['supplier_id' => $supplier->id]);

    $this->get(route('stock-adjustments.create'))
        ->assertSuccessful()
        ->assertViewIs('stock-adjustments.create')
        ->assertViewHas(['warehouses', 'suppliers', 'uoms'])
        ->assertViewHas('suppliers', fn ($suppliers) => $suppliers->pluck('id')->contains($supplier->id));
});

test('supplier user sees only related stock adjustments', function () {
    $supplier = Supplier::factory()->create(['disabled' => false]);
    $otherSupplier = Supplier::factory()->create(['disabled' => false]);
    $this->user->update(['supplier_id' => $supplier->id]);

    $relatedProduct = Product::factory()->create(['supplier_id' => $supplier->id]);
    $otherProduct = Product::factory()->create(['supplier_id' => $otherSupplier->id]);
    $relatedAdjustment = StockAdjustment::factory()->create(['adjustment_number' => 'SA-RELATED-001']);
    $otherAdjustment = StockAdjustment::factory()->create(['adjustment_number' => 'SA-OTHER-001']);

    $relatedAdjustment->items()->create([
        'stock_adjustment_id' => $relatedAdjustment->id,
        'product_id' => $relatedProduct->id,
        'system_quantity' => 1,
        'actual_quantity' => 1,
        'adjustment_quantity' => 0,
        'unit_cost' => 1,
        'adjustment_value' => 0,
        'uom_id' => $this->uom->id,
    ]);
    $otherAdjustment->items()->create([
        'stock_adjustment_id' => $otherAdjustment->id,
        'product_id' => $otherProduct->id,
        'system_quantity' => 1,
        'actual_quantity' => 1,
        'adjustment_quantity' => 0,
        'unit_cost' => 1,
        'adjustment_value' => 0,
        'uom_id' => $this->uom->id,
    ]);

    $this->get(route('stock-adjustments.index'))
        ->assertSuccessful()
        ->assertSee('SA-RELATED-001')
        ->assertDontSee('SA-OTHER-001');
});

test('supplier user cannot load another supplier products for stock adjustment', function () {
    $supplier = Supplier::factory()->create(['disabled' => false]);
    $otherSupplier = Supplier::factory()->create(['disabled' => false]);
    $this->user->update(['supplier_id' => $supplier->id]);

    $this->getJson(route('api.suppliers.stock-adjustment-products', $otherSupplier))
        ->assertForbidden();
});

test('super admin sees all stock adjustment suppliers and adjustments', function () {
    $supplier = Supplier::factory()->create(['disabled' => false]);
    $otherSupplier = Supplier::factory()->create(['disabled' => false]);
    $this->user->update(['is_super_admin' => 'Yes']);

    $this->get(route('stock-adjustments.create'))
        ->assertViewHas('suppliers', function ($suppliers) use ($supplier, $otherSupplier) {
            return $suppliers->pluck('id')->contains($supplier->id)
                && $suppliers->pluck('id')->contains($otherSupplier->id);
        });
});

test('batch status changes to depleted when fully adjusted', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'status' => 'active',
        'is_active' => true,
    ]);

    layerFor($batch, 50);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 50,
        'unit_cost' => 50.00,
        'total_value' => 2500.00,
    ]);

    $adjustment = StockAdjustment::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'adjustment_type' => 'damage',
        'status' => 'draft',
    ]);

    StockAdjustmentItem::create([
        'stock_adjustment_id' => $adjustment->id,
        'product_id' => $this->product->id,
        'stock_batch_id' => $batch->id,
        'system_quantity' => 50,
        'actual_quantity' => 0,
        'adjustment_quantity' => -50,
        'unit_cost' => 50.00,
        'adjustment_value' => -2500.00,
        'uom_id' => $this->uom->id,
    ]);

    $service = app(StockAdjustmentService::class);
    $service->postAdjustment($adjustment);

    $batch->refresh();
    expect($batch->status)->toBe('depleted');
    expect($batch->is_active)->toBeFalse();
});

test('posting writes the journal entry against the warehouse cost center', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'status' => 'active',
        'is_active' => true,
    ]);

    layerFor($batch, 100);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 100,
        'unit_cost' => 50.00,
        'total_value' => 5000.00,
        'status' => 'active',
    ]);

    $adjustment = StockAdjustment::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'adjustment_type' => 'count_variance',
        'status' => 'draft',
    ]);

    StockAdjustmentItem::create([
        'stock_adjustment_id' => $adjustment->id,
        'product_id' => $this->product->id,
        'stock_batch_id' => $batch->id,
        'system_quantity' => 100,
        'actual_quantity' => 90,
        'adjustment_quantity' => -10,
        'unit_cost' => 50.00,
        'adjustment_value' => -500.00,
        'uom_id' => $this->uom->id,
    ]);

    $result = app(StockAdjustmentService::class)->postAdjustment($adjustment);

    expect($result['success'])->toBeTrue();

    $journalEntry = $adjustment->fresh()->journalEntry;
    $costCenterId = CostCenter::where('code', 'CC006')->value('id');

    expect($journalEntry)->not->toBeNull()
        ->and($journalEntry->details)->toHaveCount(2)
        ->and($journalEntry->details->pluck('cost_center_id')->unique()->all())->toBe([$costCenterId])
        ->and((float) $journalEntry->details->sum('debit'))->toBe(500.0)
        ->and((float) $journalEntry->details->sum('credit'))->toBe(500.0);
});

test('posting is rolled back entirely when the journal entry cannot be written', function () {
    // The Stock Loss account a count variance posts to is missing.
    ChartOfAccount::where('account_name', 'Stock Loss - Other')->delete();

    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'status' => 'active',
        'is_active' => true,
    ]);

    layerFor($batch, 100);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 100,
        'unit_cost' => 50.00,
        'total_value' => 5000.00,
        'status' => 'active',
    ]);

    $adjustment = StockAdjustment::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'adjustment_type' => 'count_variance',
        'status' => 'draft',
    ]);

    StockAdjustmentItem::create([
        'stock_adjustment_id' => $adjustment->id,
        'product_id' => $this->product->id,
        'stock_batch_id' => $batch->id,
        'system_quantity' => 100,
        'actual_quantity' => 90,
        'adjustment_quantity' => -10,
        'unit_cost' => 50.00,
        'adjustment_value' => -500.00,
        'uom_id' => $this->uom->id,
    ]);

    $result = app(StockAdjustmentService::class)->postAdjustment($adjustment);

    expect($result['success'])->toBeFalse()
        ->and($adjustment->fresh()->status)->toBe('draft');

    // Stock is exactly where it was: no movement, no reduction.
    expect((float) CurrentStockByBatch::where('stock_batch_id', $batch->id)->value('quantity_on_hand'))->toBe(100.0);
    $this->assertDatabaseMissing('stock_movements', [
        'reference_type' => StockAdjustment::class,
        'reference_id' => $adjustment->id,
    ]);
});

test('an excess found on a count can be posted', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'status' => 'active',
        'is_active' => true,
        'priority_order' => 5,
    ]);

    layerFor($batch, 100);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 100,
        'unit_cost' => 50.00,
        'total_value' => 5000.00,
        'status' => 'active',
    ]);

    $adjustment = StockAdjustment::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'adjustment_type' => 'count_variance',
        'status' => 'draft',
    ]);

    StockAdjustmentItem::create([
        'stock_adjustment_id' => $adjustment->id,
        'product_id' => $this->product->id,
        'stock_batch_id' => $batch->id,
        'system_quantity' => 100,
        'actual_quantity' => 112,
        'adjustment_quantity' => 12,
        'unit_cost' => 50.00,
        'adjustment_value' => 600.00,
        'uom_id' => $this->uom->id,
    ]);

    $result = app(StockAdjustmentService::class)->postAdjustment($adjustment);

    expect($result['success'])->toBeTrue()
        ->and($adjustment->fresh()->status)->toBe('posted');

    expect((float) CurrentStockByBatch::where('stock_batch_id', $batch->id)->value('quantity_on_hand'))->toBe(112.0);

    // The excess goes back into the batch's own layer at its receipt cost rather
    // than into a second layer: a separate layer carries no grn_item_id, and the
    // Goods Issue batch picker joins that column, so its quantity could be
    // counted as available yet never picked.
    expect(StockValuationLayer::where('stock_batch_id', $batch->id)->count())->toBe(1)
        ->and((float) StockValuationLayer::where('stock_batch_id', $batch->id)->value('quantity_remaining'))->toBe(112.0);

    // An increase reverses the loss: Dr Stock In Hand, Cr Stock Loss.
    $details = $adjustment->fresh()->journalEntry->details;
    expect((float) $details->sum('debit'))->toBe(600.0)
        ->and((float) $details->sum('credit'))->toBe(600.0);
});

test('an excess on a depleted batch makes that stock issuable again', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'status' => 'depleted',
        'is_active' => false,
    ]);

    layerFor($batch, 0);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 0,
        'unit_cost' => 50.00,
        'total_value' => 0,
        'status' => 'depleted',
    ]);

    $adjustment = StockAdjustment::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'adjustment_type' => 'count_variance',
        'status' => 'draft',
    ]);

    StockAdjustmentItem::create([
        'stock_adjustment_id' => $adjustment->id,
        'product_id' => $this->product->id,
        'stock_batch_id' => $batch->id,
        'system_quantity' => 0,
        'actual_quantity' => 8,
        'adjustment_quantity' => 8,
        'unit_cost' => 50.00,
        'adjustment_value' => 400.00,
        'uom_id' => $this->uom->id,
    ]);

    expect(app(StockAdjustmentService::class)->postAdjustment($adjustment)['success'])->toBeTrue();

    // Goods issues allocate only from active rows, so found stock must not stay depleted.
    expect(CurrentStockByBatch::where('stock_batch_id', $batch->id)->value('status'))->toBe('active')
        ->and($batch->fresh()->status)->toBe('active')
        ->and($batch->fresh()->is_active)->toBeTrue();
});

test('a shortage larger than what the batch now holds is refused', function () {
    // Drafted when the batch held 378; by posting time only 3 were left.
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'status' => 'active',
        'is_active' => true,
    ]);

    layerFor($batch, 3);

    $stock = CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 3,
        'unit_cost' => 50.00,
        'total_value' => 150.00,
        'status' => 'active',
    ]);

    $adjustment = StockAdjustment::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'adjustment_type' => 'damage',
        'status' => 'draft',
    ]);

    StockAdjustmentItem::create([
        'stock_adjustment_id' => $adjustment->id,
        'product_id' => $this->product->id,
        'stock_batch_id' => $batch->id,
        'system_quantity' => 378,
        'actual_quantity' => 366,
        'adjustment_quantity' => -12,
        'unit_cost' => 50.00,
        'adjustment_value' => -600.00,
        'uom_id' => $this->uom->id,
    ]);

    $result = app(StockAdjustmentService::class)->postAdjustment($adjustment);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('only 3')
        ->and($adjustment->fresh()->status)->toBe('draft')
        ->and((float) $stock->fresh()->quantity_on_hand)->toBe(3.0)
        ->and(StockMovement::where('reference_type', StockAdjustment::class)->exists())->toBeFalse();
});
