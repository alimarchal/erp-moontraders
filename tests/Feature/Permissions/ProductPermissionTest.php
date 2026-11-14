<?php

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
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

it('allows edit with product-edit permission and keeps cost price editable', function () {
    $product = Product::factory()->create();
    $this->user->givePermissionTo('product-edit');

    $response = $this->get(route('products.edit', $product))->assertSuccessful();
    $content = (string) $response->getContent();

    preg_match('/<input[^>]*id="cost_price"[^>]*>/i', $content, $matches);

    expect($matches)->not->toBeEmpty();
    expect($matches[0])->not->toContain('disabled');
    expect($matches[0])->not->toContain('readonly');
});

it('denies update without product-edit permission', function () {
    $product = Product::factory()->create();
    $this->put(route('products.update', $product), [])->assertForbidden();
});

it('denies destroy without product-delete permission', function () {
    $product = Product::factory()->create();
    $this->delete(route('products.destroy', $product))->assertForbidden();
});

it('denies a supplier-scoped user another supplier\'s product show, edit, update and deactivate', function () {
    $ownSupplier = Supplier::factory()->create();
    $otherProduct = Product::factory()->create(['supplier_id' => Supplier::factory()]);

    $this->user->update(['supplier_id' => $ownSupplier->id]);
    $this->user->givePermissionTo(['product-list', 'product-edit', 'product-delete']);

    $this->get(route('products.show', $otherProduct))->assertForbidden();
    $this->get(route('products.edit', $otherProduct))->assertForbidden();
    $this->put(route('products.update', $otherProduct), [
        'product_code' => $otherProduct->product_code,
        'product_name' => 'Changed',
        'uom_id' => $otherProduct->uom_id ?? Uom::factory()->create()->id,
        'valuation_method' => Product::VALUATION_METHODS[0],
    ])->assertForbidden();
    $this->delete(route('products.destroy', $otherProduct))->assertForbidden();

    expect($otherProduct->fresh()->is_active)->toBeTruthy();
});

it('lets a supplier-scoped user open their own supplier\'s product', function () {
    $ownSupplier = Supplier::factory()->create();
    $ownProduct = Product::factory()->create(['supplier_id' => $ownSupplier->id]);

    $this->user->update(['supplier_id' => $ownSupplier->id]);
    $this->user->givePermissionTo(['product-list', 'product-edit']);

    $this->get(route('products.show', $ownProduct))->assertSuccessful();
    $this->get(route('products.edit', $ownProduct))->assertSuccessful();
});

it('denies a supplier-scoped user saving a product under another supplier', function () {
    $ownSupplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();

    $this->user->update(['supplier_id' => $ownSupplier->id]);
    $this->user->givePermissionTo('product-create');

    $this->post(route('products.store'), [
        'product_code' => 'SMUGGLED-1',
        'product_name' => 'Smuggled',
        'uom_id' => Uom::factory()->create()->id,
        'valuation_method' => Product::VALUATION_METHODS[0],
        'supplier_id' => $otherSupplier->id,
    ])->assertForbidden();

    expect(Product::where('product_name', 'Smuggled')->exists())->toBeFalse();
});

it('refuses an invalid update of another supplier\'s product before validating it', function () {
    $ownSupplier = Supplier::factory()->create();
    $otherProduct = Product::factory()->create(['supplier_id' => Supplier::factory()]);

    $this->user->update(['supplier_id' => $ownSupplier->id]);
    $this->user->givePermissionTo('product-edit');

    $this->putJson(route('products.update', $otherProduct), [])->assertForbidden();
});

it('denies a supplier-scoped user moving their own product to another supplier', function () {
    $ownSupplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $ownProduct = Product::factory()->create(['supplier_id' => $ownSupplier->id]);

    $this->user->update(['supplier_id' => $ownSupplier->id]);
    $this->user->givePermissionTo('product-edit');

    $this->put(route('products.update', $ownProduct), [
        'product_code' => $ownProduct->product_code,
        'product_name' => $ownProduct->product_name,
        'uom_id' => $ownProduct->uom_id ?? Uom::factory()->create()->id,
        'valuation_method' => Product::VALUATION_METHODS[0],
        'supplier_id' => $otherSupplier->id,
    ])->assertForbidden();

    expect($ownProduct->fresh()->supplier_id)->toBe($ownSupplier->id);
});
