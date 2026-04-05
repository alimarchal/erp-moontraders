<?php

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\CurrentStock;
use App\Models\CurrentStockByBatch;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockValuationLayer;
use App\Models\Supplier;
use App\Models\Uom;
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
    AccountingPeriod::create([
        'name' => 'Test Period',
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
        'status' => 'open',
    ]);

    $warehouse = Warehouse::first() ?? Warehouse::factory()->create();
    $vehicle = Vehicle::first() ?? Vehicle::factory()->create();
    $product = Product::first() ?? Product::factory()->create(['product_name' => 'Test Product']);
    $employee = Employee::first() ?? Employee::factory()->create();
    $uom = Uom::first() ?? Uom::factory()->create();

    // Create GL Accounts
    $currency = Currency::where('is_base_currency', true)->first()
        ?? Currency::factory()->create(['is_base_currency' => true]);
    $accountType = AccountType::first() ?? AccountType::factory()->create([
        'type_name' => 'Assets',
        'report_group' => 'BalanceSheet',
    ]);
    ChartOfAccount::firstOrCreate(
        ['account_code' => '1151'],
        [
            'account_name' => 'Stock In Hand',
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'is_active' => true,
            'normal_balance' => 'debit',
        ]
    );
    ChartOfAccount::firstOrCreate(
        ['account_code' => '1155'],
        [
            'account_name' => 'Van Stock',
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'is_active' => true,
            'normal_balance' => 'debit',
        ]
    );

    $stockBatch = StockBatch::create([
        'product_id' => $product->id,
        'batch_code' => 'BATCH-'.time(),
        'supplier_id' => Supplier::first()->id ?? Supplier::factory()->create()->id,
        'receipt_date' => now(),
        'manufacturing_date' => now()->subMonth(),
        'unit_cost' => 150.00,
        'selling_price' => 200.00,
        'expiry_date' => now()->addYear(),
        'status' => 'active',
    ]);

    // Create stock movement for the initial stock
    $movement = StockMovement::create([
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

    StockValuationLayer::create([
        'stock_batch_id' => $stockBatch->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'stock_movement_id' => $movement->id,
        'quantity_received' => 100,
        'quantity_remaining' => 100,
        'unit_cost' => 150.00,
        'total_value' => 15000.00,
        'receipt_date' => now(),
    ]);

    CurrentStockByBatch::create([
        'stock_batch_id' => $stockBatch->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => 100,
        'unit_cost' => 150.00,
        'total_value' => 15000.00,
        'selling_price' => 200.00,
        'status' => 'active',
    ]);

    // Ensure product assumes current stock exists (if simple current_stock table exists)
    // Some systems use CurrentStock aggregate
    CurrentStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => 100,
        'average_cost' => 150.00,
        'total_value' => 15000.00,
    ]);
    $uom = Uom::first() ?? Uom::factory()->create();

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
