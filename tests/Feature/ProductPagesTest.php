<?php

use App\Models\Product;
use App\Models\Uom;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['product-list', 'product-create', 'product-edit'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['product-list', 'product-create', 'product-edit']);
    $this->actingAs($this->user);
});

it('shows the products help page and links to it from index, create and edit', function () {
    $product = Product::factory()->create();

    $this->get(route('products.help'))->assertSuccessful()->assertSee('How products work')->assertSee('Pieces per carton');

    foreach ([route('products.index'), route('products.create'), route('products.edit', $product)] as $url) {
        $this->get($url)->assertSuccessful()->assertSee(route('products.help'), false);
    }
});

it('shows the carton entry fields on create and edit with the saved values', function () {
    $product = Product::factory()->create(['pieces_per_carton' => 120, 'units_per_piece' => 15]);

    $this->get(route('products.create'))->assertSuccessful()->assertSee('Carton entry')->assertSee('name="pieces_per_carton"', false);
    $this->get(route('products.edit', $product))->assertSuccessful()->assertSee('value="120"', false)->assertSee('value="15"', false);
});

it('hides the products help page from users without the list permission', function () {
    $this->actingAs(User::factory()->create())->get(route('products.help'))->assertForbidden();
});

it('keeps the saved dropdown values selected on edit and never re-initialises the Select2 boxes', function () {
    $uom = Uom::factory()->create();
    $product = Product::factory()->create(['uom_id' => $uom->id]);

    $html = $this->get(route('products.edit', $product))->assertSuccessful()->getContent();

    expect($html)->toContain('<option value="'.$uom->id.'" selected')
        ->and($html)->not->toContain("\$('.select2').select2(");

    $this->get(route('products.create'))->assertSuccessful()->assertDontSee("\$('.select2').select2(", false);
});
