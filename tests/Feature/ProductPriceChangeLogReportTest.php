<?php

use App\Models\Product;
use App\Models\ProductPriceChangeLog;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'report-audit-product-price-change-log', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-audit-product-price-change-log');
    $this->actingAs($this->user);
});

test('product price change log is scoped to the authenticated users supplier', function () {
    $ownSupplier = Supplier::factory()->create(['supplier_name' => 'Kausar Oil']);
    $otherSupplier = Supplier::factory()->create(['supplier_name' => 'Nestle Pakistan']);
    $ownProduct = Product::factory()->create([
        'supplier_id' => $ownSupplier->id,
        'product_name' => 'Own Product',
    ]);
    $otherProduct = Product::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'product_name' => 'Other Product',
    ]);

    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    ProductPriceChangeLog::create([
        'product_id' => $ownProduct->id,
        'changed_by' => $this->user->id,
        'price_type' => 'selling_price',
        'old_price' => 100,
        'new_price' => 110,
        'impacted_batch_count' => 0,
        'changed_at' => now(),
    ]);
    ProductPriceChangeLog::create([
        'product_id' => $otherProduct->id,
        'changed_by' => $this->user->id,
        'price_type' => 'selling_price',
        'old_price' => 200,
        'new_price' => 210,
        'impacted_batch_count' => 0,
        'changed_at' => now(),
    ]);

    $response = $this->get(route('reports.product-price-change-log.index'));

    $response->assertSuccessful();
    $response->assertSee('Own Product');
    $response->assertDontSee('Other Product');
    expect($response->viewData('supplierId'))->toBe($ownSupplier->id);
    expect($response->viewData('supplierOptions'))->toHaveCount(1);
    expect($response->viewData('supplierOptions')->first()->id)->toBe($ownSupplier->id);
    expect($response->viewData('productOptions'))->toHaveCount(1);
    expect($response->viewData('productOptions')->first()->id)->toBe($ownProduct->id);
});

test('product price change log blocks filtering by another supplier for scoped users', function () {
    $ownSupplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();

    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    $this->get(route('reports.product-price-change-log.index', [
        'supplier_id' => $otherSupplier->id,
    ]))->assertForbidden();
});

test('product price change log blocks filtering by another suppliers product', function () {
    $ownSupplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $otherProduct = Product::factory()->create(['supplier_id' => $otherSupplier->id]);

    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    $this->get(route('reports.product-price-change-log.index', [
        'product_id' => $otherProduct->id,
    ]))->assertForbidden();
});
