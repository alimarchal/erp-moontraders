<?php

use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['product-list', 'product-create', 'product-edit', 'product-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without product-list permission', function () {
    $this->get(route('products.index'))->assertForbidden();
});

it('allows index with product-list permission', function () {
    $this->user->givePermissionTo('product-list');
    $this->get(route('products.index'))->assertSuccessful();
});

it('denies create without product-create permission', function () {
    $this->get(route('products.create'))->assertForbidden();
});

it('allows create with product-create permission', function () {
    $this->user->givePermissionTo('product-create');
    $this->get(route('products.create'))->assertSuccessful();
});

it('denies store without product-create permission', function () {
    $this->post(route('products.store'), [])->assertForbidden();
});

it('denies show without product-list permission', function () {
    $product = Product::factory()->create();
    $this->get(route('products.show', $product))->assertForbidden();
});

it('denies edit without product-edit permission', function () {
    $product = Product::factory()->create();
    $this->get(route('products.edit', $product))->assertForbidden();
});

it('denies update without product-edit permission', function () {
    $product = Product::factory()->create();
    $this->put(route('products.update', $product), [])->assertForbidden();
});

it('denies destroy without product-delete permission', function () {
    $product = Product::factory()->create();
    $this->delete(route('products.destroy', $product))->assertForbidden();
});
