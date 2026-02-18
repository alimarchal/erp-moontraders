<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'report-inventory-daily-stock-register',
        'report-inventory-salesman-stock-register',
        'report-inventory-inventory-ledger',
        'report-inventory-van-stock-batch',
        'report-inventory-van-stock-ledger',
    ] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies daily stock register without report-inventory-daily-stock-register permission', function () {
    $this->get(route('reports.daily-stock-register.index'))->assertForbidden();
});

it('allows daily stock register with report-inventory-daily-stock-register permission', function () {
    $this->user->givePermissionTo('report-inventory-daily-stock-register');
    $this->get(route('reports.daily-stock-register.index'))->assertSuccessful();
});

it('denies salesman stock register without report-inventory-salesman-stock-register permission', function () {
    $this->get(route('reports.salesman-stock-register.index'))->assertForbidden();
});

it('allows salesman stock register with report-inventory-salesman-stock-register permission', function () {
    $this->user->givePermissionTo('report-inventory-salesman-stock-register');
    $this->get(route('reports.salesman-stock-register.index'))->assertSuccessful();
});

it('denies van stock ledger without report-inventory-van-stock-ledger permission', function () {
    $this->get(route('reports.van-stock-ledger.index'))->assertForbidden();
});

it('allows van stock ledger with report-inventory-van-stock-ledger permission', function () {
    $this->user->givePermissionTo('report-inventory-van-stock-ledger');
    $this->get(route('reports.van-stock-ledger.index'))->assertSuccessful();
});

it('denies van stock ledger summary without report-inventory-van-stock-ledger permission', function () {
    $this->get(route('reports.van-stock-ledger.summary'))->assertForbidden();
});

it('allows van stock ledger summary with report-inventory-van-stock-ledger permission', function () {
    $this->user->givePermissionTo('report-inventory-van-stock-ledger');
    $this->get(route('reports.van-stock-ledger.summary'))->assertSuccessful();
});

it('denies van stock batch without report-inventory-van-stock-batch permission', function () {
    $this->get(route('reports.van-stock-batch.index'))->assertForbidden();
});

it('allows van stock batch with report-inventory-van-stock-batch permission', function () {
    $this->user->givePermissionTo('report-inventory-van-stock-batch');
    $this->get(route('reports.van-stock-batch.index'))->assertSuccessful();
});

it('denies inventory ledger without report-inventory-inventory-ledger permission', function () {
    $this->get(route('reports.inventory-ledger.index'))->assertForbidden();
});

it('allows inventory ledger with report-inventory-inventory-ledger permission', function () {
    $this->user->givePermissionTo('report-inventory-inventory-ledger');
    $this->get(route('reports.inventory-ledger.index'))->assertSuccessful();
});
