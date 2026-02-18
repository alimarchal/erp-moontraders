<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'inventory-view']);
    Permission::create(['name' => 'report-inventory-daily-stock-register']);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies current stock index without any inventory permission', function () {
    $this->get(route('inventory.current-stock.index'))->assertForbidden();
});

it('allows current stock index with inventory-view permission', function () {
    $this->user->givePermissionTo('inventory-view');
    $this->get(route('inventory.current-stock.index'))->assertSuccessful();
});

it('allows current stock index with report-inventory-daily-stock-register permission', function () {
    $this->user->givePermissionTo('report-inventory-daily-stock-register');
    $this->get(route('inventory.current-stock.index'))->assertSuccessful();
});

it('denies current stock by batch without any inventory permission', function () {
    $this->get(route('inventory.current-stock.by-batch'))->assertForbidden();
});

it('allows current stock by batch with inventory-view permission', function () {
    $this->user->givePermissionTo('inventory-view');
    $this->get(route('inventory.current-stock.by-batch'))->assertSuccessful();
});

it('redirects unauthenticated users on current stock index', function () {
    auth()->logout();
    $this->get(route('inventory.current-stock.index'))->assertRedirect(route('login'));
});

it('redirects unauthenticated users on current stock by batch', function () {
    auth()->logout();
    $this->get(route('inventory.current-stock.by-batch'))->assertRedirect(route('login'));
});
