<?php

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('report-sales-sku-rates');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-sales-sku-rates');
});

test('sku rates report shows expiry price column', function () {
    $this->actingAs($this->user)
        ->get(route('reports.sku-rates.index'))
        ->assertSuccessful()
        ->assertViewIs('reports.sku-rates.index')
        ->assertSee('Expiry Price');
});

test('sku rates report filters products by expiry price', function () {
    $supplier = Supplier::factory()->create();
    $uom = Uom::factory()->create(['enabled' => true]);

    Product::factory()->create([
        'product_code' => 'PROD-EXP-1001',
        'supplier_id' => $supplier->id,
        'uom_id' => $uom->id,
        'expiry_price' => 450,
    ]);

    Product::factory()->create([
        'product_code' => 'PROD-EXP-2002',
        'supplier_id' => $supplier->id,
        'uom_id' => $uom->id,
        'expiry_price' => 900,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.sku-rates.index', [
            'filter' => ['expiry_price' => '900'],
        ]));

    $response->assertSuccessful();
    $response->assertSee('PROD-EXP-2002');
    $response->assertDontSee('PROD-EXP-1001');
});

test('sku code links to product edit page', function () {
    $supplier = Supplier::factory()->create();
    $uom = Uom::factory()->create(['enabled' => true]);

    $product = Product::factory()->create([
        'product_code' => 'PROD-LINK-3003',
        'supplier_id' => $supplier->id,
        'uom_id' => $uom->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.sku-rates.index'));

    $response->assertSuccessful();
    $response->assertSee(route('products.edit', $product), false);
    $response->assertSee('PROD-LINK-3003');
});
