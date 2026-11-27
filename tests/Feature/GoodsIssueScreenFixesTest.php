<?php

use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;
use App\Services\InventoryService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

/**
 * 100 units of one product in a warehouse (cost 10, sells at 15) and a draft issue of 10 for a
 * salesman who has no supplier of his own, so the supplier comes from the form.
 *
 * @param  array<int, string>  $permissions
 * @return array{user: User, supplier: Supplier, product: Product, uom: Uom, warehouse: Warehouse, vehicle: Vehicle, employee: Employee, goodsIssue: GoodsIssue}
 */
function goodsIssueScreenFixture(array $permissions): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    seedGrnPostingAccounts();
    actingAs($user);

    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create(['supplier_id' => $supplier->id]);
    $warehouse = Warehouse::factory()->create();
    $vehicle = Vehicle::factory()->create(['supplier_id' => $supplier->id]);
    $employee = Employee::factory()->create(['supplier_id' => null]);
    $uom = Uom::factory()->create();

    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id, 'receipt_date' => now()->subDay(), 'status' => 'draft']);
    GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $grn->id, 'product_id' => $product->id, 'stock_uom_id' => $uom->id, 'purchase_uom_id' => $uom->id,
        'qty_in_purchase_uom' => 1, 'uom_conversion_factor' => 1, 'qty_in_stock_uom' => 1, 'quantity_ordered' => 1,
        'quantity_received' => 100, 'quantity_accepted' => 100, 'unit_cost' => 10.00, 'selling_price' => 15.00,
        'is_promotional' => false, 'priority_order' => 1,
    ]);
    expect(app(InventoryService::class)->postGrnToInventory($grn->fresh())['success'])->toBeTrue();

    $goodsIssue = GoodsIssue::factory()->create([
        'issue_date' => now()->toDateString(), 'warehouse_id' => $warehouse->id, 'vehicle_id' => $vehicle->id,
        'employee_id' => $employee->id, 'supplier_id' => $supplier->id, 'issued_by' => $user->id, 'status' => 'draft',
        'total_quantity' => 10, 'total_value' => 150,
    ]);
    GoodsIssueItem::factory()->create([
        'goods_issue_id' => $goodsIssue->id, 'line_no' => 1, 'product_id' => $product->id, 'uom_id' => $uom->id,
        'quantity_issued' => 10, 'unit_cost' => 10.00, 'selling_price' => 15.00, 'total_value' => 150,
    ]);

    return compact('user', 'supplier', 'product', 'uom', 'warehouse', 'vehicle', 'employee', 'goodsIssue');
}

it('keeps the supplier picked on the form when a draft for an unassigned salesman is saved', function () {
    $fx = goodsIssueScreenFixture(['goods-issue-list', 'goods-issue-edit']);

    put(route('goods-issues.update', $fx['goodsIssue']), [
        'supplier_ids' => [$fx['supplier']->id],
        'issue_date' => now()->toDateString(),
        'warehouse_id' => $fx['warehouse']->id,
        'vehicle_id' => $fx['vehicle']->id,
        'employee_id' => $fx['employee']->id,
        'items' => [['product_id' => $fx['product']->id, 'quantity_issued' => 12, 'unit_cost' => 10, 'selling_price' => 15, 'uom_id' => $fx['uom']->id]],
    ])->assertRedirect(route('goods-issues.show', $fx['goodsIssue']));

    expect($fx['goodsIssue']->fresh()->supplier_id)->toBe($fx['supplier']->id)
        ->and((float) $fx['goodsIssue']->fresh()->total_value)->toBe(180.0);
});

it('stops a supplier-scoped user from moving a draft to another supplier on save', function () {
    $fx = goodsIssueScreenFixture(['goods-issue-list', 'goods-issue-edit']);
    $fx['user']->update(['supplier_id' => $fx['supplier']->id]);
    $other = Supplier::factory()->create();
    $fx['vehicle']->update(['supplier_id' => null]);

    put(route('goods-issues.update', $fx['goodsIssue']), [
        'supplier_ids' => [$other->id],
        'issue_date' => now()->toDateString(),
        'warehouse_id' => $fx['warehouse']->id,
        'vehicle_id' => $fx['vehicle']->id,
        'employee_id' => $fx['employee']->id,
        'items' => [['product_id' => $fx['product']->id, 'quantity_issued' => 12, 'unit_cost' => 10, 'selling_price' => 15, 'uom_id' => $fx['uom']->id]],
    ])->assertForbidden();

    expect($fx['goodsIssue']->fresh()->supplier_id)->toBe($fx['supplier']->id);
});

it('shows the carton and pieces columns on the add-items page to users who may enter cartons', function () {
    $fx = goodsIssueScreenFixture(['goods-issue-list', 'goods-issue-edit', 'goods-issue-carton-entry']);
    expect(app(DistributionService::class)->postGoodsIssue($fx['goodsIssue']->fresh())['success'])->toBeTrue();

    get(route('goods-issues.items', $fx['goodsIssue']))
        ->assertOk()
        ->assertViewHas('canEnterCartons', true);
});

it('shows the saved value of a draft line when the warehouse has no stock for it', function () {
    $fx = goodsIssueScreenFixture(['goods-issue-list']);
    $empty = Warehouse::factory()->create();
    $fx['goodsIssue']->update(['warehouse_id' => $empty->id]);

    get(route('goods-issues.show', $fx['goodsIssue']))
        ->assertOk()
        ->assertSee('<small>Rs</small>150</p>', false)
        ->assertDontSee('<small>Rs</small>0</p>', false);
});
