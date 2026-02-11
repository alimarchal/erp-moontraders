<?php

use App\Models\CostCenter;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['cost-center-list', 'cost-center-create', 'cost-center-edit', 'cost-center-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without cost-center-list permission', function () {
    $this->get(route('cost-centers.index'))->assertForbidden();
});

it('allows index with cost-center-list permission', function () {
    $this->user->givePermissionTo('cost-center-list');
    $this->get(route('cost-centers.index'))->assertSuccessful();
});

it('denies create without cost-center-create permission', function () {
    $this->get(route('cost-centers.create'))->assertForbidden();
});

it('allows create with cost-center-create permission', function () {
    $this->user->givePermissionTo('cost-center-create');
    $this->get(route('cost-centers.create'))->assertSuccessful();
});

it('denies store without cost-center-create permission', function () {
    $this->post(route('cost-centers.store'), [])->assertForbidden();
});

it('denies show without cost-center-list permission', function () {
    $costCenter = CostCenter::factory()->create();
    $this->get(route('cost-centers.show', $costCenter))->assertForbidden();
});

it('denies edit without cost-center-edit permission', function () {
    $costCenter = CostCenter::factory()->create();
    $this->get(route('cost-centers.edit', $costCenter))->assertForbidden();
});

it('denies update without cost-center-edit permission', function () {
    $costCenter = CostCenter::factory()->create();
    $this->put(route('cost-centers.update', $costCenter), [])->assertForbidden();
});

it('denies destroy without cost-center-delete permission', function () {
    $costCenter = CostCenter::factory()->create();
    $this->delete(route('cost-centers.destroy', $costCenter))->assertForbidden();
});
