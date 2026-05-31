<?php

use App\Models\ProfitCategory;
use App\Models\ProfitCategoryDetail;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'report-audit-profit-after-category',
        'profit-after-category-create',
        'profit-after-category-edit',
        'profit-after-category-delete',
        'profit-after-category-post',
    ] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'report-audit-profit-after-category',
        'profit-after-category-create',
        'profit-after-category-edit',
        'profit-after-category-delete',
        'profit-after-category-post',
    ]);
    $this->actingAs($this->user);
});

it('loads profit after category report and scopes category options by supplier', function () {
    [$supplierA, $categoryA] = createProfitAfterCategoryFixture('Supplier A', 'Taxation');
    [, $categoryB] = createProfitAfterCategoryFixture('Supplier B', 'Hidden Taxation');

    ProfitCategoryDetail::factory()->create([
        'supplier_id' => $supplierA->id,
        'profit_category_id' => $categoryA->id,
        'transaction_date' => '2026-05-10',
        'amount' => 1500,
    ]);

    $this->get(route('reports.profit-after-category.index', [
        'supplier_id' => $supplierA->id,
        'date_from' => '2026-05-01',
        'date_to' => '2026-05-31',
    ]))
        ->assertSuccessful()
        ->assertSee('Profit After Category')
        ->assertSee('Taxation')
        ->assertDontSee('Hidden Taxation');

    expect($categoryB->supplier_id)->not->toBe($supplierA->id);
});

it('blocks profit after category report without permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('reports.profit-after-category.index'))
        ->assertForbidden();
});

it('creates updates and deletes profit category entries', function () {
    [$supplier, $category] = createProfitAfterCategoryFixture();

    $this->post(route('reports.profit-after-category.store'), [
        'supplier_id' => $supplier->id,
        'profit_category_id' => $category->id,
        'transaction_date' => '2026-05-10',
        'description' => 'Tax payment',
        'amount' => 1200,
    ])->assertRedirect();

    $profitCategoryDetail = ProfitCategoryDetail::firstWhere('description', 'Tax payment');

    expect($profitCategoryDetail)->not->toBeNull()
        ->and((float) $profitCategoryDetail->amount)->toBe(1200.0);

    $this->put(route('reports.profit-after-category.update', $profitCategoryDetail), [
        'supplier_id' => $supplier->id,
        'profit_category_id' => $category->id,
        'transaction_date' => '2026-05-11',
        'description' => 'Updated tax payment',
        'amount' => 1500,
    ])->assertRedirect();

    expect($profitCategoryDetail->fresh()->description)->toBe('Updated tax payment')
        ->and((float) $profitCategoryDetail->fresh()->amount)->toBe(1500.0);

    $this->delete(route('reports.profit-after-category.destroy', $profitCategoryDetail))
        ->assertRedirect();

    $this->assertSoftDeleted('profit_category_details', ['id' => $profitCategoryDetail->id]);
});

it('posts profit category entry by marking timestamp and user only', function () {
    [$supplier, $category] = createProfitAfterCategoryFixture();

    $profitCategoryDetail = ProfitCategoryDetail::factory()->create([
        'supplier_id' => $supplier->id,
        'profit_category_id' => $category->id,
        'transaction_date' => '2026-05-10',
        'description' => 'Supplier tax',
        'amount' => 1800,
    ]);

    $this->post(route('reports.profit-after-category.post', $profitCategoryDetail))
        ->assertRedirect();

    $profitCategoryDetail->refresh();

    expect($profitCategoryDetail->posted_at)->not->toBeNull()
        ->and($profitCategoryDetail->posted_by)->toBe($this->user->id);
});

it('does not edit or delete posted profit category entries', function () {
    [$supplier, $category] = createProfitAfterCategoryFixture();

    $profitCategoryDetail = ProfitCategoryDetail::factory()->create([
        'supplier_id' => $supplier->id,
        'profit_category_id' => $category->id,
        'transaction_date' => '2026-05-10',
        'description' => 'Locked tax',
        'amount' => 500,
        'posted_at' => now(),
    ]);

    $this->put(route('reports.profit-after-category.update', $profitCategoryDetail), [
        'supplier_id' => $supplier->id,
        'profit_category_id' => $category->id,
        'transaction_date' => '2026-05-11',
        'description' => 'Changed tax',
        'amount' => 700,
    ])->assertRedirect();

    expect($profitCategoryDetail->fresh()->description)->toBe('Locked tax');

    $this->delete(route('reports.profit-after-category.destroy', $profitCategoryDetail))
        ->assertRedirect();

    expect(ProfitCategoryDetail::find($profitCategoryDetail->id))->not->toBeNull();
});

function createProfitAfterCategoryFixture(string $supplierName = 'Nestle', string $categoryName = 'Taxation'): array
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
