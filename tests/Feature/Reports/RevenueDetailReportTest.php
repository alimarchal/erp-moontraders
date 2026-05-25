<?php

use App\Models\RevenueCategory;
use App\Models\RevenueDetail;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'report-audit-revenue-detail',
        'revenue-detail-create',
        'revenue-detail-edit',
        'revenue-detail-delete',
        'revenue-detail-post',
    ] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'report-audit-revenue-detail',
        'revenue-detail-create',
        'revenue-detail-edit',
        'revenue-detail-delete',
        'revenue-detail-post',
    ]);
    $this->actingAs($this->user);

});

it('loads revenue detail report and scopes category options by supplier', function () {
    [$supplierA, $categoryA] = createRevenueCategoryFixture('Supplier A', 'Display Revenue');
    [, $categoryB] = createRevenueCategoryFixture('Supplier B', 'Hidden Revenue');

    RevenueDetail::factory()->create([
        'supplier_id' => $supplierA->id,
        'revenue_category_id' => $categoryA->id,
        'transaction_date' => '2026-05-10',
        'amount' => 1500,
    ]);

    $this->get(route('reports.revenue-detail.index', [
        'supplier_id' => $supplierA->id,
        'date_from' => '2026-05-01',
        'date_to' => '2026-05-31',
    ]))
        ->assertSuccessful()
        ->assertSee('Revenue Detail')
        ->assertSee('Display Revenue')
        ->assertDontSee('Hidden Revenue');

    expect($categoryB->supplier_id)->not->toBe($supplierA->id);
});

it('blocks revenue detail report without permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('reports.revenue-detail.index'))
        ->assertForbidden();
});

it('creates updates and deletes revenue entries', function () {
    [$supplier, $category] = createRevenueCategoryFixture();

    $this->post(route('reports.revenue-detail.store'), [
        'supplier_id' => $supplier->id,
        'revenue_category_id' => $category->id,
        'transaction_date' => '2026-05-10',
        'description' => 'Display income',
        'amount' => 1200,
    ])->assertRedirect();

    $revenue = RevenueDetail::firstWhere('description', 'Display income');

    expect($revenue)->not->toBeNull()
        ->and((float) $revenue->amount)->toBe(1200.0);

    $this->put(route('reports.revenue-detail.update', $revenue), [
        'supplier_id' => $supplier->id,
        'revenue_category_id' => $category->id,
        'transaction_date' => '2026-05-11',
        'description' => 'Updated display income',
        'amount' => 1500,
    ])->assertRedirect();

    expect($revenue->fresh()->description)->toBe('Updated display income')
        ->and((float) $revenue->fresh()->amount)->toBe(1500.0);

    $this->delete(route('reports.revenue-detail.destroy', $revenue))
        ->assertRedirect();

    $this->assertSoftDeleted('revenue_details', ['id' => $revenue->id]);
});

it('posts revenue by marking timestamp and user only', function () {
    [$supplier, $category] = createRevenueCategoryFixture();

    $revenue = RevenueDetail::factory()->create([
        'supplier_id' => $supplier->id,
        'revenue_category_id' => $category->id,
        'transaction_date' => '2026-05-10',
        'description' => 'Supplier display revenue',
        'amount' => 1800,
    ]);

    $this->post(route('reports.revenue-detail.post', $revenue))
        ->assertRedirect();

    $revenue->refresh();

    expect($revenue->posted_at)->not->toBeNull()
        ->and($revenue->posted_by)->toBe($this->user->id);
});

it('does not edit or delete posted revenue entries', function () {
    [$supplier, $category] = createRevenueCategoryFixture();

    $revenue = RevenueDetail::factory()->create([
        'supplier_id' => $supplier->id,
        'revenue_category_id' => $category->id,
        'transaction_date' => '2026-05-10',
        'description' => 'Locked revenue',
        'amount' => 500,
        'posted_at' => now(),
    ]);

    $this->put(route('reports.revenue-detail.update', $revenue), [
        'supplier_id' => $supplier->id,
        'revenue_category_id' => $category->id,
        'transaction_date' => '2026-05-11',
        'description' => 'Changed revenue',
        'amount' => 700,
    ])->assertRedirect();

    expect($revenue->fresh()->description)->toBe('Locked revenue');

    $this->delete(route('reports.revenue-detail.destroy', $revenue))
        ->assertRedirect();

    expect(RevenueDetail::find($revenue->id))->not->toBeNull();
});

function createRevenueCategoryFixture(string $supplierName = 'Nestle', string $categoryName = 'Display Revenue'): array
{
    $supplier = Supplier::factory()->create([
        'supplier_name' => $supplierName,
        'short_name' => $supplierName === 'Nestle' ? 'Nestle' : $supplierName,
        'disabled' => false,
    ]);

    $category = RevenueCategory::factory()->create([
        'supplier_id' => $supplier->id,
        'name' => $categoryName,
        'slug' => str($categoryName)->slug()->toString(),
        'is_active' => true,
    ]);

    return [$supplier, $category];
}
