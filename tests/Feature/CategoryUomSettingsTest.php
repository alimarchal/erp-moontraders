<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Uom;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $permissions = [];
    foreach (['category', 'uom'] as $module) {
        foreach (['list', 'create', 'edit', 'delete'] as $action) {
            $permissions[] = Permission::findOrCreate("{$module}-{$action}")->name;
        }
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo($permissions);
    $this->actingAs($this->user);
});

it('renders every category page', function () {
    $category = Category::create(['name' => 'Cooking Oil', 'slug' => 'cooking-oil']);
    Product::factory()->create(['category_id' => $category->id]);

    $this->get(route('categories.index'))->assertSuccessful()->assertSee('Cooking Oil');
    $this->get(route('categories.create'))->assertSuccessful()->assertSee('Category details');
    $this->get(route('categories.edit', $category))->assertSuccessful()->assertSee('Edit Category: Cooking Oil');
    $this->get(route('categories.show', $category))->assertSuccessful()->assertSee('Products in this category');
});

it('deactivates a category instead of deleting it, even when it has products', function () {
    $category = Category::create(['name' => 'Ghee']);
    Product::factory()->create(['category_id' => $category->id]);

    $this->delete(route('categories.destroy', $category))->assertRedirect(route('categories.index'));

    $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_active' => false]);
});

it('can switch a category off from the edit form', function () {
    $category = Category::create(['name' => 'Soap']);

    $this->put(route('categories.update', $category), ['name' => 'Soap', 'is_active' => '0'])
        ->assertRedirect(route('categories.index'));

    expect($category->fresh()->is_active)->toBeFalse();
});

it('searches categories and filters by status', function () {
    Category::create(['name' => 'Zeta Foods']);
    Category::create(['name' => 'Alpha Foods', 'is_active' => false]);

    $this->get(route('categories.index', ['filter' => ['search' => 'Zeta']]))
        ->assertSee('Zeta Foods')->assertDontSee('Alpha Foods');
    $this->get(route('categories.index', ['filter' => ['is_active' => '0']]))
        ->assertSee('Alpha Foods')->assertDontSee('Zeta Foods');
});

it('renders every unit page', function () {
    $uom = Uom::factory()->create(['uom_name' => 'Carton Box', 'symbol' => 'CTN']);

    $this->get(route('uoms.index'))->assertSuccessful()->assertSee('Carton Box');
    $this->get(route('uoms.create'))->assertSuccessful()->assertSee('Unit details');
    $this->get(route('uoms.edit', $uom))->assertSuccessful()->assertSee('Edit Unit: Carton Box');
    $this->get(route('uoms.show', $uom))->assertSuccessful()->assertSee('Used as base unit by');
});

it('disables a unit instead of deleting it', function () {
    $uom = Uom::factory()->create(['enabled' => true]);

    $this->delete(route('uoms.destroy', $uom))->assertRedirect(route('uoms.index'));

    $this->assertDatabaseHas('uoms', ['id' => $uom->id, 'enabled' => false, 'deleted_at' => null]);
});

it('creates and updates a unit with the new switches', function () {
    $this->post(route('uoms.store'), [
        'uom_name' => 'Dozen', 'symbol' => 'DZ', 'must_be_whole_number' => '1', 'enabled' => '1',
    ])->assertRedirect(route('uoms.index'));

    $uom = Uom::where('uom_name', 'Dozen')->firstOrFail();
    expect($uom->must_be_whole_number)->toBeTrue();

    $this->put(route('uoms.update', $uom), [
        'uom_name' => 'Dozen', 'symbol' => 'DZ', 'must_be_whole_number' => '0', 'enabled' => '0',
    ])->assertRedirect(route('uoms.index'));

    expect($uom->fresh())->must_be_whole_number->toBeFalse()->enabled->toBeFalse();
});
