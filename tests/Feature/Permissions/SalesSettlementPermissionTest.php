<?php

use App\Models\SalesSettlement;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['sales-settlement-list', 'sales-settlement-create', 'sales-settlement-edit', 'sales-settlement-delete', 'sales-settlement-post'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without sales-settlement-list permission', function () {
    $this->get(route('sales-settlements.index'))->assertForbidden();
});

it('allows index with sales-settlement-list permission', function () {
    $this->user->givePermissionTo('sales-settlement-list');
    $this->get(route('sales-settlements.index'))->assertSuccessful();
});

it('denies create without sales-settlement-create permission', function () {
    $this->get(route('sales-settlements.create'))->assertForbidden();
});

it('allows create with sales-settlement-create permission', function () {
    $this->user->givePermissionTo('sales-settlement-create');
    $this->get(route('sales-settlements.create'))->assertSuccessful();
});

it('denies store without sales-settlement-create permission', function () {
    $this->post(route('sales-settlements.store'), [])->assertForbidden();
});

it('denies show without sales-settlement-list permission', function () {
    $settlement = SalesSettlement::factory()->create();
    $this->get(route('sales-settlements.show', $settlement))->assertForbidden();
});

it('denies edit without sales-settlement-edit permission', function () {
    $settlement = SalesSettlement::factory()->create();
    $this->get(route('sales-settlements.edit', $settlement))->assertForbidden();
});

it('denies update without sales-settlement-edit permission', function () {
    $settlement = SalesSettlement::factory()->create();
    $this->put(route('sales-settlements.update', $settlement), [])->assertForbidden();
});

it('denies destroy without sales-settlement-delete permission', function () {
    $settlement = SalesSettlement::factory()->create();
    $this->delete(route('sales-settlements.destroy', $settlement))->assertForbidden();
});

it('denies post without sales-settlement-post permission', function () {
    $settlement = SalesSettlement::factory()->create();
    $this->post(route('sales-settlements.post', $settlement))->assertForbidden();
});
