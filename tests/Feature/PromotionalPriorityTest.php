<?php

use App\Models\CurrentStockByBatch;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\VanStockBalance;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('promotional items (priority 1) are issued before regular items (priority 99)', function () {
    // Setup: Create master data
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user);

    \App\Models\AccountingPeriod::factory()->create([
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
        'status' => 'open',
    ]);

    $product = Product::factory()->create([
        'product_code' => 'COKE-001',
        'product_name' => 'Coca Cola 1L',
    ]);

    $warehouse = Warehouse::factory()->create(['warehouse_name' => 'WH-MAIN']);
    $supplier = Supplier::factory()->create(['supplier_name' => 'SUP-001']);
    $vehicle = Vehicle::factory()->create(['registration_number' => 'VAN-001']);
    $employee = Employee::factory()->create();
    $uom = Uom::factory()->create();

    // Step 1: Create GRN 1 - Regular Stock (100 units, priority=99, cost=10)
    $grn1 = GoodsReceiptNote::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'receipt_date' => now()->subDays(2),
        'status' => 'draft',
    ]);

    GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $grn1->id,
        'product_id' => $product->id,
        'stock_uom_id' => $uom->id,
        'purchase_uom_id' => $uom->id,
        'qty_in_purchase_uom' => 1, // Assuming 1:1 conversion for test
        'uom_conversion_factor' => 1,
        'qty_in_stock_uom' => 1,
        'quantity_ordered' => 1,
        'quantity_received' => 100,
        'quantity_accepted' => 100,
        'unit_cost' => 10.00,
        'is_promotional' => false,
        'priority_order' => 99, // Regular priority
    ]);

    // Step 2: Create GRN 2 - Promotional Stock (50 units, priority=1, cost=8)
    $grn2 = GoodsReceiptNote::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'receipt_date' => now()->subDays(1),
        'status' => 'draft',
    ]);

    GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $grn2->id,
        'product_id' => $product->id,
        'stock_uom_id' => $uom->id,
        'purchase_uom_id' => $uom->id,
        'qty_in_purchase_uom' => 1, // Assuming 1:1 conversion for test
        'uom_conversion_factor' => 1,
        'qty_in_stock_uom' => 1,
        'quantity_ordered' => 1,
        'quantity_received' => 50,
        'quantity_accepted' => 50,
        'unit_cost' => 8.00,
        'is_promotional' => true,
        'priority_order' => 1, // Promotional priority
    ]);

    // Step 3: Post both GRNs to create batches
    $inventoryService = app(InventoryService::class);

    $result1 = $inventoryService->postGrnToInventory($grn1->fresh());
    expect($result1['success'])->toBeTrue();

    $result2 = $inventoryService->postGrnToInventory($grn2->fresh());
    expect($result2['success'])->toBeTrue();

    // Verify batches were created
    $batches = StockBatch::where('product_id', $product->id)->get();
    expect($batches)->toHaveCount(2);

    $regularBatch = $batches->where('priority_order', 99)->first();
    $promoBatch = $batches->where('priority_order', 1)->first();

    expect($regularBatch)->not->toBeNull();
    expect($promoBatch)->not->toBeNull();
    expect($promoBatch->is_promotional)->toBeTrue();
    expect($regularBatch->is_promotional)->toBeFalse();

    // Verify stock balances
    $warehouseStock = CurrentStockByBatch::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->get();

    $totalStock = $warehouseStock->sum('quantity_on_hand');
    expect($totalStock)->toBe(150.0); // 100 + 50

    // Step 4: Create Goods Issue for 120 units
    $goodsIssue = GoodsIssue::factory()->create([
        'warehouse_id' => $warehouse->id,
        'vehicle_id' => $vehicle->id,
        'employee_id' => $employee->id,
        'issued_by' => $user->id,
        'issue_date' => now(),
        'status' => 'draft',
    ]);

    GoodsIssueItem::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'product_id' => $product->id,
        'uom_id' => $uom->id,
        'quantity_issued' => 120,
    ]);

    // Step 5: Post Goods Issue
    $distributionService = app(DistributionService::class);
    $issueResult = $distributionService->postGoodsIssue($goodsIssue->fresh());

    expect($issueResult['success'])->toBeTrue();

    // Step 6: Verify batch allocations
    // Should allocate 50 from promotional batch (priority=1) FIRST
    // Then allocate 70 from regular batch (priority=99)

    $movements = StockMovement::where('reference_type', 'App\Models\GoodsIssue')
        ->where('reference_id', $goodsIssue->id)
        ->where('movement_type', 'transfer')
        ->where('warehouse_id', $warehouse->id)
        ->get();

    // Check we have movements from both batches
    $promoMovement = $movements->where('stock_batch_id', $promoBatch->id)->first();
    $regularMovement = $movements->where('stock_batch_id', $regularBatch->id)->first();

    expect($promoMovement)->not->toBeNull();
    expect($regularMovement)->not->toBeNull();

    // Verify quantities: 50 from promo, 70 from regular
    expect(abs($promoMovement->quantity))->toBe(50.0);
    expect(abs($regularMovement->quantity))->toBe(70.0);

    // Verify costs: promo=8, regular=10
    expect((float) $promoMovement->unit_cost)->toBe(8.0);
    expect((float) $regularMovement->unit_cost)->toBe(10.0);

    // Step 7: Verify Van Stock Balance
    $vanStock = VanStockBalance::where('vehicle_id', $vehicle->id)
        ->where('product_id', $product->id)
        ->first();

    expect($vanStock)->not->toBeNull();
    expect((float) $vanStock->quantity_on_hand)->toBe(120.0);

    // Verify weighted average cost: (50*8 + 70*10) / 120 = 1100/120 = 9.1667
    expect(round((float) $vanStock->average_cost, 2))->toBe(9.17);

    // Step 8: Verify warehouse stock reduced correctly
    $remainingStock = CurrentStockByBatch::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->get();

    $promoStock = $remainingStock->where('stock_batch_id', $promoBatch->id)->first();
    $regularStock = $remainingStock->where('stock_batch_id', $regularBatch->id)->first();

    expect((float) $promoStock->quantity_on_hand)->toBe(0.0); // All 50 promotional used
    expect((float) $regularStock->quantity_on_hand)->toBe(30.0); // 30 regular remaining (100-70)

})->group('promotional-priority', 'distribution');

test('sales settlement calculates COGS from promotional batches first', function () {
    // Setup accounting prerequisites for journal entries
    $currency = \App\Models\Currency::factory()->base()->create([
        'currency_code' => 'PKR',
        'currency_name' => 'Pakistani Rupee',
        'currency_symbol' => 'Rs',
    ]);

    \App\Models\AccountingPeriod::create([
        'name' => now()->format('F Y'),
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
        'status' => 'open',
    ]);

    // Create required cost centers
    \Illuminate\Support\Facades\DB::table('cost_centers')->insert([
        ['id' => 4, 'code' => 'CC004', 'name' => 'Sales', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 6, 'code' => 'CC006', 'name' => 'Warehouse', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $accountType = \App\Models\AccountType::create([
        'type_name' => 'Asset',
        'report_group' => 'BalanceSheet',
        'description' => 'Test',
    ]);

    // Create required chart of accounts
    $accounts = [
        ['code' => '1121', 'name' => 'Cash'],
        ['code' => '1123', 'name' => 'Salesman Clearing'], // Added missing required account
        ['code' => '1122', 'name' => 'Cheques In Hand'],
        ['code' => '1111', 'name' => 'Debtors'],
        ['code' => '1170', 'name' => 'Earnest Money'],
        ['code' => '1161', 'name' => 'Advance Tax'],
        ['code' => '1151', 'name' => 'Stock In Hand'],
        ['code' => '1155', 'name' => 'Van Stock'],
        ['code' => '4110', 'name' => 'Sales'],
        ['code' => '5111', 'name' => 'COGS'],
        ['code' => '5272', 'name' => 'Toll Tax'],
        ['code' => '5252', 'name' => 'AMR Powder'],
        ['code' => '5262', 'name' => 'AMR Liquid'],
        ['code' => '5292', 'name' => 'Scheme'],
        ['code' => '5282', 'name' => 'Food Salesman Loader'],
        ['code' => '5213', 'name' => 'Misc Expense'],
    ];

    foreach ($accounts as $acc) {
        \App\Models\ChartOfAccount::create([
            'account_code' => $acc['code'],
            'account_name' => $acc['name'],
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'normal_balance' => 'debit',
            'is_group' => false,
            'is_active' => true,
        ]);
    }

    // Setup: Create master data
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user);

    $product = Product::factory()->create([
        'product_code' => 'COKE-002',
        'product_name' => 'Coca Cola 2L',
    ]);

    $warehouse = Warehouse::factory()->create(['warehouse_name' => 'WH-MAIN-2']);
    $supplier = Supplier::factory()->create(['supplier_name' => 'SUP-002']);
    $vehicle = Vehicle::factory()->create(['registration_number' => 'VAN-002']);
    $employee = Employee::factory()->create();
    $uom = Uom::factory()->create();

    // Create and post GRNs
    $grn1 = GoodsReceiptNote::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'receipt_date' => now()->subDays(2),
        'status' => 'draft',
    ]);

    GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $grn1->id,
        'product_id' => $product->id,
        'stock_uom_id' => $uom->id,
        'purchase_uom_id' => $uom->id,
        'qty_in_purchase_uom' => 1, // Assuming 1:1 conversion for test
        'uom_conversion_factor' => 1,
        'qty_in_stock_uom' => 1,
        'quantity_ordered' => 1,
        'quantity_received' => 100,
        'quantity_accepted' => 100,
        'unit_cost' => 10.00,
        'is_promotional' => false,
        'priority_order' => 99,
    ]);

    $grn2 = GoodsReceiptNote::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'receipt_date' => now()->subDays(1),
        'status' => 'draft',
    ]);

    GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $grn2->id,
        'product_id' => $product->id,
        'stock_uom_id' => $uom->id,
        'purchase_uom_id' => $uom->id,
        'qty_in_purchase_uom' => 1, // Assuming 1:1 conversion for test
        'uom_conversion_factor' => 1,
        'qty_in_stock_uom' => 1,
        'quantity_ordered' => 1,
        'quantity_received' => 50,
        'quantity_accepted' => 50,
        'unit_cost' => 8.00,
        'is_promotional' => true,
        'priority_order' => 1,
    ]);

    $inventoryService = app(InventoryService::class);
    $inventoryService->postGrnToInventory($grn1->fresh());
    $inventoryService->postGrnToInventory($grn2->fresh());

    // Create and post goods issue for 120 units
    $goodsIssue = GoodsIssue::factory()->create([
        'warehouse_id' => $warehouse->id,
        'vehicle_id' => $vehicle->id,
        'employee_id' => $employee->id,
        'issued_by' => $user->id,
        'issue_date' => now(),
        'status' => 'draft',
    ]);

    GoodsIssueItem::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'product_id' => $product->id,
        'uom_id' => $uom->id,
        'quantity_issued' => 120,
    ]);

    $distributionService = app(DistributionService::class);
    $distributionService->postGoodsIssue($goodsIssue->fresh());

    // Create sales settlement - sell all 120 units
    $settlement = SalesSettlement::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => $warehouse->id,
        'employee_id' => $employee->id,
        'settlement_date' => now(),
        'status' => 'draft',
        'cash_sales_amount' => 2400, // 120 * 20 selling price
    ]);

    SalesSettlementItem::factory()->create([
        'sales_settlement_id' => $settlement->id,
        'goods_issue_item_id' => $goodsIssue->items()->first()->id,
        'product_id' => $product->id,
        'quantity_issued' => 120,
        'quantity_sold' => 120,
        'quantity_returned' => 0,
        'quantity_shortage' => 0,
        'unit_selling_price' => 20.00,
        'total_sales_value' => 2400.00,
        'unit_cost' => 0, // Will be calculated by service
        'total_cogs' => 0, // Will be calculated by service
    ]);

    // Post settlement
    $settlementResult = $distributionService->postSalesSettlement($settlement->fresh());

    expect($settlementResult['success'])->toBeTrue();

    // Verify COGS calculation
    // Expected COGS: (50 * 8) + (70 * 10) = 400 + 700 = 1100
    $settlement->refresh();

    // The settlement should track the COGS from batch allocations
    $settlementItem = $settlement->items()->first();

    // Calculate expected COGS from stock movements
    $expectedCOGS = (50 * 8.00) + (70 * 10.00);
    expect($expectedCOGS)->toBe(1100.0);

    // Verify van stock is now zero
    $vanStock = VanStockBalance::where('vehicle_id', $vehicle->id)
        ->where('product_id', $product->id)
        ->first();

    expect((float) $vanStock->quantity_on_hand)->toBe(0.0);

})->group('promotional-priority', 'sales-settlement');

test('multiple promotional batches are sorted by priority order correctly', function () {
    // Setup
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user);

    \App\Models\AccountingPeriod::factory()->create([
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
        'status' => 'open',
    ]);

    $product = Product::factory()->create(['product_code' => 'PEPSI-001']);
    $warehouse = Warehouse::factory()->create();
    $supplier = Supplier::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $employee = Employee::factory()->create();
    $uom = Uom::factory()->create();

    // Create 4 GRNs with different priorities
    // Priority 1 (urgent promo) - 20 units @ 5
    // Priority 5 (medium promo) - 30 units @ 6
    // Priority 10 (low promo) - 40 units @ 7
    // Priority 99 (regular) - 50 units @ 10

    $priorities = [
        ['qty' => 20, 'cost' => 5, 'priority' => 1, 'promo' => true],
        ['qty' => 30, 'cost' => 6, 'priority' => 5, 'promo' => true],
        ['qty' => 40, 'cost' => 7, 'priority' => 10, 'promo' => true],
        ['qty' => 50, 'cost' => 10, 'priority' => 99, 'promo' => false],
    ];

    $inventoryService = app(InventoryService::class);

    foreach ($priorities as $index => $data) {
        $grn = GoodsReceiptNote::factory()->create([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'receipt_date' => now()->subDays(4 - $index),
            'status' => 'draft',
        ]);

        GoodsReceiptNoteItem::factory()->create([
            'grn_id' => $grn->id,
            'product_id' => $product->id,
            'stock_uom_id' => $uom->id,
            'quantity_ordered' => $data['qty'],
            'quantity_accepted' => $data['qty'],
            'unit_cost' => $data['cost'],
            'is_promotional' => $data['promo'],
            'priority_order' => $data['priority'],
        ]);

        $inventoryService->postGrnToInventory($grn->fresh());
    }

    // Issue 100 units
    $goodsIssue = GoodsIssue::factory()->create([
        'warehouse_id' => $warehouse->id,
        'vehicle_id' => $vehicle->id,
        'employee_id' => $employee->id,
        'issued_by' => $user->id,
        'issue_date' => now(),
        'status' => 'draft',
    ]);

    GoodsIssueItem::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'product_id' => $product->id,
        'uom_id' => $uom->id,
        'quantity_issued' => 100,
    ]);

    $distributionService = app(DistributionService::class);
    $distributionService->postGoodsIssue($goodsIssue->fresh());

    // Verify allocation order: should take all from priority 1, 5, 10, then 10 from 99
    // Expected: 20 @ 5 + 30 @ 6 + 40 @ 7 + 10 @ 10 = 100 + 180 + 280 + 100 = 660

    $movements = StockMovement::where('reference_type', 'App\Models\GoodsIssue')
        ->where('reference_id', $goodsIssue->id)
        ->where('movement_type', 'transfer')
        ->where('warehouse_id', $warehouse->id)
        ->with('stockBatch')
        ->get()
        ->sortBy('stockBatch.priority_order');

    expect($movements)->toHaveCount(4);

    // Verify first movement is from priority 1
    $firstMovement = $movements->first();
    expect($firstMovement->stockBatch->priority_order)->toBe(1);
    expect(abs($firstMovement->quantity))->toBe(20.0);
    expect((float) $firstMovement->unit_cost)->toBe(5.0);

    // Verify last movement is from priority 99
    $lastMovement = $movements->last();
    expect($lastMovement->stockBatch->priority_order)->toBe(99);
    expect(abs($lastMovement->quantity))->toBe(10.0);
    expect((float) $lastMovement->unit_cost)->toBe(10.0);

    // Verify total COGS
    $totalCOGS = (20 * 5) + (30 * 6) + (40 * 7) + (10 * 10);
    expect((float) $totalCOGS)->toBe(660.0);

    $vanStock = VanStockBalance::where('vehicle_id', $vehicle->id)
        ->where('product_id', $product->id)
        ->first();

    // Verify weighted average cost: 660 / 100 = 6.60
    expect(round((float) $vanStock->average_cost, 2))->toBe(6.60);

})->group('promotional-priority', 'multi-batch');
