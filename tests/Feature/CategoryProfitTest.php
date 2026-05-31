<?php

use App\Models\ProfitCategory;
use App\Models\ProfitCategoryDetail;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['profit-category-list', 'profit-category-create', 'profit-category-edit', 'profit-category-delete'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['profit-category-list', 'profit-category-create', 'profit-category-edit', 'profit-category-delete']);
    $this->actingAs($this->user);
});

it('loads profit category index and filters by supplier', function () {
    $supplierA = Supplier::factory()->create(['supplier_name' => 'Supplier A', 'disabled' => false]);
    $supplierB = Supplier::factory()->create(['supplier_name' => 'Supplier B', 'disabled' => false]);

    ProfitCategory::factory()->create([
        'supplier_id' => $supplierA->id,
        'name' => 'Supplier A Profit',
        'slug' => 'supplier-a-profit',
    ]);
    ProfitCategory::factory()->create([
        'supplier_id' => $supplierB->id,
        'name' => 'Supplier B Profit',
        'slug' => 'supplier-b-profit',
    ]);

    $this->get(route('profit-categories.index', ['supplier_id' => $supplierA->id]))
        ->assertSuccessful()
        ->assertSee('Supplier A Profit')
        ->assertDontSee('Supplier B Profit');
});

it('creates updates and deletes a profit category', function () {
    $supplier = Supplier::factory()->create(['disabled' => false]);

    $this->post(route('profit-categories.store'), [
        'supplier_id' => $supplier->id,
        'name' => 'Taxation',
        'is_active' => '1',
    ])->assertRedirect(route('profit-categories.index'));

    $category = ProfitCategory::firstWhere('name', 'Taxation');

    expect($category)->not->toBeNull()
        ->and($category->slug)->toBe('taxation');

    $this->put(route('profit-categories.update', $category), [
        'supplier_id' => $supplier->id,
        'name' => 'Updated Taxation',
        'is_active' => '1',
    ])->assertRedirect(route('profit-categories.index'));

    expect($category->fresh()->name)->toBe('Updated Taxation');

    $this->delete(route('profit-categories.destroy', $category))
        ->assertRedirect(route('profit-categories.index'));

    $this->assertSoftDeleted('profit_categories', ['id' => $category->id]);
});

it('does not delete a profit category with entries', function () {
    [$supplier, $category] = createProfitCategoryFixture();

    ProfitCategoryDetail::factory()->create([
        'supplier_id' => $supplier->id,
        'profit_category_id' => $category->id,
    ]);

    $this->delete(route('profit-categories.destroy', $category))
        ->assertRedirect();

    expect(ProfitCategory::find($category->id))->not->toBeNull();
});

it('blocks profit category index without permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profit-categories.index'))
        ->assertForbidden();
});

function createProfitCategoryFixture(string $supplierName = 'Nestle', string $categoryName = 'Taxation'): array
{
    $supplier = Supplier::factory()->create([
        'supplier_name' => $supplierName,
        'short_name' => $supplierName === 'Nestle' ? 'Nestle' : $supplierName,
        'disabled' => false,
    ]);

    $category = ProfitCategory::factory()->create([
        'supplier_id' => $supplier->id,
        'name' => $categoryName,
        'slug' => str($categoryName)->slug()->toString(),
        'is_active' => true,
    ]);

    return [$supplier, $category];
}
