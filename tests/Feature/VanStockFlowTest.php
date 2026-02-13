<?php

use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;

use function Pest\Laravel\actingAs;

it('creates van stock batches when goods issue is posted', function () {
    // 1. Setup Data
    $user = User::factory()->create();
    actingAs($user);

    // Create Accounting Period
    \App\Models\AccountingPeriod::create([
        'name' => 'Test Period',
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
        'status' => 'open',
    ]);

    $warehouse = Warehouse::first() ?? Warehouse::factory()->create();
    $vehicle = Vehicle::first() ?? Vehicle::factory()->create();
    $product = Product::first() ?? Product::factory()->create(['product_name' => 'Test Product']);
    $employee = Employee::first() ?? Employee::factory()->create();
    $uom = \App\Models\Uom::first() ?? \App\Models\Uom::factory()->create();

    // Create GL Accounts
    $currency = \App\Models\Currency::where('is_base_currency', true)->first()
        ?? \App\Models\Currency::factory()->create(['is_base_currency' => true]);
    $accountType = \App\Models\AccountType::first() ?? \App\Models\AccountType::factory()->create([
        'type_name' => 'Assets',
        'report_group' => 'BalanceSheet',
    ]);
    \App\Models\ChartOfAccount::firstOrCreate(
        ['account_code' => '1151'],
        [
            'account_name' => 'Stock In Hand',
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'is_active' => true,
        ]
    );
    \App\Models\ChartOfAccount::firstOrCreate(
        ['account_code' => '1155'],
        [
            'account_name' => 'Van Stock',
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'is_active' => true,
        ]
    );

    $stockBatch = \App\Models\StockBatch::create([
        'product_id' => $product->id,
        'batch_code' => 'BATCH-'.time(),
        'supplier_id' => \App\Models\Supplier::first()->id ?? \App\Models\Supplier::factory()->create()->id,
        'receipt_date' => now(),
        'manufacturing_date' => now()->subMonth(),
        'unit_cost' => 150.00,
        'selling_price' => 200.00,
        'expiry_date' => now()->addYear(),
        'status' => 'active',
    ]);

    // Create stock movement for the initial stock
    $movement = \App\Models\StockMovement::create([
        'movement_type' => 'grn',
        'reference_type' => 'App\Models\StockBatch',
        'reference_id' => $stockBatch->id,
        'movement_date' => now(),
        'product_id' => $product->id,
        'stock_batch_id' => $stockBatch->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'uom_id' => $uom->id,
        'unit_cost' => 150.00,
        'total_value' => 15000.00,
        'created_by' => $user->id,
    ]);

    \App\Models\StockValuationLayer::create([
        'stock_batch_id' => $stockBatch->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'stock_movement_id' => $movement->id,
        'quantity_received' => 100,
        'quantity_remaining' => 100,
        'rate' => 150.00,
        'unit_cost' => 150.00,
        'value' => 15000.00,
        'valuation_method' => 'FIFO',
        'receipt_date' => now(),
    ]);

    \App\Models\CurrentStockByBatch::create([
        'stock_batch_id' => $stockBatch->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => 100,
        'unit_cost' => 150.00,
        'selling_price' => 200.00, // Likely needed
        'status' => 'active',
    ]);

    // Ensure product assumes current stock exists (if simple current_stock table exists)
    // Some systems use CurrentStock aggregate
    \App\Models\CurrentStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => 100,
    ]);
    $uom = \App\Models\Uom::first() ?? \App\Models\Uom::factory()->create();

    // 2. Create Goods Issue
    $issueNumber = 'GI-TEST-'.time();

    $goodsIssue = GoodsIssue::create([
        'warehouse_id' => $warehouse->id,
        'vehicle_id' => $vehicle->id,
        'employee_id' => $employee->id,
        'issued_by' => $user->id,
        'issue_date' => now(),
        'issue_number' => $issueNumber,
        'status' => 'draft',
        'notes' => 'Automated Test for Van Stock',
    ]);

    $item = GoodsIssueItem::create([
        'goods_issue_id' => $goodsIssue->id,
        'product_id' => $product->id,
        'uom_id' => $uom->id,
        'batch_no' => 'BATCH-'.time(),
        'quantity_issued' => 10,
        'unit_cost' => 150.00,
        'selling_price' => 200.00,
        'total_cogs' => 1500.00,
        'total_value' => 2000.00,
    ]);

    // 3. Trigger Service
    $service = app(DistributionService::class);
    $goodsIssue->refresh(); // Ensure items are loaded
    $service->postGoodsIssue($goodsIssue);

    // 4. Verify Batch
    $this->assertDatabaseHas('van_stock_batches', [
        'goods_issue_number' => $issueNumber,
        'product_id' => $product->id,
        'vehicle_id' => $vehicle->id,
        'quantity_on_hand' => 10,
        'unit_cost' => 150.00,
        'selling_price' => 200.00,
    ]);
});
