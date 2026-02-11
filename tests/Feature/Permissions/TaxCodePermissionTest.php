<?php

use App\Models\TaxCode;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['tax-list', 'tax-create', 'tax-edit', 'tax-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without tax-list permission', function () {
    $this->get(route('tax-codes.index'))->assertForbidden();
});

it('allows index with tax-list permission', function () {
    $this->user->givePermissionTo('tax-list');
    $this->get(route('tax-codes.index'))->assertSuccessful();
});

it('denies create without tax-create permission', function () {
    $this->get(route('tax-codes.create'))->assertForbidden();
});

it('allows create with tax-create permission', function () {
    $this->user->givePermissionTo('tax-create');
    $this->get(route('tax-codes.create'))->assertSuccessful();
});

it('denies store without tax-create permission', function () {
    $this->post(route('tax-codes.store'), [])->assertForbidden();
});

it('denies show without tax-list permission', function () {
    $taxCode = TaxCode::factory()->create();
    $this->get(route('tax-codes.show', $taxCode))->assertForbidden();
});

it('denies edit without tax-edit permission', function () {
    $taxCode = TaxCode::factory()->create();
    $this->get(route('tax-codes.edit', $taxCode))->assertForbidden();
});

it('denies update without tax-edit permission', function () {
    $taxCode = TaxCode::factory()->create();
    $this->put(route('tax-codes.update', $taxCode), [])->assertForbidden();
});

it('denies destroy without tax-delete permission', function () {
    $taxCode = TaxCode::factory()->create();
    $this->delete(route('tax-codes.destroy', $taxCode))->assertForbidden();
});
