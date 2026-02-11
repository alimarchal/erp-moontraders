<?php

use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['supplier-list', 'supplier-create', 'supplier-edit', 'supplier-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without supplier-list permission', function () {
    $this->get(route('suppliers.index'))->assertForbidden();
});

it('allows index with supplier-list permission', function () {
    $this->user->givePermissionTo('supplier-list');
    $this->get(route('suppliers.index'))->assertSuccessful();
});

it('denies create without supplier-create permission', function () {
    $this->get(route('suppliers.create'))->assertForbidden();
});

it('allows create with supplier-create permission', function () {
    $this->user->givePermissionTo('supplier-create');
    $this->get(route('suppliers.create'))->assertSuccessful();
});

it('denies store without supplier-create permission', function () {
    $this->post(route('suppliers.store'), [])->assertForbidden();
});

it('denies show without supplier-list permission', function () {
    $supplier = Supplier::factory()->create();
    $this->get(route('suppliers.show', $supplier))->assertForbidden();
});

it('denies edit without supplier-edit permission', function () {
    $supplier = Supplier::factory()->create();
    $this->get(route('suppliers.edit', $supplier))->assertForbidden();
});

it('denies update without supplier-edit permission', function () {
    $supplier = Supplier::factory()->create();
    $this->put(route('suppliers.update', $supplier), [])->assertForbidden();
});

it('denies destroy without supplier-delete permission', function () {
    $supplier = Supplier::factory()->create();
    $this->delete(route('suppliers.destroy', $supplier))->assertForbidden();
});
