<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'report-financial-general-ledger',
        'report-financial-trial-balance',
        'report-financial-account-balances',
        'report-financial-balance-sheet',
        'report-financial-income-statement',
        'report-inventory-daily-stock-register',
        'report-sales-daily-sales',
        'report-audit-cash-detail',
    ] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies reports index without any report permission', function () {
    $this->get(route('reports.index'))->assertForbidden();
});

it('allows reports index with a financial report permission', function () {
    $this->user->givePermissionTo('report-financial-general-ledger');
    $this->get(route('reports.index'))->assertSuccessful();
});

it('allows reports index with an inventory report permission', function () {
    $this->user->givePermissionTo('report-inventory-daily-stock-register');
    $this->get(route('reports.index'))->assertSuccessful();
});

it('allows reports index with a sales report permission', function () {
    $this->user->givePermissionTo('report-sales-daily-sales');
    $this->get(route('reports.index'))->assertSuccessful();
});

it('allows reports index with an audit report permission', function () {
    $this->user->givePermissionTo('report-audit-cash-detail');
    $this->get(route('reports.index'))->assertSuccessful();
});
