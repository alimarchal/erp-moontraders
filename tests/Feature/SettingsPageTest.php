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
        ->assertSee('data-search="bank accounts company bank accounts &amp; balances accounting setup', false);
});

it('does not duplicate links that already live in the main navigation', function () {
    $user = settingsUserWith(['goods-issue-list', 'sales-settlement-list', 'goods-receipt-note-list', 'inventory-view', 'stock-adjustment-list']);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertSuccessful()
        ->assertSee(route('stock-adjustments.index'), false)
        ->assertDontSee(route('goods-issues.index'), false)
        ->assertDontSee(route('sales-settlements.index'), false)
        ->assertDontSee(route('goods-receipt-notes.index'), false)
        ->assertDontSee(route('inventory.current-stock.index'), false)
        ->assertDontSee('Sales & Distribution');
});
