<?php

use App\Models\AccountingPeriod;
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
 * Regression coverage for the multi-line same-product GI breakdown bug.
 *
 * Before the `goods_issue_item_id` link was added to `stock_movements`, the
 * controllers attributed movements to lines via `(reference_id, product_id)`,
 * so a GI carrying multiple lines for the same product would inflate every
 * line's batch breakdown to ALL movements for that product on the GI. The
 * grand total ended up multiplied by the number of lines.
 *
 * @return array{user: User, product: Product, warehouse: Warehouse, supplier: Supplier, vehicle: Vehicle, employee: Employee, uom: Uom, batch: StockBatch}
 */
function setupSingleBatchStockForMultiLineTest(): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create();

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
        'product_code' => 'TEST-ML-001',
        'product_name' => 'Multi Line Test Product',
    ]);

    $warehouse = Warehouse::factory()->create(['warehouse_name' => 'WH-ML-TEST']);
    $supplier = Supplier::factory()->create(['supplier_name' => 'SUP-ML-TEST']);
    $vehicle = Vehicle::factory()->create(['registration_number' => 'VAN-ML-TEST']);
    $employee = Employee::factory()->create(['supplier_id' => $supplier->id]);
    $uom = Uom::factory()->create();

    // One GRN with 100 units @ cost 10, selling 15 — single batch.
    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'receipt_date' => now()->subDays(2),
        'status' => 'draft',
    ]);

    GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $grn->id,
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
        'priority_order' => 1,
    ]);

    auth()->login($user);

    $result = app(InventoryService::class)->postGrnToInventory($grn->fresh());
    expect($result['success'])->toBeTrue($result['message'] ?? 'GRN posting failed');

    $batch = StockBatch::where('product_id', $product->id)->firstOrFail();

    return compact('user', 'product', 'warehouse', 'supplier', 'vehicle', 'employee', 'uom', 'batch');
}

it('attributes each stock movement to its specific goods_issue_item when one product appears on multiple lines', function () {
    $data = setupSingleBatchStockForMultiLineTest();

    $goodsIssue = GoodsIssue::factory()->create([
        'warehouse_id' => $data['warehouse']->id,
        'vehicle_id' => $data['vehicle']->id,
        'employee_id' => $data['employee']->id,
        'issued_by' => $data['user']->id,
        'issue_date' => now(),
        'status' => 'draft',
    ]);

    // Two lines for the SAME product — 10 + 8 units = 18 total.
    $line1 = GoodsIssueItem::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'product_id' => $data['product']->id,
        'uom_id' => $data['uom']->id,
        'quantity_issued' => 10,
        'unit_cost' => 10.00,
        'selling_price' => 15.00,
    ]);

    $line2 = GoodsIssueItem::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'product_id' => $data['product']->id,
        'uom_id' => $data['uom']->id,
        'quantity_issued' => 8,
        'unit_cost' => 10.00,
        'selling_price' => 15.00,
    ]);

    $result = app(DistributionService::class)->postGoodsIssue($goodsIssue->fresh());
    expect($result['success'])->toBeTrue();

    $movements = StockMovement::where('reference_type', GoodsIssue::class)
        ->where('reference_id', $goodsIssue->id)
        ->where('movement_type', 'transfer')
        ->orderBy('id')
        ->get();

    // Single batch, so each line produces exactly one movement.
    expect($movements)->toHaveCount(2);

    $line1Movement = $movements->firstWhere('goods_issue_item_id', $line1->id);
    $line2Movement = $movements->firstWhere('goods_issue_item_id', $line2->id);

    expect($line1Movement)->not->toBeNull();
    expect($line2Movement)->not->toBeNull();
    expect((float) abs($line1Movement->quantity))->toBe(10.0);
    expect((float) abs($line2Movement->quantity))->toBe(8.0);
});

it('shows the correct per-line batch breakdown on the show page when one product appears on multiple lines', function () {
    $data = setupSingleBatchStockForMultiLineTest();

    $goodsIssue = GoodsIssue::factory()->create([
        'warehouse_id' => $data['warehouse']->id,
        'vehicle_id' => $data['vehicle']->id,
        'employee_id' => $data['employee']->id,
        'issued_by' => $data['user']->id,
        'issue_date' => now(),
        'status' => 'draft',
    ]);

    $line1 = GoodsIssueItem::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'product_id' => $data['product']->id,
        'uom_id' => $data['uom']->id,
        'quantity_issued' => 10,
        'unit_cost' => 10.00,
        'selling_price' => 15.00,
    ]);

    $line2 = GoodsIssueItem::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'product_id' => $data['product']->id,
        'uom_id' => $data['uom']->id,
        'quantity_issued' => 8,
        'unit_cost' => 10.00,
        'selling_price' => 15.00,
    ]);

    app(DistributionService::class)->postGoodsIssue($goodsIssue->fresh());

    // Drive the controller through HTTP so we exercise the actual show() pipeline
    // (the bug lived in the controller's batch_breakdown query, not the service).
    $this->actingAs($data['user']);
    $response = $this->get(route('goods-issues.show', $goodsIssue));
    $response->assertSuccessful();

    /** @var GoodsIssue $rendered */
    $rendered = $response->viewData('goodsIssue');
    $items = $rendered->items->keyBy('id');

    // Each line's calculated_total must reflect ONLY its own movements:
    // 10 * 15 = 150 and 8 * 15 = 120 — NOT both lines summed together.
    expect((float) $items[$line1->id]->calculated_total)->toBe(150.0);
    expect((float) $items[$line2->id]->calculated_total)->toBe(120.0);

    // Each line's batch breakdown must contain only one row with its own quantity.
    expect($items[$line1->id]->batch_breakdown)->toHaveCount(1);
    expect($items[$line2->id]->batch_breakdown)->toHaveCount(1);
    expect((float) $items[$line1->id]->batch_breakdown[0]['quantity'])->toBe(10.0);
    expect((float) $items[$line2->id]->batch_breakdown[0]['quantity'])->toBe(8.0);

    // Grand total across both lines = 270, NOT 540 (which is what the bug produced).
    $grandTotal = $rendered->items->sum('calculated_total');
    expect((float) $grandTotal)->toBe(270.0);
});
