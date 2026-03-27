<?php

use App\Models\Employee;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementAmrLiquid;
use App\Models\SalesSettlementAmrPowder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'report-audit-sku-fmr-amr']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-audit-sku-fmr-amr');

    $this->supplier = Supplier::factory()->create([
        'supplier_name' => 'Nestlé Pakistan',
    ]);
});

test('sku fmr amr report page loads for authenticated user', function () {
    $this->actingAs($this->user)
        ->get(route('reports.sku-fmr-amr.index'))
        ->assertSuccessful();
});

test('sku fmr amr report requires authentication', function () {
    $this->get(route('reports.sku-fmr-amr.index'))
        ->assertRedirect(route('login'));
});

test('sku fmr amr report is forbidden without permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.sku-fmr-amr.index'))
        ->assertForbidden();
});

test('sku fmr amr report defaults to nestle supplier', function () {
    $this->actingAs($this->user);

    $response = $this->get(route('reports.sku-fmr-amr.index'));

    $response->assertSuccessful();
    expect($response->viewData('selectedSupplierId'))->toBe($this->supplier->id);
});

test('sku fmr amr report shows all supplier skus with zero defaults when no data', function () {
    $this->actingAs($this->user);

    $product = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'is_active' => true,
    ]);

    $response = $this->get(route('reports.sku-fmr-amr.index', [
        'filter' => [
            'supplier_id' => $this->supplier->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ],
    ]));

    $response->assertSuccessful();

    $reportData = $response->viewData('reportData');

    // Product should be present even with no FMR/AMR data
    $row = $reportData->firstWhere('product_id', $product->id);
    expect($row)->not->toBeNull()
        ->and((float) $row->fmr_amount)->toBe(0.0)
        ->and((float) $row->amr_liquid_amount)->toBe(0.0)
        ->and((float) $row->amr_powder_amount)->toBe(0.0)
        ->and((float) $row->total_amr)->toBe(0.0)
        ->and((float) $row->difference)->toBe(0.0);
});

test('sku fmr amr report calculates fmr from grn items', function () {
    $this->actingAs($this->user);

    $product = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'is_active' => true,
        'is_powder' => false,
    ]);

    $warehouse = Warehouse::factory()->create();

    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $warehouse->id,
        'receipt_date' => '2025-06-15',
        'status' => 'posted',
    ]);

    // Create GRN item with fmr_allowance
    GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $grn->id,
        'product_id' => $product->id,
        'fmr_allowance' => 1500.00,
    ]);

    $response = $this->get(route('reports.sku-fmr-amr.index', [
        'filter' => [
            'supplier_id' => $this->supplier->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ],
    ]));

    $response->assertSuccessful();

    $reportData = $response->viewData('reportData');
    $row = $reportData->firstWhere('product_id', $product->id);

    expect($row)->not->toBeNull()
        ->and((float) $row->fmr_amount)->toBe(1500.0);
});

test('sku fmr amr report calculates amr from sales settlements', function () {
    $this->actingAs($this->user);

    $product = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'is_active' => true,
        'is_powder' => true,
    ]);

    $settlement = SalesSettlement::factory()->create([
        'settlement_date' => '2025-06-20',
        'status' => 'posted',
        'warehouse_id' => Warehouse::factory(),
        'vehicle_id' => Vehicle::factory(),
        'employee_id' => Employee::factory(),
    ]);

    SalesSettlementAmrLiquid::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'amount' => 400.00,
    ]);

    SalesSettlementAmrPowder::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'amount' => 600.00,
    ]);

    $response = $this->get(route('reports.sku-fmr-amr.index', [
        'filter' => [
            'supplier_id' => $this->supplier->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ],
    ]));

    $response->assertSuccessful();

    $reportData = $response->viewData('reportData');
    $row = $reportData->firstWhere('product_id', $product->id);

    expect($row)->not->toBeNull()
        ->and((float) $row->amr_liquid_amount)->toBe(400.0)
        ->and((float) $row->amr_powder_amount)->toBe(600.0)
        ->and((float) $row->total_amr)->toBe(1000.0);
});

test('sku fmr amr report computes net difference correctly', function () {
    $this->actingAs($this->user);

    $product = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'is_active' => true,
    ]);

    $warehouse = Warehouse::factory()->create();

    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $warehouse->id,
        'receipt_date' => '2025-06-15',
        'status' => 'posted',
    ]);

    GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $grn->id,
        'product_id' => $product->id,
        'fmr_allowance' => 2000.00,
    ]);

    $settlement = SalesSettlement::factory()->create([
        'settlement_date' => '2025-06-20',
        'status' => 'posted',
        'warehouse_id' => $warehouse->id,
        'vehicle_id' => Vehicle::factory(),
        'employee_id' => Employee::factory(),
    ]);

    SalesSettlementAmrLiquid::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'amount' => 800.00,
    ]);

    SalesSettlementAmrPowder::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'amount' => 500.00,
    ]);

    $response = $this->get(route('reports.sku-fmr-amr.index', [
        'filter' => [
            'supplier_id' => $this->supplier->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ],
    ]));

    $response->assertSuccessful();

    $reportData = $response->viewData('reportData');
    $grandTotals = $response->viewData('grandTotals');
    $row = $reportData->firstWhere('product_id', $product->id);

    // FMR=2000, AMR=1300, Net=700
    expect((float) $row->fmr_amount)->toBe(2000.0)
        ->and((float) $row->total_amr)->toBe(1300.0)
        ->and((float) $row->difference)->toBe(700.0)
        ->and((float) $grandTotals->difference)->toBe(700.0);
});

test('sku fmr amr report type filter shows only liquid products', function () {
    $this->actingAs($this->user);

    $liquid = Product::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true, 'is_powder' => false]);
    $powder = Product::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true, 'is_powder' => true]);

    $response = $this->get(route('reports.sku-fmr-amr.index', [
        'filter' => [
            'supplier_id' => $this->supplier->id,
            'type' => 'liquid',
        ],
    ]));

    $response->assertSuccessful();

    $reportData = $response->viewData('reportData');
    $ids = $reportData->pluck('product_id')->toArray();

    expect($ids)->toContain($liquid->id)
        ->and($ids)->not->toContain($powder->id);
});

test('sku fmr amr report type filter shows only powder products', function () {
    $this->actingAs($this->user);

    $liquid = Product::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true, 'is_powder' => false]);
    $powder = Product::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true, 'is_powder' => true]);

    $response = $this->get(route('reports.sku-fmr-amr.index', [
        'filter' => [
            'supplier_id' => $this->supplier->id,
            'type' => 'powder',
        ],
    ]));

    $response->assertSuccessful();

    $reportData = $response->viewData('reportData');
    $ids = $reportData->pluck('product_id')->toArray();

    expect($ids)->toContain($powder->id)
        ->and($ids)->not->toContain($liquid->id);
});

test('sku fmr amr report product filter limits rows', function () {
    $this->actingAs($this->user);

    $productA = Product::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true]);
    $productB = Product::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true]);

    $response = $this->get(route('reports.sku-fmr-amr.index', [
        'filter' => [
            'supplier_id' => $this->supplier->id,
            'product_ids' => [$productA->id],
        ],
    ]));

    $response->assertSuccessful();

    $reportData = $response->viewData('reportData');
    $ids = $reportData->pluck('product_id')->toArray();

    expect($ids)->toContain($productA->id)
        ->and($ids)->not->toContain($productB->id);
});

test('sku fmr amr report excludes non-posted grns', function () {
    $this->actingAs($this->user);

    $product = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'is_active' => true,
    ]);

    $warehouse = Warehouse::factory()->create();

    $draftGrn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $warehouse->id,
        'receipt_date' => '2025-06-15',
        'status' => 'draft',
    ]);

    GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $draftGrn->id,
        'product_id' => $product->id,
        'fmr_allowance' => 999.00,
    ]);

    $response = $this->get(route('reports.sku-fmr-amr.index', [
        'filter' => [
            'supplier_id' => $this->supplier->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ],
    ]));

    $response->assertSuccessful();

    $reportData = $response->viewData('reportData');
    $row = $reportData->firstWhere('product_id', $product->id);

    // Draft GRN should not contribute FMR
    expect((float) $row->fmr_amount)->toBe(0.0);
});
