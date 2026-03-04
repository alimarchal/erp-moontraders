<?php

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'goods-issue-list',
        'goods-issue-create',
        'goods-issue-edit',
        'goods-issue-carton-entry',
    ] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('passes canEnterCartons as false to create view when user lacks permission', function () {
    $this->user->givePermissionTo('goods-issue-create');

    $response = $this->get(route('goods-issues.create'));

    $response->assertSuccessful();
    $response->assertViewHas('canEnterCartons', false);
});

it('passes canEnterCartons as true to create view when user has permission', function () {
    $this->user->givePermissionTo(['goods-issue-create', 'goods-issue-carton-entry']);

    $response = $this->get(route('goods-issues.create'));

    $response->assertSuccessful();
    $response->assertViewHas('canEnterCartons', true);
});

it('does not show carton and pieces columns without carton-entry permission', function () {
    $this->user->givePermissionTo('goods-issue-create');

    $response = $this->get(route('goods-issues.create'));

    $response->assertSuccessful();
    $response->assertDontSee('Auto-calculated from Carton + Pieces');
});

it('shows carton and pieces columns with carton-entry permission', function () {
    $this->user->givePermissionTo(['goods-issue-create', 'goods-issue-carton-entry']);

    $response = $this->get(route('goods-issues.create'));

    $response->assertSuccessful();
    $response->assertSee('Auto-calculated from Carton + Pieces');
});

it('returns conversion_factor in product stock API response', function () {
    $this->user->givePermissionTo('goods-issue-create');

    $uom = Uom::factory()->create(['uom_name' => 'Piece', 'enabled' => true]);
    $salesUom = Uom::factory()->create(['uom_name' => 'Carton', 'enabled' => true]);
    $warehouse = Warehouse::factory()->create(['disabled' => false]);
    $product = Product::factory()->create([
        'uom_id' => $uom->id,
        'sales_uom_id' => $salesUom->id,
        'uom_conversion_factor' => 27.000,
        'is_active' => true,
    ]);

    $response = $this->getJson("/api/warehouses/{$warehouse->id}/products/{$product->id}/stock");

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'available_quantity',
        'conversion_factor',
        'sales_uom_id',
        'sales_uom_name',
    ]);
    $response->assertJsonFragment([
        'conversion_factor' => 27.0,
        'sales_uom_name' => 'Carton',
    ]);
});

it('returns uom_conversion_factor in products by suppliers API response', function () {
    $this->user->givePermissionTo('goods-issue-create');

    $supplier = Supplier::factory()->create(['disabled' => false]);
    $uom = Uom::factory()->create(['uom_name' => 'Piece', 'enabled' => true]);
    $salesUom = Uom::factory()->create(['uom_name' => 'Case', 'enabled' => true]);

    Product::factory()->create([
        'supplier_id' => $supplier->id,
        'uom_id' => $uom->id,
        'sales_uom_id' => $salesUom->id,
        'uom_conversion_factor' => 12.000,
        'is_active' => true,
    ]);

    $response = $this->getJson("/api/products/by-suppliers?supplier_ids[]={$supplier->id}");

    $response->assertSuccessful();
    $response->assertJsonFragment([
        'uom_conversion_factor' => '12.000',
        'sales_uom_id' => $salesUom->id,
    ]);
});

it('sets canEnterCartons as JS variable when permission is granted', function () {
    $this->user->givePermissionTo(['goods-issue-create', 'goods-issue-carton-entry']);

    $response = $this->get(route('goods-issues.create'));

    $response->assertSuccessful();
    $response->assertSee('const canEnterCartons = true');
});

it('sets canEnterCartons as JS variable false when permission is not granted', function () {
    $this->user->givePermissionTo('goods-issue-create');

    $response = $this->get(route('goods-issues.create'));

    $response->assertSuccessful();
    $response->assertSee('const canEnterCartons = false');
});
