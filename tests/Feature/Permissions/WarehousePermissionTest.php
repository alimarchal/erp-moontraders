<?php

use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['warehouse-list', 'warehouse-create', 'warehouse-edit', 'warehouse-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without warehouse-list permission', function () {
    $this->get(route('warehouses.index'))->assertForbidden();
});

it('allows index with warehouse-list permission', function () {
    $this->user->givePermissionTo('warehouse-list');
    $this->get(route('warehouses.index'))->assertSuccessful();
});

it('denies create without warehouse-create permission', function () {
    $this->get(route('warehouses.create'))->assertForbidden();
});

it('allows create with warehouse-create permission', function () {
    $this->user->givePermissionTo('warehouse-create');
    $this->get(route('warehouses.create'))->assertSuccessful();
});

it('denies store without warehouse-create permission', function () {
    $this->post(route('warehouses.store'), [])->assertForbidden();
});

it('denies show without warehouse-list permission', function () {
    $warehouse = Warehouse::factory()->create();
    $this->get(route('warehouses.show', $warehouse))->assertForbidden();
});

it('denies edit without warehouse-edit permission', function () {
    $warehouse = Warehouse::factory()->create();
    $this->get(route('warehouses.edit', $warehouse))->assertForbidden();
});

it('denies update without warehouse-edit permission', function () {
    $warehouse = Warehouse::factory()->create();
    $this->put(route('warehouses.update', $warehouse), [])->assertForbidden();
});

it('denies destroy without warehouse-delete permission', function () {
    $warehouse = Warehouse::factory()->create();
    $this->delete(route('warehouses.destroy', $warehouse))->assertForbidden();
});
