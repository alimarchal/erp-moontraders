<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['user-list', 'user-create', 'user-edit', 'user-delete', 'user-bulk-update'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without user-list permission', function () {
    $this->get(route('users.index'))->assertForbidden();
});

it('allows index with user-list permission', function () {
    $this->user->givePermissionTo('user-list');
    $this->get(route('users.index'))->assertSuccessful();
});

it('denies show without user-list permission', function () {
    $target = User::factory()->create();
    $this->get(route('users.show', $target))->assertForbidden();
});

it('allows show with user-list permission', function () {
    $this->user->givePermissionTo('user-list');
    $target = User::factory()->create();
    $this->get(route('users.show', $target))->assertSuccessful();
});

it('denies create without user-create permission', function () {
    $this->get(route('users.create'))->assertForbidden();
});

it('allows create with user-create permission', function () {
    $this->user->givePermissionTo('user-create');
    $this->get(route('users.create'))->assertSuccessful();
});

it('denies store without user-create permission', function () {
    $this->post(route('users.store'), [])->assertForbidden();
});

it('denies edit without user-edit permission', function () {
    $target = User::factory()->create();
    $this->get(route('users.edit', $target))->assertForbidden();
});

it('allows edit with user-edit permission', function () {
    $this->user->givePermissionTo('user-edit');
    $target = User::factory()->create();
    $this->get(route('users.edit', $target))->assertSuccessful();
});

it('denies update without user-edit permission', function () {
    $target = User::factory()->create();
    $this->put(route('users.update', $target), [])->assertForbidden();
});

it('denies destroy without user-delete permission', function () {
    $target = User::factory()->create();
    $this->delete(route('users.destroy', $target))->assertForbidden();
});

it('denies bulk-update without user-bulk-update permission', function () {
    $this->post(route('users.bulk-update'), [])->assertForbidden();
});
