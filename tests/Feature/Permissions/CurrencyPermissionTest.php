<?php

use App\Models\Currency;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['currency-list', 'currency-create', 'currency-edit', 'currency-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without currency-list permission', function () {
    $this->get(route('currencies.index'))->assertForbidden();
});

it('allows index with currency-list permission', function () {
    $this->user->givePermissionTo('currency-list');
    $this->get(route('currencies.index'))->assertSuccessful();
});

it('denies create without currency-create permission', function () {
    $this->get(route('currencies.create'))->assertForbidden();
});

it('allows create with currency-create permission', function () {
    $this->user->givePermissionTo('currency-create');
    $this->get(route('currencies.create'))->assertSuccessful();
});

it('denies store without currency-create permission', function () {
    $this->post(route('currencies.store'), [])->assertForbidden();
});

it('denies show without currency-list permission', function () {
    $currency = Currency::factory()->create();
    $this->get(route('currencies.show', $currency))->assertForbidden();
});

it('denies edit without currency-edit permission', function () {
    $currency = Currency::factory()->create();
    $this->get(route('currencies.edit', $currency))->assertForbidden();
});

it('denies update without currency-edit permission', function () {
    $currency = Currency::factory()->create();
    $this->put(route('currencies.update', $currency), [])->assertForbidden();
});

it('denies destroy without currency-delete permission', function () {
    $currency = Currency::factory()->create();
    $this->delete(route('currencies.destroy', $currency))->assertForbidden();
});
