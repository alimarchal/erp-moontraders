<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['permission-list', 'permission-create', 'permission-edit', 'permission-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without permission-list permission', function () {
    $this->get(route('permissions.index'))->assertForbidden();
});

it('allows index with permission-list permission', function () {
    $this->user->givePermissionTo('permission-list');
    $this->get(route('permissions.index'))->assertSuccessful();
});

it('denies show without permission-list permission', function () {
    $perm = \App\Models\Permission::create(['name' => 'test-perm', 'guard_name' => 'web']);
    $this->get(route('permissions.show', $perm))->assertForbidden();
});

it('denies create without permission-create permission', function () {
    $this->get(route('permissions.create'))->assertForbidden();
});

it('allows create with permission-create permission', function () {
    $this->user->givePermissionTo('permission-create');
    $this->get(route('permissions.create'))->assertSuccessful();
});

it('denies store without permission-create permission', function () {
    $this->post(route('permissions.store'), [])->assertForbidden();
});

it('denies edit without permission-edit permission', function () {
    $perm = \App\Models\Permission::create(['name' => 'test-perm', 'guard_name' => 'web']);
    $this->get(route('permissions.edit', $perm))->assertForbidden();
});

it('allows edit with permission-edit permission', function () {
    $this->user->givePermissionTo('permission-edit');
    $perm = \App\Models\Permission::create(['name' => 'test-perm', 'guard_name' => 'web']);
    $this->get(route('permissions.edit', $perm))->assertSuccessful();
});

it('denies update without permission-edit permission', function () {
    $perm = \App\Models\Permission::create(['name' => 'test-perm', 'guard_name' => 'web']);
    $this->put(route('permissions.update', $perm), [])->assertForbidden();
});

it('denies destroy without permission-delete permission', function () {
    $perm = \App\Models\Permission::create(['name' => 'test-perm', 'guard_name' => 'web']);
    $this->delete(route('permissions.destroy', $perm))->assertForbidden();
});
