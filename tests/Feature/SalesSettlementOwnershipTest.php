<?php

use App\Models\SalesSettlement;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'sales-settlement-list', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'sales-settlement-create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'sales-settlement-edit', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'sales-settlement-delete', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'sales-settlement-post', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'sales-settlement-view-all', 'guard_name' => 'web']);

    $this->regularRole = Role::firstOrCreate(['name' => 'sales-user', 'guard_name' => 'web']);
    $this->regularRole->syncPermissions([
        'sales-settlement-list',
        'sales-settlement-create',
        'sales-settlement-edit',
        'sales-settlement-delete',
        'sales-settlement-post',
    ]);

    $this->adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->adminRole->syncPermissions([
        'sales-settlement-list',
        'sales-settlement-create',
        'sales-settlement-edit',
        'sales-settlement-delete',
        'sales-settlement-post',
        'sales-settlement-view-all',
    ]);
});

it('regular user sees only their own settlements on index', function () {
    $user = User::factory()->create();
    $user->assignRole($this->regularRole);

    $otherUser = User::factory()->create();

    $this->actingAs($user);
    $ownSettlement = SalesSettlement::factory()->create();

    auth()->login($otherUser);
    $otherSettlement = SalesSettlement::factory()->create();

    auth()->login($user);

    $this->get(route('sales-settlements.index'))
        ->assertSuccessful()
        ->assertSee($ownSettlement->settlement_number)
        ->assertDontSee($otherSettlement->settlement_number);
});

it('admin user sees all settlements on index', function () {
    $admin = User::factory()->create();
    $admin->assignRole($this->adminRole);

    $otherUser1 = User::factory()->create();
    $otherUser2 = User::factory()->create();

    auth()->login($otherUser1);
    $settlement1 = SalesSettlement::factory()->create();

    auth()->login($otherUser2);
    $settlement2 = SalesSettlement::factory()->create();

    $this->actingAs($admin)
        ->get(route('sales-settlements.index'))
        ->assertSuccessful()
        ->assertSee($settlement1->settlement_number)
        ->assertSee($settlement2->settlement_number);
});

it('super admin sees all settlements on index', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => 'Yes']);

    $otherUser1 = User::factory()->create();
    $otherUser2 = User::factory()->create();

    auth()->login($otherUser1);
    $settlement1 = SalesSettlement::factory()->create();

    auth()->login($otherUser2);
    $settlement2 = SalesSettlement::factory()->create();

    $this->actingAs($superAdmin)
        ->get(route('sales-settlements.index'))
        ->assertSuccessful()
        ->assertSee($settlement1->settlement_number)
        ->assertSee($settlement2->settlement_number);
});

it('regular user can view their own settlement', function () {
    $user = User::factory()->create();
    $user->assignRole($this->regularRole);

    auth()->login($user);
    $ownSettlement = SalesSettlement::factory()->create();

    $this->actingAs($user)
        ->get(route('sales-settlements.show', $ownSettlement))
        ->assertSuccessful();
});

it('regular user cannot view another users settlement', function () {
    $user = User::factory()->create();
    $user->assignRole($this->regularRole);

    $otherUser = User::factory()->create();
    auth()->login($otherUser);
    $otherSettlement = SalesSettlement::factory()->create();

    $this->actingAs($user)
        ->get(route('sales-settlements.show', $otherSettlement))
        ->assertForbidden();
});

it('admin can view any users settlement', function () {
    $admin = User::factory()->create();
    $admin->assignRole($this->adminRole);

    $otherUser = User::factory()->create();
    auth()->login($otherUser);
    $otherSettlement = SalesSettlement::factory()->create();

    $this->actingAs($admin)
        ->get(route('sales-settlements.show', $otherSettlement))
        ->assertSuccessful();
});

it('super admin can view any users settlement', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => 'Yes']);

    $otherUser = User::factory()->create();
    auth()->login($otherUser);
    $otherSettlement = SalesSettlement::factory()->create();

    $this->actingAs($superAdmin)
        ->get(route('sales-settlements.show', $otherSettlement))
        ->assertSuccessful();
});

it('regular user cannot edit another users settlement', function () {
    $user = User::factory()->create();
    $user->assignRole($this->regularRole);

    $otherUser = User::factory()->create();
    auth()->login($otherUser);
    $otherSettlement = SalesSettlement::factory()->create(['status' => 'draft']);

    $this->actingAs($user)
        ->get(route('sales-settlements.edit', $otherSettlement))
        ->assertForbidden();
});

it('regular user cannot delete another users settlement', function () {
    $user = User::factory()->create();
    $user->assignRole($this->regularRole);

    $otherUser = User::factory()->create();
    auth()->login($otherUser);
    $otherSettlement = SalesSettlement::factory()->create(['status' => 'draft']);

    $this->actingAs($user)
        ->delete(route('sales-settlements.destroy', $otherSettlement))
        ->assertForbidden();
});

it('regular user can edit their own draft settlement', function () {
    $user = User::factory()->create();
    $user->assignRole($this->regularRole);

    auth()->login($user);
    $ownSettlement = SalesSettlement::factory()->create(['status' => 'draft']);

    $this->actingAs($user)
        ->get(route('sales-settlements.edit', $ownSettlement))
        ->assertSuccessful();
});
