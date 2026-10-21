<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

function settingsUserWith(array $permissions): User
{
    foreach (array_merge(['setting-view'], $permissions) as $permission) {
        Permission::findOrCreate($permission);
    }

    $user = User::factory()->create();
    $user->givePermissionTo(array_merge(['setting-view'], $permissions));

    return $user;
}

it('requires the setting-view permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('settings.index'))
        ->assertForbidden();
});

it('renders the search box and groups links by area, smaller groups first', function () {
    $user = settingsUserWith(['user-list', 'chart-of-account-list', 'supplier-list']);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertSuccessful()
        ->assertSee('id="settings-search"', false)
        ->assertSeeInOrder(['Organization & Access', 'Users', 'Business Partners', 'Suppliers', 'Accounting Setup', 'Chart of Accounts'])
        ->assertSee(route('users.index'), false)
        ->assertSee('data-settings-item', false);
});

it('only shows links the user is permitted to see', function () {
    $user = settingsUserWith(['user-list']);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertSuccessful()
        ->assertSee(route('users.index'), false)
        ->assertDontSee(route('chart-of-accounts.index'), false)
        ->assertDontSee('Accounting Setup');
});

it('shows company targets even without other partner permissions', function () {
    $user = settingsUserWith(['company-target-list']);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertSuccessful()
        ->assertSee(route('company-targets.index'), false)
        ->assertSee('Business Partners');
});

it('exposes searchable keywords on every link', function () {
    $user = settingsUserWith(['bank-account-list']);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertSuccessful()
        ->assertSee('data-search="bank accounts company bank accounts and balances accounting setup', false);
});

it('does not duplicate links that already live in the main navigation', function () {
    $user = settingsUserWith(['goods-issue-list', 'sales-settlement-list', 'goods-receipt-note-list', 'inventory-view', 'stock-adjustment-list']);

    $html = $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertSuccessful()
        ->getContent();

    preg_match_all('#<a\s[^>]*data-settings-item[^>]*>#', $html, $anchors);
    $settingsLinks = implode("\n", $anchors[0]);

    expect($settingsLinks)
        ->toContain(route('stock-adjustments.index'))
        ->not->toContain(route('goods-issues.index'))
        ->not->toContain(route('sales-settlements.index'))
        ->not->toContain(route('goods-receipt-notes.index'))
        ->not->toContain(route('inventory.current-stock.index'));

    expect($html)->not->toContain('Sales &amp; Distribution');
});

function settingsNavItemIsActive(string $html): bool
{
    preg_match_all('#<a\s[^>]*>#', $html, $anchors);
    $settingsUrl = route('settings.index');

    foreach ($anchors[0] as $anchor) {
        if (str_contains($anchor, 'href="'.$settingsUrl.'"') && str_contains($anchor, 'border-indigo-400')) {
            return true;
        }
    }

    return false;
}

it('keeps the Settings menu item highlighted on pages opened from settings', function (string $routeName) {
    $user = settingsUserWith(['tax-list', 'company-target-list']);

    $html = $this->actingAs($user)
        ->get(route($routeName))
        ->assertSuccessful()
        ->getContent();

    expect(settingsNavItemIsActive($html))->toBeTrue();
})->with(['tax-rates.index', 'company-targets.index']);

it('does not highlight Settings on unrelated pages', function () {
    $user = settingsUserWith([]);

    $html = $this->actingAs($user)
        ->get(route('dashboard'))
        ->getContent();

    expect(settingsNavItemIsActive($html))->toBeFalse();
});

it('does not list Expense Details, which only redirects to the Expense Detail report', function () {
    $user = settingsUserWith(['expense-detail-list', 'tax-list']);

    $html = $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertSuccessful()
        ->getContent();

    preg_match_all('#<a\s[^>]*data-settings-item[^>]*>#', $html, $anchors);

    expect(implode("\n", $anchors[0]))
        ->toContain(route('tax-codes.index'))
        ->not->toContain(route('expense-details.index'));
});

it('highlights Reports, not Settings, on expense detail pages', function () {
    foreach (['expense-detail-create', 'report-audit-expense-detail'] as $permission) {
        Permission::findOrCreate($permission);
    }
    $user = settingsUserWith(['expense-detail-create', 'report-audit-expense-detail']);

    $html = $this->actingAs($user)
        ->get(route('expense-details.create'))
        ->assertSuccessful()
        ->getContent();

    expect(settingsNavItemIsActive($html))->toBeFalse();
    preg_match_all('#<a\s[^>]*>#', $html, $anchors);
    $reportsActive = collect($anchors[0])->contains(
        fn (string $a) => str_contains($a, 'href="'.route('reports.index').'"') && str_contains($a, 'border-indigo-400')
    );
    expect($reportsActive)->toBeTrue();
});
