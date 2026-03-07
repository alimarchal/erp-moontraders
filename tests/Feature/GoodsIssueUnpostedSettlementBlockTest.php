<?php

use App\Models\Employee;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'goods-issue-create', 'guard_name' => 'web']);

    $this->user = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->actingAs($this->user);

    $this->vehicle = Vehicle::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->employee = Employee::factory()->create();
    $this->product = Product::factory()->create();
    $this->uom = Uom::factory()->create();
});

it('blocks goods issue creation when vehicle has a draft settlement', function () {
    SalesSettlement::factory()->create([
        'vehicle_id' => $this->vehicle->id,
        'status' => 'draft',
        'settlement_number' => 'SETTLE-TEST-9001',
    ]);

    $response = $this->post(route('goods-issues.store'), [
        'issue_date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'vehicle_id' => $this->vehicle->id,
        'employee_id' => $this->employee->id,
        'items' => [[
            'product_id' => $this->product->id,
            'quantity_issued' => 10,
            'unit_cost' => 100,
            'selling_price' => 150,
            'uom_id' => $this->uom->id,
        ]],
    ]);

    $response->assertSessionHasErrors('vehicle_id');
    expect(session('errors')->get('vehicle_id')[0])->toContain('SETTLE-TEST-9001');
});

it('blocks goods issue creation when vehicle has a verified (unposted) settlement', function () {
    SalesSettlement::factory()->create([
        'vehicle_id' => $this->vehicle->id,
        'status' => 'verified',
        'settlement_number' => 'SETTLE-TEST-9002',
    ]);

    $response = $this->post(route('goods-issues.store'), [
        'issue_date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'vehicle_id' => $this->vehicle->id,
        'employee_id' => $this->employee->id,
        'items' => [[
            'product_id' => $this->product->id,
            'quantity_issued' => 10,
            'unit_cost' => 100,
            'selling_price' => 150,
            'uom_id' => $this->uom->id,
        ]],
    ]);

    $response->assertSessionHasErrors('vehicle_id');
});

it('allows goods issue creation when vehicle has only posted settlements', function () {
    SalesSettlement::factory()->create([
        'vehicle_id' => $this->vehicle->id,
        'status' => 'posted',
    ]);

    $response = $this->post(route('goods-issues.store'), [
        'issue_date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'vehicle_id' => $this->vehicle->id,
        'employee_id' => $this->employee->id,
        'items' => [[
            'product_id' => $this->product->id,
            'quantity_issued' => 10,
            'unit_cost' => 100,
            'selling_price' => 150,
            'uom_id' => $this->uom->id,
        ]],
    ]);

    // vehicle_id itself must not have a validation error — other errors (e.g. stock) are irrelevant
    $response->assertSessionDoesntHaveErrors('vehicle_id');
});

it('allows goods issue creation when vehicle has no settlements at all', function () {
    $response = $this->post(route('goods-issues.store'), [
        'issue_date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'vehicle_id' => $this->vehicle->id,
        'employee_id' => $this->employee->id,
        'items' => [[
            'product_id' => $this->product->id,
            'quantity_issued' => 10,
            'unit_cost' => 100,
            'selling_price' => 150,
            'uom_id' => $this->uom->id,
        ]],
    ]);

    $response->assertSessionDoesntHaveErrors('vehicle_id');
});

it('only blocks for the specific vehicle and not other vehicles', function () {
    $otherVehicle = Vehicle::factory()->create();

    // Draft settlement on OTHER vehicle — should not block THIS vehicle
    SalesSettlement::factory()->create([
        'vehicle_id' => $otherVehicle->id,
        'status' => 'draft',
    ]);

    $response = $this->post(route('goods-issues.store'), [
        'issue_date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'vehicle_id' => $this->vehicle->id,
        'employee_id' => $this->employee->id,
        'items' => [[
            'product_id' => $this->product->id,
            'quantity_issued' => 10,
            'unit_cost' => 100,
            'selling_price' => 150,
            'uom_id' => $this->uom->id,
        ]],
    ]);

    $response->assertSessionDoesntHaveErrors('vehicle_id');
});
