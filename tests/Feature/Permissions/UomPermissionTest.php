<?php

use App\Models\Uom;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['uom-list', 'uom-create', 'uom-edit', 'uom-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without uom-list permission', function () {
    $this->get(route('uoms.index'))->assertForbidden();
});

it('allows index with uom-list permission', function () {
    $this->user->givePermissionTo('uom-list');
    $this->get(route('uoms.index'))->assertSuccessful();
});

it('denies create without uom-create permission', function () {
    $this->get(route('uoms.create'))->assertForbidden();
});

it('allows create with uom-create permission', function () {
    $this->user->givePermissionTo('uom-create');
    $this->get(route('uoms.create'))->assertSuccessful();
});

it('denies store without uom-create permission', function () {
    $this->post(route('uoms.store'), [])->assertForbidden();
});

it('denies show without uom-list permission', function () {
    $uom = Uom::factory()->create();
    $this->get(route('uoms.show', $uom))->assertForbidden();
});

it('denies edit without uom-edit permission', function () {
    $uom = Uom::factory()->create();
    $this->get(route('uoms.edit', $uom))->assertForbidden();
});

it('denies update without uom-edit permission', function () {
    $uom = Uom::factory()->create();
    $this->put(route('uoms.update', $uom), [])->assertForbidden();
});

it('denies destroy without uom-delete permission', function () {
    $uom = Uom::factory()->create();
    $this->delete(route('uoms.destroy', $uom))->assertForbidden();
});
