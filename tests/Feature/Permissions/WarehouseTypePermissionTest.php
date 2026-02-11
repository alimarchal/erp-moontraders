<?php

use App\Models\User;
use App\Models\WarehouseType;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['warehouse-type-list', 'warehouse-type-create', 'warehouse-type-edit', 'warehouse-type-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without warehouse-type-list permission', function () {
    $this->get(route('warehouse-types.index'))->assertForbidden();
});

it('allows index with warehouse-type-list permission', function () {
    $this->user->givePermissionTo('warehouse-type-list');
    $this->get(route('warehouse-types.index'))->assertSuccessful();
});

it('denies create without warehouse-type-create permission', function () {
    $this->get(route('warehouse-types.create'))->assertForbidden();
});

it('allows create with warehouse-type-create permission', function () {
    $this->user->givePermissionTo('warehouse-type-create');
    $this->get(route('warehouse-types.create'))->assertSuccessful();
});

it('denies store without warehouse-type-create permission', function () {
    $this->post(route('warehouse-types.store'), [])->assertForbidden();
});

it('denies show without warehouse-type-list permission', function () {
    $type = WarehouseType::factory()->create(['name' => 'Test Type']);
    $this->get(route('warehouse-types.show', $type))->assertForbidden();
});

it('denies edit without warehouse-type-edit permission', function () {
    $type = WarehouseType::factory()->create(['name' => 'Test Type']);
    $this->get(route('warehouse-types.edit', $type))->assertForbidden();
});

it('denies update without warehouse-type-edit permission', function () {
    $type = WarehouseType::factory()->create(['name' => 'Test Type']);
    $this->put(route('warehouse-types.update', $type), [])->assertForbidden();
});

it('denies destroy without warehouse-type-delete permission', function () {
    $type = WarehouseType::factory()->create(['name' => 'Test Type']);
    $this->delete(route('warehouse-types.destroy', $type))->assertForbidden();
});
