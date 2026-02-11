<?php

use App\Models\AccountType;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['account-type-list', 'account-type-create', 'account-type-edit', 'account-type-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without account-type-list permission', function () {
    $this->get(route('account-types.index'))->assertForbidden();
});

it('allows index with account-type-list permission', function () {
    $this->user->givePermissionTo('account-type-list');
    $this->get(route('account-types.index'))->assertSuccessful();
});

it('denies create without account-type-create permission', function () {
    $this->get(route('account-types.create'))->assertForbidden();
});

it('allows create with account-type-create permission', function () {
    $this->user->givePermissionTo('account-type-create');
    $this->get(route('account-types.create'))->assertSuccessful();
});

it('denies store without account-type-create permission', function () {
    $this->post(route('account-types.store'), [])->assertForbidden();
});

it('denies show without account-type-list permission', function () {
    $accountType = AccountType::factory()->create();
    $this->get(route('account-types.show', $accountType))->assertForbidden();
});

it('denies edit without account-type-edit permission', function () {
    $accountType = AccountType::factory()->create();
    $this->get(route('account-types.edit', $accountType))->assertForbidden();
});

it('denies update without account-type-edit permission', function () {
    $accountType = AccountType::factory()->create();
    $this->put(route('account-types.update', $accountType), [])->assertForbidden();
});

it('denies destroy without account-type-delete permission', function () {
    $accountType = AccountType::factory()->create();
    $this->delete(route('account-types.destroy', $accountType))->assertForbidden();
});
