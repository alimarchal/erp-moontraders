<?php

use App\Models\Product;
use App\Models\ProductTaxMapping;
use App\Models\TaxCode;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['tax-list', 'tax-manage-mapping'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without tax-list permission', function () {
    $this->get(route('product-tax-mappings.index'))->assertForbidden();
});

it('allows index with tax-list permission', function () {
    $this->user->givePermissionTo('tax-list');
    $this->get(route('product-tax-mappings.index'))->assertSuccessful();
});

it('denies create without tax-manage-mapping permission', function () {
    $this->get(route('product-tax-mappings.create'))->assertForbidden();
});

it('allows create with tax-manage-mapping permission', function () {
    $this->user->givePermissionTo('tax-manage-mapping');
    $this->get(route('product-tax-mappings.create'))->assertSuccessful();
});

it('denies store without tax-manage-mapping permission', function () {
    $this->post(route('product-tax-mappings.store'), [])->assertForbidden();
});

it('denies show without tax-list permission', function () {
    $mapping = ProductTaxMapping::factory()->create([
        'product_id' => Product::factory()->create()->id,
        'tax_code_id' => TaxCode::factory()->create()->id,
    ]);
    $this->get(route('product-tax-mappings.show', $mapping))->assertForbidden();
});

it('denies edit without tax-manage-mapping permission', function () {
    $mapping = ProductTaxMapping::factory()->create([
        'product_id' => Product::factory()->create()->id,
        'tax_code_id' => TaxCode::factory()->create()->id,
    ]);
    $this->get(route('product-tax-mappings.edit', $mapping))->assertForbidden();
});

it('denies update without tax-manage-mapping permission', function () {
    $mapping = ProductTaxMapping::factory()->create([
        'product_id' => Product::factory()->create()->id,
        'tax_code_id' => TaxCode::factory()->create()->id,
    ]);
    $this->put(route('product-tax-mappings.update', $mapping), [])->assertForbidden();
});

it('denies destroy without tax-manage-mapping permission', function () {
    $mapping = ProductTaxMapping::factory()->create([
        'product_id' => Product::factory()->create()->id,
        'tax_code_id' => TaxCode::factory()->create()->id,
    ]);
    $this->delete(route('product-tax-mappings.destroy', $mapping))->assertForbidden();
});
