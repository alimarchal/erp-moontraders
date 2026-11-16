<?php

use App\Models\AppSetting;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * A user holding the base permission of the page plus the given ones.
 *
 * @param  list<string>  $permissions
 */
function menuLayoutUser(string $page, array $permissions, array $attributes = []): User
{
    $all = array_values(array_unique(array_merge([$page === 'settings' ? 'setting-view' : 'report-financial-general-ledger'], $permissions)));

    foreach ($all as $permission) {
        Permission::findOrCreate($permission);
    }

    $user = User::factory()->create($attributes);
    $user->givePermissionTo($all);

    return $user;
}

/**
 * Every permission the classic page checks with @can or @canany (gates that are not permissions left out).
 *
 * @return list<string>
 */
function classicPagePermissions(string $page): array
{
    $source = file_get_contents(resource_path("views/{$page}/index.blade.php"));
    preg_match_all("/@can(?:any)?\((\[[^\]]*\]|'[^']+')\)/", $source, $matches);

    return collect($matches[1])
        ->flatMap(fn (string $argument) => preg_match_all("/'([^']+)'/", $argument, $names) ? $names[1] : [])
        ->reject(fn (string $permission) => $permission === 'manage-appearance')
        ->unique()
        ->values()
        ->all();
}

/**
 * The hrefs of the menu links on a page: data-settings-item anchors on the classic pages,
 * data-menu-link anchors on Option A and B.
 *
 * @return list<string>
 */
function menuLinkHrefs(string $html, string $marker): array
{
    preg_match_all('#<a\s[^>]*'.$marker.'[^>]*>#', $html, $anchors);

    return collect($anchors[0])
        ->map(fn (string $anchor) => preg_match('#href="([^"]+)"#', $anchor, $match) ? html_entity_decode($match[1]) : null)
        ->filter()
        ->unique()
        ->sort()
        ->values()
        ->all();
}

dataset('menu layouts', [
    'settings A' => ['settings', 'a', 'settings.layouts.option-a'],
    'settings B' => ['settings', 'b', 'settings.layouts.option-b'],
    'reports A' => ['reports', 'a', 'reports.layouts.option-a'],
    'reports B' => ['reports', 'b', 'reports.layouts.option-b'],
]);

it('picks the view from the layout chosen on Appearance', function (string $page, string $layout, string $view) {
    $user = menuLayoutUser($page, []);

    $this->actingAs($user)->get(route("{$page}.index"))->assertViewIs("{$page}.index");

    AppSetting::put("layout.{$page}", $layout);

    $this->actingAs($user)->get(route("{$page}.index"))->assertSuccessful()->assertViewIs($view);
})->with('menu layouts');

it('shows only the links and groups the user is permitted to see', function (string $page, string $layout) {
    AppSetting::put("layout.{$page}", $layout);

    [$permissions, $allowed, $denied, $shownGroup, $hiddenGroup] = $page === 'settings'
        ? [['user-list', 'stock-adjustment-list'], [route('users.index'), route('stock-adjustments.index')], route('chart-of-accounts.index'), 'Company &amp; access', 'Accounting']
        : [['report-audit-creditors-ledger', 'payroll-list'], [route('reports.creditors-ledger.index'), route('payroll.index')], route('reports.trial-balance.index'), 'Receivables &amp; cash', 'Stock &amp; vans'];

    $html = $this->actingAs(menuLayoutUser($page, $permissions))
        ->get(route("{$page}.index"))
        ->assertSuccessful()
        ->assertSee($shownGroup, false)
        ->assertDontSee($hiddenGroup, false)
        ->assertDontSee('&amp;amp;', false)
        ->getContent();

    $hrefs = menuLinkHrefs($html, 'data-menu-link');

    expect($hrefs)->toContain(...$allowed)
        ->and($hrefs)->not->toContain($denied)
        ->and(substr_count($html, 'data-menu-item data-'))->toBe(substr_count($html, 'x-show="matches($el)"'));
})->with('menu layouts');

it('keeps every link of the classic page for a user with all its permissions', function (string $page, string $layout) {
    $user = menuLayoutUser($page, classicPagePermissions($page));

    $classic = menuLinkHrefs($this->actingAs($user)->get(route("{$page}.index"))->assertSuccessful()->getContent(), 'data-settings-item');

    AppSetting::put("layout.{$page}", $layout);

    $redesign = menuLinkHrefs($this->actingAs($user)->get(route("{$page}.index"))->assertSuccessful()->getContent(), 'data-menu-link');

    expect($classic)->not->toBeEmpty()
        ->and($redesign)->toBe($classic)
        ->and($redesign)->not->toContain(route('settings.appearance.edit'));
})->with('menu layouts');

it('shows the Appearance link on Settings to super admins only, in every layout', function (string $layout) {
    AppSetting::put('layout.settings', $layout);
    $appearance = route('settings.appearance.edit');
    $marker = $layout === 'classic' ? 'data-settings-item' : 'data-menu-link';

    $superAdmin = menuLayoutUser('settings', [], ['is_super_admin' => 'Yes']);
    $admin = menuLayoutUser('settings', classicPagePermissions('settings'));

    $superAdminLinks = menuLinkHrefs($this->actingAs($superAdmin)->get(route('settings.index'))->assertSuccessful()->getContent(), $marker);
    $adminLinks = menuLinkHrefs($this->actingAs($admin)->get(route('settings.index'))->assertSuccessful()->getContent(), $marker);

    expect($superAdminLinks)->toContain($appearance)
        ->and($adminLinks)->not->toContain($appearance)
        ->and(array_values(array_diff($superAdminLinks, $adminLinks)))->toBe([$appearance]);
})->with(['classic', 'a', 'b']);

it('names the customer balances report Debtors ledger and links the bank register reports', function (string $layout) {
    AppSetting::put('layout.reports', $layout);

    $this->actingAs(menuLayoutUser('reports', ['report-audit-creditors-ledger', 'report-bank-summary']))
        ->get(route('reports.index'))
        ->assertSuccessful()
        ->assertSee('Debtors ledger')
        ->assertDontSee('Creditors Ledger')
        ->assertSee(route('reports.bank.index'), false);
})->with(['a', 'b']);
