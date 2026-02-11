<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'report-view-inventory']);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies current stock index without report-view-inventory permission', function () {
    $this->get(route('inventory.current-stock.index'))->assertForbidden();
});

it('allows current stock index with report-view-inventory permission', function () {
    $this->user->givePermissionTo('report-view-inventory');
    $this->get(route('inventory.current-stock.index'))->assertSuccessful();
});

it('denies current stock by batch without report-view-inventory permission', function () {
    $this->get(route('inventory.current-stock.by-batch'))->assertForbidden();
});

it('allows current stock by batch with report-view-inventory permission', function () {
    $this->user->givePermissionTo('report-view-inventory');
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
