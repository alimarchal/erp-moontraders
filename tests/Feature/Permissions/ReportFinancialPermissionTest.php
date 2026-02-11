<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'report-view-financial']);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies general ledger without report-view-financial permission', function () {
    $this->get(route('reports.general-ledger.index'))->assertForbidden();
});

it('allows general ledger with report-view-financial permission', function () {
    $this->user->givePermissionTo('report-view-financial');
    $this->get(route('reports.general-ledger.index'))->assertSuccessful();
});

it('denies trial balance without report-view-financial permission', function () {
    $this->get(route('reports.trial-balance.index'))->assertForbidden();
});

it('allows trial balance with report-view-financial permission', function () {
    $this->user->givePermissionTo('report-view-financial');
    $this->get(route('reports.trial-balance.index'))->assertSuccessful();
});

it('denies account balances without report-view-financial permission', function () {
    $this->get(route('reports.account-balances.index'))->assertForbidden();
});

it('allows account balances with report-view-financial permission', function () {
    $this->user->givePermissionTo('report-view-financial');
    $this->get(route('reports.account-balances.index'))->assertSuccessful();
});

it('denies balance sheet without report-view-financial permission', function () {
    $this->get(route('reports.balance-sheet.index'))->assertForbidden();
});

it('allows balance sheet with report-view-financial permission', function () {
    $this->user->givePermissionTo('report-view-financial');
    $this->get(route('reports.balance-sheet.index'))->assertSuccessful();
});

it('denies income statement without report-view-financial permission', function () {
    $this->get(route('reports.income-statement.index'))->assertForbidden();
});

it('allows income statement with report-view-financial permission', function () {
    $this->user->givePermissionTo('report-view-financial');
    $this->get(route('reports.income-statement.index'))->assertSuccessful();
});
