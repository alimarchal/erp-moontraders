<?php

use App\Models\AccountingPeriod;
use App\Models\CurrentStockByBatch;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Helper: set up common master data + stock for exclude_promotional tests.
 *
 * Creates one product with:
 *  - GRN 1: 100 regular units (priority 99, cost 10, selling 15)
 *  - GRN 2:  50 promo units   (priority 1,  cost 8,  selling 12)
 *
 * @return array{user: User, product: Product, warehouse: Warehouse, supplier: Supplier, vehicle: Vehicle, employee: Employee, uom: Uom, regularBatch: StockBatch, promoBatch: StockBatch}
 */
function setupStockWithPromotionalBatches(): array
{
    // Reset Spatie permission cache to avoid stale IDs across RefreshDatabase transactions
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create();

    // Assign required permissions
    $permissions = [
        'goods-issue-list',
        'goods-issue-create',
        'goods-issue-edit',
        'goods-issue-delete',
        'goods-issue-post',
    ];
    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $user->givePermissionTo($permissions);

    AccountingPeriod::factory()->create([
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
        'status' => 'open',
    ]);

    $product = Product::factory()->create([
        'product_code' => 'TEST-EP-001',
        'product_name' => 'Test Exclude Promo Product',
    ]);

    $warehouse = Warehouse::factory()->create(['warehouse_name' => 'WH-EP-TEST']);
    $supplier = Supplier::factory()->create(['supplier_name' => 'SUP-EP-TEST']);
    $vehicle = Vehicle::factory()->create(['registration_number' => 'VAN-EP-TEST']);
    $employee = Employee::factory()->create([
        'supplier_id' => $supplier->id,
    ]);
    $uom = Uom::factory()->create();

    // GRN 1: Regular stock (100 units)
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
        'qty_in_purchase_uom' => 1,
        'uom_conversion_factor' => 1,
        'qty_in_stock_uom' => 1,
        'quantity_ordered' => 1,
        'quantity_received' => 100,
        'quantity_accepted' => 100,
        'unit_cost' => 10.00,
        'selling_price' => 15.00,
        'is_promotional' => false,
        'priority_order' => 99,
    ]);

    // GRN 2: Promotional stock (50 units)
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
        'qty_in_purchase_uom' => 1,
        'uom_conversion_factor' => 1,
        'qty_in_stock_uom' => 1,
        'quantity_ordered' => 1,
        'quantity_received' => 50,
        'quantity_accepted' => 50,
        'unit_cost' => 8.00,
        'selling_price' => 12.00,
        'is_promotional' => true,
        'priority_order' => 1,
    ]);

    // Authenticate user so InventoryService can resolve auth()->id() for created_by FK
    auth()->login($user);

    // Post both GRNs
    $inventoryService = app(InventoryService::class);
    $result1 = $inventoryService->postGrnToInventory($grn1->fresh());
    expect($result1['success'])->toBeTrue($result1['message'] ?? 'GRN1 posting failed');

    $result2 = $inventoryService->postGrnToInventory($grn2->fresh());
    expect($result2['success'])->toBeTrue($result2['message'] ?? 'GRN2 posting failed');

    $batches = StockBatch::where('product_id', $product->id)->get();
    $regularBatch = $batches->where('priority_order', 99)->first();
    $promoBatch = $batches->where('priority_order', 1)->first();

    return compact('user', 'product', 'warehouse', 'supplier', 'vehicle', 'employee', 'uom', 'regularBatch', 'promoBatch');
}

// ---------------------------------------------------------------------------
// API: getProductStock
// ---------------------------------------------------------------------------

test('getProductStock returns all batches when exclude_promotional is not set', function () {
    $data = setupStockWithPromotionalBatches();
    $this->actingAs($data['user']);

    $response = $this->getJson(route('api.warehouses.products.stock', [
        'warehouse' => $data['warehouse']->id,
        'product' => $data['product']->id,
    ]));

    $response->assertSuccessful();
    $json = $response->json();

    expect((float) $json['available_quantity'])->toBe(150.0);
    expect($json['batches'])->toHaveCount(2);
})->group('exclude-promotional');

test('getProductStock excludes promotional batches when exclude_promotional=1', function () {
    $data = setupStockWithPromotionalBatches();
    $this->actingAs($data['user']);

    $response = $this->getJson(route('api.warehouses.products.stock', [
        'warehouse' => $data['warehouse']->id,
        'product' => $data['product']->id,
    ]).'?exclude_promotional=1');

    $response->assertSuccessful();
    $json = $response->json();

    expect((float) $json['available_quantity'])->toBe(100.0);
    expect($json['batches'])->toHaveCount(1);
    expect($json['batches'][0]['is_promotional'])->toBeFalse();
})->group('exclude-promotional');

// ---------------------------------------------------------------------------
// Store: persists exclude_promotional flag
// ---------------------------------------------------------------------------

test('store saves exclude_promotional flag on goods issue items', function () {
    $data = setupStockWithPromotionalBatches();
    $this->actingAs($data['user']);

    $response = $this->post(route('goods-issues.store'), [
        'issue_date' => now()->toDateString(),
        'warehouse_id' => $data['warehouse']->id,
        'vehicle_id' => $data['vehicle']->id,
        'employee_id' => $data['employee']->id,
        'items' => [
            [
                'product_id' => $data['product']->id,
                'quantity_issued' => 50,
                'unit_cost' => 10.00,
                'selling_price' => 15.00,
                'uom_id' => $data['uom']->id,
                'exclude_promotional' => 1,
            ],
        ],
    ]);

    $response->assertRedirect();

    $gi = GoodsIssue::latest('id')->first();
    expect($gi)->not->toBeNull();

    $item = $gi->items()->first();
    expect($item->exclude_promotional)->toBeTrue();
})->group('exclude-promotional');

test('store defaults exclude_promotional to false when not provided', function () {
    $data = setupStockWithPromotionalBatches();
    $this->actingAs($data['user']);

    $response = $this->post(route('goods-issues.store'), [
        'issue_date' => now()->toDateString(),
        'warehouse_id' => $data['warehouse']->id,
        'vehicle_id' => $data['vehicle']->id,
        'employee_id' => $data['employee']->id,
        'items' => [
            [
                'product_id' => $data['product']->id,
                'quantity_issued' => 50,
                'unit_cost' => 10.00,
                'selling_price' => 15.00,
                'uom_id' => $data['uom']->id,
            ],
        ],
    ]);

    $response->assertRedirect();

    $item = GoodsIssue::latest('id')->first()->items()->first();
    expect($item->exclude_promotional)->toBeFalse();
})->group('exclude-promotional');

// ---------------------------------------------------------------------------
// Validation: stock check respects exclude_promotional
// ---------------------------------------------------------------------------

test('store validation fails when non-promo stock is insufficient but total stock is enough', function () {
    $data = setupStockWithPromotionalBatches();
    $this->actingAs($data['user']);

    // Request 120 units with exclude_promotional=1 — only 100 non-promo available
    $response = $this->post(route('goods-issues.store'), [
        'issue_date' => now()->toDateString(),
        'warehouse_id' => $data['warehouse']->id,
        'vehicle_id' => $data['vehicle']->id,
        'employee_id' => $data['employee']->id,
        'items' => [
            [
                'product_id' => $data['product']->id,
                'quantity_issued' => 120,
                'unit_cost' => 10.00,
                'selling_price' => 15.00,
                'uom_id' => $data['uom']->id,
                'exclude_promotional' => 1,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('items.0.quantity_issued');
})->group('exclude-promotional');

test('store validation passes when total stock is enough without exclude_promotional', function () {
    $data = setupStockWithPromotionalBatches();
    $this->actingAs($data['user']);

    // Request 120 units without exclude_promotional — 150 total available
    $response = $this->post(route('goods-issues.store'), [
        'issue_date' => now()->toDateString(),
        'warehouse_id' => $data['warehouse']->id,
        'vehicle_id' => $data['vehicle']->id,
        'employee_id' => $data['employee']->id,
        'items' => [
            [
                'product_id' => $data['product']->id,
                'quantity_issued' => 120,
                'unit_cost' => 10.00,
                'selling_price' => 15.00,
                'uom_id' => $data['uom']->id,
                'exclude_promotional' => 0,
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
})->group('exclude-promotional');

// ---------------------------------------------------------------------------
// Posting: allocates only non-promotional batches
// ---------------------------------------------------------------------------

test('posting with exclude_promotional only allocates from non-promotional batches', function () {
    $data = setupStockWithPromotionalBatches();
    $this->actingAs($data['user']);

    $goodsIssue = GoodsIssue::factory()->create([
        'warehouse_id' => $data['warehouse']->id,
        'vehicle_id' => $data['vehicle']->id,
        'employee_id' => $data['employee']->id,
        'issued_by' => $data['user']->id,
        'issue_date' => now(),
        'status' => 'draft',
    ]);

    GoodsIssueItem::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'product_id' => $data['product']->id,
        'uom_id' => $data['uom']->id,
        'quantity_issued' => 80,
        'exclude_promotional' => true,
    ]);

    $result = app(DistributionService::class)->postGoodsIssue($goodsIssue->fresh());
    expect($result['success'])->toBeTrue();

    // Verify: only regular batch was used
    $movements = StockMovement::where('reference_type', GoodsIssue::class)
        ->where('reference_id', $goodsIssue->id)
        ->where('movement_type', 'transfer')
        ->where('warehouse_id', $data['warehouse']->id)
        ->get();

    expect($movements)->toHaveCount(1);
    expect($movements->first()->stock_batch_id)->toBe($data['regularBatch']->id);
    expect(abs($movements->first()->quantity))->toBe(80.0);

    // Promo stock should be untouched
    $promoStock = CurrentStockByBatch::where('stock_batch_id', $data['promoBatch']->id)
        ->where('warehouse_id', $data['warehouse']->id)
        ->first();
    expect((float) $promoStock->quantity_on_hand)->toBe(50.0);
})->group('exclude-promotional');

test('posting without exclude_promotional allocates from promotional batches first', function () {
    $data = setupStockWithPromotionalBatches();
    $this->actingAs($data['user']);

    $goodsIssue = GoodsIssue::factory()->create([
        'warehouse_id' => $data['warehouse']->id,
        'vehicle_id' => $data['vehicle']->id,
        'employee_id' => $data['employee']->id,
        'issued_by' => $data['user']->id,
        'issue_date' => now(),
        'status' => 'draft',
    ]);

    GoodsIssueItem::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'product_id' => $data['product']->id,
        'uom_id' => $data['uom']->id,
        'quantity_issued' => 80,
        'exclude_promotional' => false,
    ]);

    $result = app(DistributionService::class)->postGoodsIssue($goodsIssue->fresh());
    expect($result['success'])->toBeTrue();

    // Verify: promo batch used first (50), then regular (30)
    $movements = StockMovement::where('reference_type', GoodsIssue::class)
        ->where('reference_id', $goodsIssue->id)
        ->where('movement_type', 'transfer')
        ->where('warehouse_id', $data['warehouse']->id)
        ->get();

    $promoMovement = $movements->where('stock_batch_id', $data['promoBatch']->id)->first();
    $regularMovement = $movements->where('stock_batch_id', $data['regularBatch']->id)->first();

    expect($promoMovement)->not->toBeNull();
    expect($regularMovement)->not->toBeNull();
    expect(abs($promoMovement->quantity))->toBe(50.0);
    expect(abs($regularMovement->quantity))->toBe(30.0);
})->group('exclude-promotional');
