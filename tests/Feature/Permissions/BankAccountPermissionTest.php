<?php

use App\Models\BankAccount;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['bank-account-list', 'bank-account-create', 'bank-account-edit', 'bank-account-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without bank-account-list permission', function () {
    $this->get(route('bank-accounts.index'))->assertForbidden();
});

it('allows index with bank-account-list permission', function () {
    $this->user->givePermissionTo('bank-account-list');
    $this->get(route('bank-accounts.index'))->assertSuccessful();
});

it('denies create without bank-account-create permission', function () {
    $this->get(route('bank-accounts.create'))->assertForbidden();
});

it('allows create with bank-account-create permission', function () {
    $this->user->givePermissionTo('bank-account-create');
    $this->get(route('bank-accounts.create'))->assertSuccessful();
});

it('denies store without bank-account-create permission', function () {
    $this->post(route('bank-accounts.store'), [])->assertForbidden();
});

it('denies show without bank-account-list permission', function () {
    $bankAccount = BankAccount::factory()->create();
    $this->get(route('bank-accounts.show', $bankAccount))->assertForbidden();
});

it('denies edit without bank-account-edit permission', function () {
    $bankAccount = BankAccount::factory()->create();
    $this->get(route('bank-accounts.edit', $bankAccount))->assertForbidden();
});

it('denies update without bank-account-edit permission', function () {
    $bankAccount = BankAccount::factory()->create();
    $this->put(route('bank-accounts.update', $bankAccount), [])->assertForbidden();
});

it('denies destroy without bank-account-delete permission', function () {
    $bankAccount = BankAccount::factory()->create();
    $this->delete(route('bank-accounts.destroy', $bankAccount))->assertForbidden();
});
