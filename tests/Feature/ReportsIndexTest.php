<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

function reportsUserWith(array $permissions): User
{
    foreach (array_merge(['view-any-report'], $permissions) as $permission) {
        Permission::findOrCreate($permission);
    }

    $user = User::factory()->create();
    $user->givePermissionTo(array_merge(['view-any-report'], $permissions));

    return $user;
}

it('requires the view-any-report permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('reports.index'))
        ->assertForbidden();
});

it('renders the search box and groups reports by area, smaller groups first', function () {
    $user = reportsUserWith(['report-financial-general-ledger', 'report-audit-supplier-ledger', 'report-inventory-inventory-ledger']);

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertSuccessful()
        ->assertSee('id="reports-search"', false)
        ->assertSeeInOrder(['Financial Statements', 'General Ledger', 'Supplier & Registers', 'Supplier Ledger', 'Inventory & Stock', 'Inventory Ledger'])
        ->assertSee(route('reports.general-ledger.index'), false)
        ->assertSee('data-settings-item', false);
});

it('only shows reports the user is permitted to see', function () {
    $user = reportsUserWith(['report-financial-general-ledger']);

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertSuccessful()
        ->assertSee(route('reports.general-ledger.index'), false)
        ->assertDontSee(route('reports.trial-balance.index'), false)
        ->assertDontSee('Inventory &amp; Stock', false);
});

it('lists each report link only once', function () {
    $permissions = collect(glob(resource_path('views/reports/index.blade.php')))
        ->flatMap(fn (string $file) => preg_match_all("/@can\('([^']+)'\)/", file_get_contents($file), $matches) ? $matches[1] : [])
        ->unique()
        ->all();

    $html = $this->actingAs(reportsUserWith($permissions))
        ->get(route('reports.index'))
        ->assertSuccessful()
        ->getContent();

    preg_match_all('#<a\s[^>]*data-settings-item[^>]*>#', $html, $anchors);
    $hrefs = collect($anchors[0])
        ->map(fn (string $anchor) => preg_match('#href="([^"]+)"#', $anchor, $match) ? $match[1] : null)
        ->all();

    expect($hrefs)->not->toBeEmpty()
        ->and($hrefs)->toHaveCount(count(array_unique($hrefs)));
});

it('keeps the Reports menu item highlighted on a report page', function () {
    $user = reportsUserWith(['report-financial-general-ledger']);

    $html = $this->actingAs($user)
        ->get(route('reports.index'))
        ->getContent();

    preg_match_all('#<a\s[^>]*>#', $html, $anchors);
    $active = collect($anchors[0])->contains(
        fn (string $anchor) => str_contains($anchor, 'href="'.route('reports.index').'"') && str_contains($anchor, 'border-indigo-400')
    );

    expect($active)->toBeTrue();
});
