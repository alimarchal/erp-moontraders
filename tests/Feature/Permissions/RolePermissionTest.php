<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['role-list', 'role-create', 'role-edit', 'role-delete', 'role-sync'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without role-list permission', function () {
    $this->get(route('roles.index'))->assertForbidden();
});

it('allows index with role-list permission', function () {
    $this->user->givePermissionTo('role-list');
    $this->get(route('roles.index'))->assertSuccessful();
});

it('denies show without role-list permission', function () {
    $role = Role::create(['name' => 'test-role']);
    $this->get(route('roles.show', $role))->assertForbidden();
});

it('allows show with role-list permission', function () {
    $this->user->givePermissionTo('role-list');
    $role = Role::create(['name' => 'test-role']);
    $this->get(route('roles.show', $role))->assertSuccessful();
});

it('denies create without role-create permission', function () {
    $this->get(route('roles.create'))->assertForbidden();
});

it('allows create with role-create permission', function () {
    $this->user->givePermissionTo('role-create');
    $this->get(route('roles.create'))->assertSuccessful();
});

it('denies store without role-create permission', function () {
    $this->post(route('roles.store'), [])->assertForbidden();
});

it('denies edit without role-edit permission', function () {
    $role = Role::create(['name' => 'test-role']);
    $this->get(route('roles.edit', $role))->assertForbidden();
});

it('allows edit with role-edit permission', function () {
    $this->user->givePermissionTo('role-edit');
    $role = Role::create(['name' => 'test-role']);
    $this->get(route('roles.edit', $role))->assertSuccessful();
});

it('denies update without role-edit permission', function () {
    $role = Role::create(['name' => 'test-role']);
    $this->put(route('roles.update', $role), [])->assertForbidden();
});

it('denies destroy without role-delete permission', function () {
    $role = Role::create(['name' => 'test-role']);
    $this->delete(route('roles.destroy', $role))->assertForbidden();
});
