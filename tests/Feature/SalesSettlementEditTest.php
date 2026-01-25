<?php

use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;

test('sales settlement edit page can be rendered', function () {
    $user = User::factory()->create(['is_super_admin' => 'Yes']);

    $employee = Employee::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $uom = Uom::factory()->create();
    $product = Product::factory()->create();

    $goodsIssue = GoodsIssue::factory()->create([
        'status' => 'issued',
        'warehouse_id' => $warehouse->id,
        'vehicle_id' => $vehicle->id,
        'employee_id' => $employee->id,
        'issued_by' => $user->id,
    ]);

    GoodsIssueItem::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'line_no' => 1,
        'product_id' => $product->id,
        'uom_id' => $uom->id,
        'quantity_issued' => 1,
        'unit_cost' => 10,
        'selling_price' => 15,
        'total_value' => 15,
    ]);

    $settlement = SalesSettlement::factory()->create([
        'status' => 'draft',
        'goods_issue_id' => $goodsIssue->id,
        'employee_id' => $employee->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $this->actingAs($user)
        ->get(route('sales-settlements.edit', $settlement, absolute: false))
        ->assertSuccessful()
        ->assertSee('Edit Sales Settlement');
});
