<?php

use App\Models\User;
use App\Services\PermissionCatalog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['user-list', 'user-create', 'user-edit', 'user-delete', 'user-bulk-update', 'role-create', 'role-edit'] as $name) {
        Permission::firstOrCreate(['name' => $name]);
    }

    $this->admin = User::factory()->create(['is_super_admin' => 'No', 'is_active' => 'Yes']);
    $this->admin->givePermissionTo(['user-list', 'user-create', 'user-edit', 'user-bulk-update', 'role-create', 'role-edit']);
    $this->actingAs($this->admin);
});

/**
 * @return array<string, array{module: string, section: string, group: string, action: string}>
 */
function catalogIndex(array $catalog): array
{
    $index = [];
    foreach ($catalog as $module) {
        foreach ($module['sections'] as $section) {
            foreach ($section['groups'] as $group) {
                foreach ($group['permissions'] as $permission) {
                    $index[$permission['name']] = ['module' => $module['key'], 'section' => $section['label'], 'group' => $group['key'], 'action' => $permission['action']];
                }
            }
        }
    }

    return $index;
}

it('arranges permissions like the settings and reports screens without losing any', function () {
    foreach (['report-financial-trial-balance', 'report-audit-ledger-register', 'report-audit-ledger-register-manage',
        'report-audit-advance-tax-sales-register', 'goods-issue-view-own', 'supplier-payment-post', 'widget-create'] as $name) {
        Permission::firstOrCreate(['name' => $name]);
    }

    $index = catalogIndex(PermissionCatalog::build(Permission::all()));

    expect($index)->toHaveCount(Permission::count())
        ->and($index['user-list'])->toMatchArray(['module' => 'settings', 'section' => 'Access & Identity', 'group' => 'user', 'action' => 'list'])
        ->and($index['report-financial-trial-balance'])->toMatchArray(['module' => 'reports', 'section' => 'Financial Statements', 'action' => 'view-report'])
        ->and($index['report-audit-ledger-register-manage'])->toMatchArray(['group' => 'report-audit-ledger-register', 'action' => 'manage'])
        ->and($index['report-audit-advance-tax-sales-register']['group'])->toBe('report-audit-advance-tax-sales-register')
        ->and($index['goods-issue-view-own'])->toMatchArray(['group' => 'goods-issue', 'action' => 'view-own'])
        ->and($index['supplier-payment-post']['group'])->toBe('supplier-payment')
        ->and($index['widget-create'])->toMatchArray(['module' => 'other', 'group' => 'widget', 'action' => 'create']);
});

it('searches users by name, email or designation regardless of case', function () {
    User::factory()->create(['name' => 'Zahid Khan', 'email' => 'zk@example.com', 'designation' => 'Driver']);
    User::factory()->create(['name' => 'Other Person', 'email' => 'other@example.com', 'designation' => 'Accountant']);

    $this->get(route('users.index', ['filter' => ['search' => 'ZAHID']]))
        ->assertOk()->assertSee('Zahid Khan')->assertDontSee('Other Person');

    $this->get(route('users.index', ['filter' => ['search' => 'accountant']]))
        ->assertOk()->assertSee('Other Person')->assertDontSee('Zahid Khan');
});

it('lists active users without any role or permission', function () {
    $idle = User::factory()->create(['name' => 'No Access Yet', 'is_active' => 'Yes', 'is_super_admin' => 'No']);
    $withRole = User::factory()->create(['name' => 'Has A Role', 'is_active' => 'Yes', 'is_super_admin' => 'No']);
    $withRole->assignRole(Role::create(['name' => 'user']));

    $response = $this->get(route('users.index', ['filter' => ['access' => 'none', 'is_active' => 'Yes']]))
        ->assertOk()->assertSee($idle->name)->assertDontSee($withRole->name);

    expect($response->viewData('stats')['no_access'])->toBe(1);
});

it('counts the status tabs with the other filters applied', function () {
    User::factory()->count(2)->create(['designation' => 'Salesman', 'is_active' => 'Yes']);
    User::factory()->create(['designation' => 'Salesman', 'is_active' => 'No']);

    $stats = $this->get(route('users.index', ['filter' => ['designation' => 'Salesman', 'is_active' => 'No']]))
        ->assertOk()->viewData('stats');

    expect($stats)->toMatchArray(['total' => 3, 'active' => 2, 'inactive' => 1]);
});

it('ignores an unsupported rows-per-page value', function () {
    $this->get(route('users.index', ['per_page' => 5000]))
        ->assertOk()->assertViewHas('perPage', '10');
});

it('shows every matching user on one page when all rows are chosen', function () {
    User::factory()->count(12)->create();

    $users = $this->get(route('users.index', ['per_page' => 'all']))
        ->assertOk()->viewData('users');

    expect($users->count())->toBe(User::count())->and($users->lastPage())->toBe(1);
});

it('shows where each of a user\'s permissions comes from', function () {
    $this->admin->givePermissionTo('user-list');
    Permission::firstOrCreate(['name' => 'report-sales-roi']);
    $role = Role::create(['name' => 'sales-manager']);
    $role->givePermissionTo('report-sales-roi');

    $target = User::factory()->create(['name' => 'Viewed Person']);
    $target->assignRole($role);
    $target->givePermissionTo('user-create');

    $this->get(route('users.show', $target))
        ->assertOk()
        ->assertSee('Viewed Person')
        ->assertSee('Return on Investment')
        ->assertSee('from role: sales-manager')
        ->assertSee('user-create — extra permission', false);
});

it('keeps the super admin flag when a non super admin saves a user', function () {
    $target = User::factory()->create(['is_super_admin' => 'Yes', 'is_active' => 'Yes']);

    $this->get(route('users.edit', $target))
        ->assertOk()
        ->assertSee('<input type="hidden" name="is_super_admin" value="Yes">', false);

    $this->put(route('users.update', $target), [
        'name' => 'Renamed',
        'email' => $target->email,
        'is_super_admin' => 'No',
        'is_active' => 'Yes',
    ])->assertRedirect(route('users.index'));

    expect($target->fresh())->name->toBe('Renamed')->is_super_admin->toBe('Yes');
});

it('shows every permission on the add user and role screens', function () {
    Permission::firstOrCreate(['name' => 'report-sales-roi']);
    $roi = Permission::findByName('report-sales-roi');

    $this->get(route('users.create'))
        ->assertOk()
        ->assertSee('Return on Investment')
        ->assertSee('name="permissions[]" value="'.$roi->id.'"', false);

    $this->get(route('roles.create'))
        ->assertOk()
        ->assertSee('Return on Investment')
        ->assertSee('name="permissions[]" value="'.$roi->id.'"', false);
});
