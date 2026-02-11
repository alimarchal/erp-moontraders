<?php

use App\Models\ChartOfAccount;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['chart-of-account-list', 'chart-of-account-create', 'chart-of-account-edit', 'chart-of-account-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without chart-of-account-list permission', function () {
    $this->get(route('chart-of-accounts.index'))->assertForbidden();
});

it('allows index with chart-of-account-list permission', function () {
    $this->user->givePermissionTo('chart-of-account-list');
    $this->get(route('chart-of-accounts.index'))->assertSuccessful();
});

it('denies tree view without chart-of-account-list permission', function () {
    $this->get(route('chart-of-accounts.tree'))->assertForbidden();
});

it('allows tree view with chart-of-account-list permission', function () {
    $this->user->givePermissionTo('chart-of-account-list');
    $this->get(route('chart-of-accounts.tree'))->assertSuccessful();
});

it('denies create without chart-of-account-create permission', function () {
    $this->get(route('chart-of-accounts.create'))->assertForbidden();
});

it('allows create with chart-of-account-create permission', function () {
    $this->user->givePermissionTo('chart-of-account-create');
    $this->get(route('chart-of-accounts.create'))->assertSuccessful();
});

it('denies store without chart-of-account-create permission', function () {
    $this->post(route('chart-of-accounts.store'), [])->assertForbidden();
});

it('denies show without chart-of-account-list permission', function () {
    $account = ChartOfAccount::factory()->create();
    $this->get(route('chart-of-accounts.show', $account))->assertForbidden();
});

it('denies edit without chart-of-account-edit permission', function () {
    $account = ChartOfAccount::factory()->create();
    $this->get(route('chart-of-accounts.edit', $account))->assertForbidden();
});

it('denies update without chart-of-account-edit permission', function () {
    $account = ChartOfAccount::factory()->create();
    $this->put(route('chart-of-accounts.update', $account), [])->assertForbidden();
});

it('denies destroy without chart-of-account-delete permission', function () {
    $account = ChartOfAccount::factory()->create();
    $this->delete(route('chart-of-accounts.destroy', $account))->assertForbidden();
});
