<?php

use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\CurrentStockByBatch;
use App\Models\InventoryLedgerEntry;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockBatch;
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
    $currency = \App\Models\Currency::factory()->create();
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

    CostCenter::create(['code' => 'CC006', 'name' => 'Warehouse', 'is_active' => true]);
});

test('stock adjustment can be created as draft', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'status' => 'active',
    ]);

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

    $service = new StockAdjustmentService;
    $result = $service->createAdjustment($data);

    expect($result['success'])->toBeTrue();
    expect($result['data'])->toBeInstanceOf(StockAdjustment::class);
    expect($result['data']->adjustment_number)->toStartWith('SA-');
    expect($result['data']->status)->toBe('draft');
    expect($result['data']->items)->toHaveCount(1);
});

test('stock adjustment number is generated correctly', function () {
    $service = new StockAdjustmentService;
    $number = $service->generateAdjustmentNumber();
    $year = now()->year;

    expect($number)->toBe("SA-{$year}-0001");
});

test('stock adjustment can be posted', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'status' => 'active',
        'is_active' => true,
    ]);

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

    $service = new StockAdjustmentService;
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

    $service = new StockAdjustmentService;
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

    $service = new StockAdjustmentService;
    $service->postAdjustment($adjustment);

    $currentStock->refresh();
    expect($currentStock->quantity_on_hand)->toBe('85.00');
});

test('only draft adjustments can be posted', function () {
    $adjustment = StockAdjustment::factory()->create([
        'status' => 'posted',
    ]);

    $service = new StockAdjustmentService;
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
    $this->get(route('stock-adjustments.create'))
        ->assertSuccessful()
        ->assertViewIs('stock-adjustments.create')
        ->assertViewHas(['warehouses', 'products', 'uoms']);
});

test('batch status changes to depleted when fully adjusted', function () {
    $batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'status' => 'active',
        'is_active' => true,
    ]);

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

    $service = new StockAdjustmentService;
    $service->postAdjustment($adjustment);

    $batch->refresh();
    expect($batch->status)->toBe('depleted');
    expect($batch->is_active)->toBeFalse();
});
