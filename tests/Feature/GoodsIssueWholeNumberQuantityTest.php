<?php

use App\Models\AccountingPeriod;
use App\Models\Employee;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Regression test for a production incident where a batch's last fractional
 * "dust" quantity (e.g. 0.06 pieces) was issued on a Goods Issue for a
 * whole-number ("Piece") UOM. That quantity can never be sold/returned/
 * shortaged on the Sales Settlement screen (whole-number inputs), leaving
 * the settlement permanently stuck ("B/F Out" mismatch on post).
 */
function setupWholeNumberUomStock(): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create();

    foreach (['goods-issue-list', 'goods-issue-create', 'goods-issue-edit'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $user->givePermissionTo(['goods-issue-list', 'goods-issue-create', 'goods-issue-edit']);

    AccountingPeriod::factory()->create([
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
        'status' => 'open',
    ]);

    $uom = Uom::factory()->create(['uom_name' => 'Piece', 'must_be_whole_number' => true]);
    $product = Product::factory()->create([
        'product_code' => 'TEST-WN-001',
        'product_name' => 'Whole Number Test Product',
        'uom_id' => $uom->id,
    ]);

    $warehouse = Warehouse::factory()->create(['warehouse_name' => 'WH-WN-TEST']);
    $supplier = Supplier::factory()->create(['supplier_name' => 'SUP-WN-TEST']);
    $vehicle = Vehicle::factory()->create(['registration_number' => 'VAN-WN-TEST']);
    $employee = Employee::factory()->create(['supplier_id' => $supplier->id]);

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

    return compact('user', 'product', 'warehouse', 'supplier', 'vehicle', 'employee', 'uom');
}

test('store rejects fractional quantity_issued for a whole-number UOM', function () {
    $data = setupWholeNumberUomStock();
    $this->actingAs($data['user']);

    $response = $this->post(route('goods-issues.store'), [
        'issue_date' => now()->toDateString(),
        'warehouse_id' => $data['warehouse']->id,
        'vehicle_id' => $data['vehicle']->id,
        'employee_id' => $data['employee']->id,
        'items' => [
            [
                'product_id' => $data['product']->id,
                'quantity_issued' => 0.06,
                'unit_cost' => 10.00,
                'selling_price' => 15.00,
                'uom_id' => $data['uom']->id,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('items.0.quantity_issued');
});

test('store accepts whole quantity_issued for a whole-number UOM', function () {
    $data = setupWholeNumberUomStock();
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
    $response->assertSessionHasNoErrors();
});
