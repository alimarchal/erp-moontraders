<?php

use App\Models\RevenueCategory;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['category-revenue-list', 'category-revenue-create', 'category-revenue-edit', 'category-revenue-delete'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['category-revenue-list', 'category-revenue-create', 'category-revenue-edit', 'category-revenue-delete']);
    $this->actingAs($this->user);

});

it('loads category revenue index and filters by supplier', function () {
    $supplierA = Supplier::factory()->create(['supplier_name' => 'Supplier A', 'disabled' => false]);
    $supplierB = Supplier::factory()->create(['supplier_name' => 'Supplier B', 'disabled' => false]);

    RevenueCategory::factory()->create([
        'supplier_id' => $supplierA->id,
        'name' => 'Supplier A Revenue',
        'slug' => 'supplier-a-revenue',
    ]);
    RevenueCategory::factory()->create([
        'supplier_id' => $supplierB->id,
        'name' => 'Supplier B Revenue',
        'slug' => 'supplier-b-revenue',
    ]);

    $this->get(route('category-revenue.index', ['supplier_id' => $supplierA->id]))
        ->assertSuccessful()
        ->assertSee('Supplier A Revenue')
        ->assertDontSee('Supplier B Revenue');
});

it('creates updates and deletes a revenue category', function () {
    $supplier = Supplier::factory()->create(['disabled' => false]);

    $this->post(route('category-revenue.store'), [
        'supplier_id' => $supplier->id,
        'name' => 'Display Income',
        'is_active' => '1',
    ])->assertRedirect(route('category-revenue.index'));

    $category = RevenueCategory::firstWhere('name', 'Display Income');

    expect($category)->not->toBeNull()
        ->and($category->slug)->toBe('display-income');

    $this->put(route('category-revenue.update', $category), [
        'supplier_id' => $supplier->id,
        'name' => 'Updated Display Income',
        'is_active' => '1',
    ])->assertRedirect(route('category-revenue.index'));

    expect($category->fresh()->name)->toBe('Updated Display Income');

    $this->delete(route('category-revenue.destroy', $category))
        ->assertRedirect(route('category-revenue.index'));

    $this->assertSoftDeleted('revenue_categories', ['id' => $category->id]);
});

it('blocks category revenue index without permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('category-revenue.index'))
        ->assertForbidden();
});
