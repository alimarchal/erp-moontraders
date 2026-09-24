<?php

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\CurrentStockByBatch;
use App\Models\InventoryLedgerEntry;
use App\Models\Product;
use App\Models\ProductRecall;
use App\Models\ProductRecallItem;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockValuationLayer;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\ProductRecallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['product-recall-list', 'product-recall-create', 'product-recall-edit', 'product-recall-delete', 'product-recall-post', 'product-recall-cancel'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['product-recall-list', 'product-recall-create', 'product-recall-edit', 'product-recall-delete', 'product-recall-post', 'product-recall-cancel']);
    $this->actingAs($this->user);

    $this->supplier = Supplier::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->uom = Uom::factory()->create();
    $this->product = Product::factory()->create(['uom_id' => $this->uom->id]);

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

    CostCenter::create(['code' => 'CC006', 'name' => 'Warehouse & Inventory', 'is_active' => true]);

    // Posting a recall writes a journal entry, which needs an open period for its date.
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
 * A recall posts a stock adjustment, which moves the quantity through these
 * layers, so a batch with a current_stock_by_batch row and no layer is not a
 * state the application can reach — and posting against one is refused.
 */
function recallLayerFor(StockBatch $batch, float $quantity): StockValuationLayer
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

test('product recall can be created as draft', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'status' => 'active',
    ]);

    recallLayerFor($batch, 100);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 100,
        'unit_cost' => 50.00,
        'total_value' => 5000.00,
    ]);

    $data = [
        'recall_date' => now()->format('Y-m-d'),
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'recall_type' => 'supplier_initiated',
        'reason' => 'Defective product batch',
        'items' => [
            [
                'product_id' => $this->product->id,
                'stock_batch_id' => $batch->id,
                'quantity_recalled' => 20,
                'unit_cost' => 50.00,
                'total_value' => 1000.00,
            ],
        ],
    ];

    $service = new ProductRecallService;
    $result = $service->createRecall($data);

    expect($result['success'])->toBeTrue();
    expect($result['data'])->toBeInstanceOf(ProductRecall::class);
    expect($result['data']->recall_number)->toStartWith('RCL-');
    expect($result['data']->status)->toBe('draft');
    expect($result['data']->total_quantity_recalled)->toBe('20.000');
    expect($result['data']->items)->toHaveCount(1);
});

test('product recall number is generated correctly', function () {
    $service = new ProductRecallService;
    $number = $service->generateRecallNumber();
    $year = now()->year;

    expect($number)->toBe("RCL-{$year}-0001");
});

test('product recall can be posted and creates stock adjustment', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'status' => 'active',
        'is_active' => true,
    ]);

    recallLayerFor($batch, 100);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 100,
        'unit_cost' => 50.00,
        'total_value' => 5000.00,
    ]);

    $recall = ProductRecall::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'recall_type' => 'supplier_initiated',
        'status' => 'draft',
        'total_quantity_recalled' => 30,
        'total_value' => 1500.00,
    ]);

    ProductRecallItem::create([
        'product_recall_id' => $recall->id,
        'product_id' => $this->product->id,
        'stock_batch_id' => $batch->id,
        'quantity_recalled' => 30,
        'unit_cost' => 50.00,
        'total_value' => 1500.00,
    ]);

    $service = new ProductRecallService;
    $result = $service->postRecall($recall);

    expect($result['success'])->toBeTrue();
    $recall->refresh();
    expect($recall->status)->toBe('posted');
    expect($recall->stock_adjustment_id)->not->toBeNull();

    $adjustment = StockAdjustment::find($recall->stock_adjustment_id);
    expect($adjustment)->not->toBeNull();
    expect($adjustment->adjustment_type)->toBe('recall');
    expect($adjustment->status)->toBe('posted');
});

test('product recall validates stock availability', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'status' => 'active',
    ]);

    recallLayerFor($batch, 10);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 10,
        'unit_cost' => 50.00,
        'total_value' => 500.00,
    ]);

    $recall = ProductRecall::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    ProductRecallItem::create([
        'product_recall_id' => $recall->id,
        'product_id' => $this->product->id,
        'stock_batch_id' => $batch->id,
        'quantity_recalled' => 50,
        'unit_cost' => 50.00,
        'total_value' => 2500.00,
    ]);

    $service = new ProductRecallService;
    $result = $service->postRecall($recall);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('exceeds available');
});

test('product recall prevents recall if batch issued to vans', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'status' => 'active',
    ]);

    recallLayerFor($batch, 100);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 100,
        'unit_cost' => 50.00,
        'total_value' => 5000.00,
    ]);

    $vehicle = Vehicle::factory()->create();

    InventoryLedgerEntry::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'vehicle_id' => $vehicle->id,
        'stock_batch_id' => $batch->id,
        'transaction_type' => 'transfer_in',
        'date' => now(),
        'debit_qty' => 20,
        'credit_qty' => 0,
        'unit_cost' => 50.00,
    ]);

    $recall = ProductRecall::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    ProductRecallItem::create([
        'product_recall_id' => $recall->id,
        'product_id' => $this->product->id,
        'stock_batch_id' => $batch->id,
        'quantity_recalled' => 10,
        'unit_cost' => 50.00,
        'total_value' => 500.00,
    ]);

    $service = new ProductRecallService;
    $result = $service->postRecall($recall);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('issued to vans');
});

test('product recall prevents recall if batch has sales', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'status' => 'active',
    ]);

    recallLayerFor($batch, 100);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 100,
        'unit_cost' => 50.00,
        'total_value' => 5000.00,
    ]);

    InventoryLedgerEntry::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'transaction_type' => 'sale',
        'date' => now(),
        'debit_qty' => 0,
        'credit_qty' => 5,
        'unit_cost' => 50.00,
    ]);

    $recall = ProductRecall::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    ProductRecallItem::create([
        'product_recall_id' => $recall->id,
        'product_id' => $this->product->id,
        'stock_batch_id' => $batch->id,
        'quantity_recalled' => 10,
        'unit_cost' => 50.00,
        'total_value' => 500.00,
    ]);

    $service = new ProductRecallService;
    $result = $service->postRecall($recall);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('has sales');
});

test('product recall marks batch as recalled when fully recalled', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'status' => 'active',
        'is_active' => true,
    ]);

    recallLayerFor($batch, 50);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 50,
        'unit_cost' => 50.00,
        'total_value' => 2500.00,
    ]);

    $recall = ProductRecall::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    ProductRecallItem::create([
        'product_recall_id' => $recall->id,
        'product_id' => $this->product->id,
        'stock_batch_id' => $batch->id,
        'quantity_recalled' => 50,
        'unit_cost' => 50.00,
        'total_value' => 2500.00,
    ]);

    $service = new ProductRecallService;
    $service->postRecall($recall);

    $batch->refresh();
    expect($batch->status)->toBe('recalled');
    expect($batch->is_active)->toBeFalse();
});

test('product recall controller index page renders', function () {
    $this->get(route('product-recalls.index'))
        ->assertSuccessful()
        ->assertViewIs('product-recalls.index')
        ->assertViewHas('recalls');
});

test('product recall controller create page renders', function () {
    $this->get(route('product-recalls.create'))
        ->assertSuccessful()
        ->assertViewIs('product-recalls.create')
        ->assertViewHas(['suppliers', 'warehouses']);
});

test('product recalls are scoped to the authenticated users supplier', function () {
    $ownSupplier = Supplier::factory()->create(['disabled' => false]);
    $otherSupplier = Supplier::factory()->create(['disabled' => false]);
    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    ProductRecall::factory()->create([
        'supplier_id' => $ownSupplier->id,
        'warehouse_id' => $this->warehouse->id,
        'recall_number' => 'OWN-RECALL',
    ]);
    ProductRecall::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'warehouse_id' => $this->warehouse->id,
        'recall_number' => 'OTHER-RECALL',
    ]);

    $response = $this->get(route('product-recalls.index'));

    $response->assertSuccessful();
    $response->assertSee('OWN-RECALL');
    $response->assertDontSee('OTHER-RECALL');
    expect($response->viewData('suppliers'))->toHaveCount(1);
    expect($response->viewData('suppliers')->first()->id)->toBe($ownSupplier->id);
});

test('product recall create page supplier options are scoped', function () {
    $ownSupplier = Supplier::factory()->create(['supplier_name' => 'Own Supplier', 'disabled' => false]);
    Supplier::factory()->create(['supplier_name' => 'Other Supplier', 'disabled' => false]);
    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    $response = $this->get(route('product-recalls.create'));

    $response->assertSuccessful();
    $response->assertSee('Own Supplier');
    $response->assertDontSee('Other Supplier');
});

test('scoped users cannot store recall for another supplier', function () {
    $ownSupplier = Supplier::factory()->create(['disabled' => false]);
    $otherSupplier = Supplier::factory()->create(['disabled' => false]);
    $product = Product::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'uom_id' => $this->uom->id,
    ]);
    $batch = StockBatch::factory()->create([
        'product_id' => $product->id,
        'supplier_id' => $otherSupplier->id,
        'status' => 'active',
    ]);
    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    $this->post(route('product-recalls.store'), [
        'recall_date' => now()->format('Y-m-d'),
        'supplier_id' => $otherSupplier->id,
        'warehouse_id' => $this->warehouse->id,
        'recall_type' => 'supplier_initiated',
        'reason' => 'Forbidden supplier',
        'items' => [[
            'product_id' => $product->id,
            'stock_batch_id' => $batch->id,
            'quantity_recalled' => 1,
            'unit_cost' => 10,
        ]],
    ])->assertForbidden();
});

test('scoped users cannot fetch batches for another supplier', function () {
    $ownSupplier = Supplier::factory()->create(['disabled' => false]);
    $otherSupplier = Supplier::factory()->create(['disabled' => false]);
    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    $this->get(route('api.suppliers.batches', $otherSupplier))
        ->assertForbidden();
});

test('get available batches filters by supplier and warehouse', function () {
    $batch1 = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'status' => 'active',
    ]);

    $otherSupplier = Supplier::factory()->create();
    $batch2 = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $otherSupplier->id,
        'status' => 'active',
    ]);

    recallLayerFor($batch1, 100);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch1->id,
        'quantity_on_hand' => 100,
        'unit_cost' => 50.00,
        'total_value' => 5000.00,
    ]);

    recallLayerFor($batch2, 75);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch2->id,
        'quantity_on_hand' => 75,
        'unit_cost' => 50.00,
        'total_value' => 3750.00,
    ]);

    $service = new ProductRecallService;
    $batches = $service->getAvailableBatches($this->supplier->id, $this->warehouse->id);

    expect($batches)->toHaveCount(1);
    expect($batches->first()->id)->toBe($batch1->id);
});

test('partial batch recall keeps batch active', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'status' => 'active',
        'is_active' => true,
    ]);

    recallLayerFor($batch, 100);

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 100,
        'unit_cost' => 50.00,
        'total_value' => 5000.00,
    ]);

    $recall = ProductRecall::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    ProductRecallItem::create([
        'product_recall_id' => $recall->id,
        'product_id' => $this->product->id,
        'stock_batch_id' => $batch->id,
        'quantity_recalled' => 30,
        'unit_cost' => 50.00,
        'total_value' => 1500.00,
    ]);

    $service = new ProductRecallService;
    $service->postRecall($recall);

    $batch->refresh();
    expect($batch->status)->toBe('active');
    expect($batch->is_active)->toBeTrue();
});
