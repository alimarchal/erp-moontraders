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
    ] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies general ledger without report-financial-general-ledger permission', function () {
    $this->get(route('reports.general-ledger.index'))->assertForbidden();
});

it('allows general ledger with report-financial-general-ledger permission', function () {
    $this->user->givePermissionTo('report-financial-general-ledger');
    $this->get(route('reports.general-ledger.index'))->assertSuccessful();
});

it('denies trial balance without report-financial-trial-balance permission', function () {
    $this->get(route('reports.trial-balance.index'))->assertForbidden();
});

it('allows trial balance with report-financial-trial-balance permission', function () {
    $this->user->givePermissionTo('report-financial-trial-balance');
    $this->get(route('reports.trial-balance.index'))->assertSuccessful();
});

it('denies account balances without report-financial-account-balances permission', function () {
    $this->get(route('reports.account-balances.index'))->assertForbidden();
});

it('allows account balances with report-financial-account-balances permission', function () {
    $this->user->givePermissionTo('report-financial-account-balances');
    $this->get(route('reports.account-balances.index'))->assertSuccessful();
});

it('denies balance sheet without report-financial-balance-sheet permission', function () {
    $this->get(route('reports.balance-sheet.index'))->assertForbidden();
});

it('allows balance sheet with report-financial-balance-sheet permission', function () {
    $this->user->givePermissionTo('report-financial-balance-sheet');
    $this->get(route('reports.balance-sheet.index'))->assertSuccessful();
});

it('denies income statement without report-financial-income-statement permission', function () {
    $this->get(route('reports.income-statement.index'))->assertForbidden();
});

it('allows income statement with report-financial-income-statement permission', function () {
    $this->user->givePermissionTo('report-financial-income-statement');
    $this->get(route('reports.income-statement.index'))->assertSuccessful();
});
