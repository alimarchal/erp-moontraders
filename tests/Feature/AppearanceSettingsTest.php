<?php

use App\Enums\PageLayout;
use App\Models\AppSetting;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function appearanceSuperAdmin(): User
{
    Role::findOrCreate('super-admin');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    return $user;
}

it('defaults every page to the classic layout', function () {
    expect(AppSetting::layoutFor('dashboard'))->toBe(PageLayout::Classic)
        ->and(AppSetting::layoutFor('settings'))->toBe(PageLayout::Classic)
        ->and(AppSetting::layoutFor('reports'))->toBe(PageLayout::Classic)
        ->and(AppSetting::layoutFor('goods_receipt_notes'))->toBe(PageLayout::Classic);
});

it('falls back to classic for a layout the page does not offer', function () {
    AppSetting::put('layout.dashboard', 'b');

    expect(AppSetting::layoutFor('dashboard'))->toBe(PageLayout::Classic);
});

it('lets a super admin open the page and save a layout for each page', function () {
    $admin = appearanceSuperAdmin();

    $this->actingAs($admin)
        ->get(route('settings.appearance.edit'))
        ->assertSuccessful()
        ->assertSee('Save layouts')
        ->assertSee('name="settings"', false);

    $this->actingAs($admin)
        ->put(route('settings.appearance.update'), ['dashboard' => 'a', 'settings' => 'b', 'reports' => 'a', 'goods_receipt_notes' => 'd', 'goods_receipt_notes_index' => 'b'])
        ->assertRedirect(route('settings.appearance.edit'));

    expect(AppSetting::layoutFor('dashboard'))->toBe(PageLayout::OptionA)
        ->and(AppSetting::layoutFor('settings'))->toBe(PageLayout::OptionB)
        ->and(AppSetting::layoutFor('reports'))->toBe(PageLayout::OptionA)
        ->and(AppSetting::layoutFor('goods_receipt_notes'))->toBe(PageLayout::OptionD)
        ->and(AppSetting::layoutFor('goods_receipt_notes_index'))->toBe(PageLayout::OptionB)
        ->and(AppSetting::query()->where('key', 'layout.settings')->value('updated_by'))->toBe($admin->id);
});

it('refuses a layout a page does not offer', function () {
    $this->actingAs(appearanceSuperAdmin())
        ->put(route('settings.appearance.update'), ['dashboard' => 'b', 'settings' => 'a', 'reports' => 'a', 'goods_receipt_notes' => 'a', 'goods_receipt_notes_index' => 'a'])
        ->assertSessionHasErrors('dashboard');

    expect(AppSetting::layoutFor('settings'))->toBe(PageLayout::Classic);
});

it('keeps everyone but super admins out, admins included', function () {
    Permission::findOrCreate('setting-view');
    Role::findOrCreate('admin');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('setting-view');

    $this->actingAs($admin)->get(route('settings.appearance.edit'))->assertForbidden();
    $this->actingAs($admin)
        ->put(route('settings.appearance.update'), ['dashboard' => 'a', 'settings' => 'a', 'reports' => 'a'])
        ->assertForbidden();

    expect(AppSetting::layoutFor('dashboard'))->toBe(PageLayout::Classic);
});
