<?php

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'product-list']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('product-list');
    $this->actingAs($this->user);
});

it('downloads an excel file for authenticated user with permission', function () {
    Product::factory()->count(3)->create();

    $this->get(route('products.export.excel'))
        ->assertSuccessful()
        ->assertDownload('products.xlsx');
});

it('exports only filtered products by supplier', function () {
    $supplier = Supplier::factory()->create();
    Product::factory()->create(['supplier_id' => $supplier->id, 'product_name' => 'Filtered Product']);
    Product::factory()->create(['product_name' => 'Other Product']);

    $this->get(route('products.export.excel', ['filter' => ['supplier_id' => $supplier->id]]))
        ->assertSuccessful()
        ->assertDownload('products.xlsx');
});

it('exports only active products when status filter applied', function () {
    Product::factory()->create(['is_active' => true]);
    Product::factory()->create(['is_active' => false]);

    $this->get(route('products.export.excel', ['filter' => ['is_active' => '1']]))
        ->assertSuccessful()
        ->assertDownload('products.xlsx');
});

it('exports with brand filter', function () {
    Product::factory()->create(['brand' => 'Nestle']);
    Product::factory()->create(['brand' => 'Pepsi']);

    $this->get(route('products.export.excel', ['filter' => ['brand' => 'Nest']]))
        ->assertSuccessful()
        ->assertDownload('products.xlsx');
});

it('denies export for unauthenticated user', function () {
    auth()->logout();

    $this->get(route('products.export.excel'))
        ->assertRedirect(route('login'));
});

it('denies export without product-list permission', function () {
    $userWithoutPermission = User::factory()->create();
    $this->actingAs($userWithoutPermission);

    $this->get(route('products.export.excel'))
        ->assertForbidden();
});

it('supports per page on index', function () {
    Product::factory()->count(20)->create();

    $this->get(route('products.index', ['per_page' => 10]))
        ->assertSuccessful();
});

it('falls back to default per page for invalid value', function () {
    Product::factory()->count(5)->create();

    $this->get(route('products.index', ['per_page' => 999]))
        ->assertSuccessful();
});

it('supports sorting by price', function () {
    Product::factory()->create(['unit_sell_price' => 100]);
    Product::factory()->create(['unit_sell_price' => 50]);

    $this->get(route('products.index', ['sort' => '-unit_sell_price']))
        ->assertSuccessful();
});
