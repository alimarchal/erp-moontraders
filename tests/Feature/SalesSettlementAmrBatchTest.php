<?php

use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementAmrLiquid;
use App\Models\SalesSettlementAmrPowder;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper to build a complete settlement payload with AMR data.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function buildAmrSettlementPayload(array $overrides = []): array
{
    $user = User::factory()->create(['is_super_admin' => 'Yes']);
    test()->actingAs($user);

    $product = Product::factory()->create([
        'unit_sell_price' => 100,
        'cost_price' => 50,
    ]);
    $warehouse = Warehouse::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $supplier = Supplier::factory()->create();
    $employee = Employee::factory()->create(['supplier_id' => $supplier->id]);
    $uom = Uom::factory()->create();

    $grn = GoodsReceiptNote::factory()->create([
        'warehouse_id' => $warehouse->id,
        'supplier_id' => $supplier->id,
        'status' => 'draft',
        'receipt_date' => now()->toDateString(),
    ]);

    GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $grn->id,
        'product_id' => $product->id,
        'stock_uom_id' => $uom->id,
        'purchase_uom_id' => $uom->id,
        'quantity_ordered' => 100,
        'quantity_received' => 100,
        'quantity_accepted' => 100,
        'unit_cost' => 50.00,
        'selling_price' => 100.00,
    ]);

    app(InventoryService::class)->postGrnToInventory($grn->fresh());

    $stockBatch = StockBatch::where('product_id', $product->id)->firstOrFail();

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
        'quantity_issued' => 10,
        'unit_cost' => 50.00,
        'uom_id' => $uom->id,
    ]);

    app(DistributionService::class)->postGoodsIssue($goodsIssue->fresh());

    $payload = [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $goodsIssue->id,
        'cash_sales_amount' => 1000.00,
        'cheque_sales_amount' => 0,
        'credit_sales_amount' => 0,
        'cash_collected' => 1000.00,
        'expenses_claimed' => 0,
        'items' => [
            [
                'product_id' => $product->id,
                'goods_issue_item_id' => $goodsIssueItem->id,
                'quantity_issued' => 10,
                'quantity_sold' => 10,
                'quantity_returned' => 0,
                'quantity_shortage' => 0,
                'unit_cost' => 50.00,
                'selling_price' => 100.00,
                'batches' => [
                    [
                        'stock_batch_id' => $stockBatch->id,
                        'batch_code' => $stockBatch->batch_code,
                        'quantity_issued' => 10,
                        'quantity_sold' => 10,
                        'quantity_returned' => 0,
                        'quantity_shortage' => 0,
                        'unit_cost' => 50.00,
                        'selling_price' => 100.00,
                        'is_promotional' => false,
                    ],
                ],
            ],
        ],
    ];

    return array_merge($payload, $overrides, [
        '_context' => compact('product', 'stockBatch', 'warehouse', 'vehicle', 'employee', 'supplier'),
    ]);
}

it('fetches batches for a product via API endpoint', function () {
    $user = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->actingAs($user);

    $product = Product::factory()->create();
    StockBatch::factory()->create([
        'product_id' => $product->id,
        'batch_code' => 'AMR-BATCH-001',
        'selling_price' => 50.00,
        'status' => 'active',
    ]);
    StockBatch::factory()->create([
        'product_id' => $product->id,
        'batch_code' => 'AMR-BATCH-002',
        'selling_price' => 60.00,
        'status' => 'active',
    ]);
    StockBatch::factory()->create([
        'product_id' => $product->id,
        'batch_code' => 'AMR-BATCH-DEP',
        'selling_price' => 40.00,
        'status' => 'depleted',
    ]);

    $response = $this->getJson(route('api.products.amr-batches', $product));

    $response->assertSuccessful()
        ->assertJsonCount(2)
        ->assertJsonFragment(['batch_code' => 'AMR-BATCH-001', 'selling_price' => '50.00'])
        ->assertJsonFragment(['batch_code' => 'AMR-BATCH-002', 'selling_price' => '60.00'])
        ->assertJsonMissing(['batch_code' => 'AMR-BATCH-DEP']);
});

it('stores AMR powder entries with batch fields when use_batch_expiry is true', function () {
    config(['app.use_batch_expiry' => true]);

    $payload = buildAmrSettlementPayload();
    unset($payload['_context']);

    $amrProduct = Product::factory()->create(['is_powder' => true, 'expiry_price' => 25.00]);
    $amrBatch = StockBatch::factory()->create([
        'product_id' => $amrProduct->id,
        'batch_code' => 'PWD-BATCH-001',
        'selling_price' => 30.00,
        'status' => 'active',
    ]);

    $payload['amr_powders'] = json_encode([
        [
            'product_id' => $amrProduct->id,
            'stock_batch_id' => $amrBatch->id,
            'batch_code' => 'PWD-BATCH-001',
            'quantity' => 5,
            'amount' => 150.00,
        ],
    ]);

    $response = $this->post(route('sales-settlements.store'), $payload);
    $response->assertRedirect();

    $settlement = SalesSettlement::latest('id')->first();
    $amrPowder = SalesSettlementAmrPowder::where('sales_settlement_id', $settlement->id)->first();

    expect($amrPowder)->not->toBeNull()
        ->and((int) $amrPowder->product_id)->toBe($amrProduct->id)
        ->and((int) $amrPowder->stock_batch_id)->toBe($amrBatch->id)
        ->and($amrPowder->batch_code)->toBe('PWD-BATCH-001')
        ->and((float) $amrPowder->quantity)->toBe(5.0)
        ->and((float) $amrPowder->amount)->toBe(150.0);
});

it('stores AMR liquid entries with batch fields when use_batch_expiry is true', function () {
    config(['app.use_batch_expiry' => true]);

    $payload = buildAmrSettlementPayload();
    unset($payload['_context']);

    $amrProduct = Product::factory()->create(['is_powder' => false, 'expiry_price' => 20.00]);
    $amrBatch = StockBatch::factory()->create([
        'product_id' => $amrProduct->id,
        'batch_code' => 'LIQ-BATCH-001',
        'selling_price' => 35.00,
        'status' => 'active',
    ]);

    $payload['amr_liquids'] = json_encode([
        [
            'product_id' => $amrProduct->id,
            'stock_batch_id' => $amrBatch->id,
            'batch_code' => 'LIQ-BATCH-001',
            'quantity' => 3,
            'amount' => 105.00,
        ],
    ]);

    $response = $this->post(route('sales-settlements.store'), $payload);
    $response->assertRedirect();

    $settlement = SalesSettlement::latest('id')->first();
    $amrLiquid = SalesSettlementAmrLiquid::where('sales_settlement_id', $settlement->id)->first();

    expect($amrLiquid)->not->toBeNull()
        ->and((int) $amrLiquid->product_id)->toBe($amrProduct->id)
        ->and((int) $amrLiquid->stock_batch_id)->toBe($amrBatch->id)
        ->and($amrLiquid->batch_code)->toBe('LIQ-BATCH-001')
        ->and((float) $amrLiquid->quantity)->toBe(3.0)
        ->and((float) $amrLiquid->amount)->toBe(105.0);
});

it('stores AMR entries without batch fields when use_batch_expiry is false', function () {
    config(['app.use_batch_expiry' => false]);

    $payload = buildAmrSettlementPayload();
    unset($payload['_context']);

    $amrProduct = Product::factory()->create(['is_powder' => true, 'expiry_price' => 25.00]);

    $payload['amr_powders'] = json_encode([
        [
            'product_id' => $amrProduct->id,
            'quantity' => 4,
            'amount' => 100.00,
        ],
    ]);

    $response = $this->post(route('sales-settlements.store'), $payload);
    $response->assertRedirect();

    $settlement = SalesSettlement::latest('id')->first();
    $amrPowder = SalesSettlementAmrPowder::where('sales_settlement_id', $settlement->id)->first();

    expect($amrPowder)->not->toBeNull()
        ->and((int) $amrPowder->product_id)->toBe($amrProduct->id)
        ->and($amrPowder->stock_batch_id)->toBeNull()
        ->and($amrPowder->batch_code)->toBeNull()
        ->and((float) $amrPowder->quantity)->toBe(4.0)
        ->and((float) $amrPowder->amount)->toBe(100.0);
});

it('passes use_batch_expiry flag to create view', function () {
    config(['app.use_batch_expiry' => true]);

    $user = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->actingAs($user);

    $response = $this->get(route('sales-settlements.create'));

    $response->assertSuccessful()
        ->assertViewHas('useBatchExpiry', true);
});

it('passes use_batch_expiry flag as false to create view', function () {
    config(['app.use_batch_expiry' => false]);

    $user = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->actingAs($user);

    $response = $this->get(route('sales-settlements.create'));

    $response->assertSuccessful()
        ->assertViewHas('useBatchExpiry', false);
});

it('includes expiry_price in product data passed to create view', function () {
    config(['app.use_batch_expiry' => false]);

    $user = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->actingAs($user);

    Product::factory()->create(['is_powder' => true, 'expiry_price' => 42.50, 'is_active' => true]);

    $response = $this->get(route('sales-settlements.create'));

    $response->assertSuccessful();
    $powderProducts = $response->viewData('powderProducts');
    expect($powderProducts->first()->expiry_price)->not->toBeNull();
});
