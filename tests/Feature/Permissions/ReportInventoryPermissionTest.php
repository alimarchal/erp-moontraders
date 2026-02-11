<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'report-view-inventory']);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies daily stock register without report-view-inventory permission', function () {
    $this->get(route('reports.daily-stock-register.index'))->assertForbidden();
});

it('allows daily stock register with report-view-inventory permission', function () {
    $this->user->givePermissionTo('report-view-inventory');
    $this->get(route('reports.daily-stock-register.index'))->assertSuccessful();
});

it('denies salesman stock register without report-view-inventory permission', function () {
    $this->get(route('reports.salesman-stock-register.index'))->assertForbidden();
});

it('allows salesman stock register with report-view-inventory permission', function () {
    $this->user->givePermissionTo('report-view-inventory');
    $this->get(route('reports.salesman-stock-register.index'))->assertSuccessful();
});

it('denies van stock ledger without report-view-inventory permission', function () {
    $this->get(route('reports.van-stock-ledger.index'))->assertForbidden();
});

it('allows van stock ledger with report-view-inventory permission', function () {
    $this->user->givePermissionTo('report-view-inventory');
    $this->get(route('reports.van-stock-ledger.index'))->assertSuccessful();
});

it('denies van stock ledger summary without report-view-inventory permission', function () {
    $this->get(route('reports.van-stock-ledger.summary'))->assertForbidden();
});

it('allows van stock ledger summary with report-view-inventory permission', function () {
    $this->user->givePermissionTo('report-view-inventory');
    $this->get(route('reports.van-stock-ledger.summary'))->assertSuccessful();
});

it('denies van stock batch without report-view-inventory permission', function () {
    $this->get(route('reports.van-stock-batch.index'))->assertForbidden();
});

it('allows van stock batch with report-view-inventory permission', function () {
    $this->user->givePermissionTo('report-view-inventory');
    $this->get(route('reports.van-stock-batch.index'))->assertSuccessful();
});

it('denies inventory ledger without report-view-inventory permission', function () {
    $this->get(route('reports.inventory-ledger.index'))->assertForbidden();
});

it('allows inventory ledger with report-view-inventory permission', function () {
    $this->user->givePermissionTo('report-view-inventory');
    $this->get(route('reports.inventory-ledger.index'))->assertSuccessful();
});
