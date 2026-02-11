<?php

use App\Models\User;
use App\Models\Vehicle;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['vehicle-list', 'vehicle-create', 'vehicle-edit', 'vehicle-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without vehicle-list permission', function () {
    $this->get(route('vehicles.index'))->assertForbidden();
});

it('allows index with vehicle-list permission', function () {
    $this->user->givePermissionTo('vehicle-list');
    $this->get(route('vehicles.index'))->assertSuccessful();
});

it('denies create without vehicle-create permission', function () {
    $this->get(route('vehicles.create'))->assertForbidden();
});

it('allows create with vehicle-create permission', function () {
    $this->user->givePermissionTo('vehicle-create');
    $this->get(route('vehicles.create'))->assertSuccessful();
});

it('denies store without vehicle-create permission', function () {
    $this->post(route('vehicles.store'), [])->assertForbidden();
});

it('denies show without vehicle-list permission', function () {
    $vehicle = Vehicle::factory()->create();
    $this->get(route('vehicles.show', $vehicle))->assertForbidden();
});

it('denies edit without vehicle-edit permission', function () {
    $vehicle = Vehicle::factory()->create();
    $this->get(route('vehicles.edit', $vehicle))->assertForbidden();
});

it('denies update without vehicle-edit permission', function () {
    $vehicle = Vehicle::factory()->create();
    $this->put(route('vehicles.update', $vehicle), [])->assertForbidden();
});

it('denies destroy without vehicle-delete permission', function () {
    $vehicle = Vehicle::factory()->create();
    $this->delete(route('vehicles.destroy', $vehicle))->assertForbidden();
});

it('denies export PDF without vehicle-list permission', function () {
    $this->get(route('vehicles.export.pdf'))->assertForbidden();
});

it('allows export PDF with vehicle-list permission', function () {
    $this->user->givePermissionTo('vehicle-list');
    $this->get(route('vehicles.export.pdf'))->assertSuccessful();
});
