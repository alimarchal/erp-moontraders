<?php

use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses promotional batch selling price when calculating sales value', function () {
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user);

    $product = Product::factory()->create([
        'product_code' => 'PROMO-001',
        'product_name' => 'Promo Cola',
    ]);

    $warehouse = Warehouse::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $supplier = Supplier::factory()->create();
    $employee = Employee::factory()->create([
        'supplier_id' => $supplier->id,
    ]);
    $uom = Uom::factory()->create();

    // Create GRN with promotional pricing
    $grn = GoodsReceiptNote::factory()->create([
        'warehouse_id' => $warehouse->id,
        'supplier_id' => $supplier->id,
        'status' => 'draft',
        'receipt_date' => now()->toDateString(),
    ]);

    $grnItem = GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $grn->id,
        'product_id' => $product->id,
        'stock_uom_id' => $uom->id,
        'purchase_uom_id' => $uom->id,
        'quantity_ordered' => 10,
        'quantity_received' => 10,
        'quantity_accepted' => 10,
        'unit_cost' => 10.00,
        'selling_price' => 20.00,
        'is_promotional' => true,
        'promotional_price' => 15.00,
    ]);

    // Post GRN to create batch with promotional_selling_price copied
    $inventoryService = app(InventoryService::class);
    $inventoryService->postGrnToInventory($grn->fresh());

    $stockBatch = StockBatch::where('product_id', $product->id)->firstOrFail();

    // Create and post a goods issue for 5 units
    $goodsIssue = GoodsIssue::factory()->create([
        'warehouse_id' => $warehouse->id,
        'vehicle_id' => $vehicle->id,
        'employee_id' => $employee->id,
        'supplier_id' => $employee->supplier_id,
        'issued_by' => $user->id,
        'issue_date' => now()->toDateString(),
        'status' => 'draft',
    ]);

    $goodsIssueItem = GoodsIssueItem::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'product_id' => $product->id,
        'quantity_issued' => 5,
        'unit_cost' => 10.00,
        'uom_id' => $uom->id,
    ]);

    app(DistributionService::class)->postGoodsIssue($goodsIssue->fresh());

    // Build settlement payload - note selling_price sent as 20 but effective promo should be 15
    $payload = [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $goodsIssue->id,
        'cash_sales_amount' => 75.00,
        'cheque_sales_amount' => 0,
        'credit_sales_amount' => 0,
        'cash_collected' => 75.00,
        'expenses_claimed' => 0,
        'items' => [
            [
                'product_id' => $product->id,
                'goods_issue_item_id' => $goodsIssueItem->id,
                'quantity_issued' => 5,
                'quantity_sold' => 5,
                'quantity_returned' => 0,
                'quantity_shortage' => 0,
                'unit_cost' => 10.00,
                'selling_price' => 20.00, // sent from UI, but promo price should be used
                'batches' => [
                    [
                        'stock_batch_id' => $stockBatch->id,
                        'batch_code' => $stockBatch->batch_code,
                        'quantity_issued' => 5,
                        'quantity_sold' => 5,
                        'quantity_returned' => 0,
                        'quantity_shortage' => 0,
                        'unit_cost' => 10.00,
                        'selling_price' => 20.00,
                        'is_promotional' => true,
                    ],
                ],
            ],
        ],
    ];

    $response = $this->post(route('sales-settlements.store'), $payload);

    $response->assertRedirect();

    $settlement = \App\Models\SalesSettlement::latest('id')->with('items')->first();

    expect($settlement)->not->toBeNull();

    $item = $settlement->items->first();
    // Should use promotional price 15 * 5 = 75
    expect((float) $item->total_sales_value)->toBe(75.0);
    expect((float) $item->unit_selling_price)->toBe(15.0);
    expect((float) $settlement->gross_profit)->toBe(25.0); // 75 - (5 * 10)
});
