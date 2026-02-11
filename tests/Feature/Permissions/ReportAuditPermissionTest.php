<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'report-view-audit']);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies creditors ledger without report-view-audit permission', function () {
    $this->get(route('reports.creditors-ledger.index'))->assertForbidden();
});

it('allows creditors ledger with report-view-audit permission', function () {
    $this->user->givePermissionTo('report-view-audit');
    $this->get(route('reports.creditors-ledger.index'))->assertSuccessful();
});

it('denies creditors salesman-creditors without report-view-audit permission', function () {
    $this->get(route('reports.creditors-ledger.salesman-creditors'))->assertForbidden();
});

it('allows creditors salesman-creditors with report-view-audit permission', function () {
    $this->user->givePermissionTo('report-view-audit');
    $response = $this->get(route('reports.creditors-ledger.salesman-creditors'));
    expect($response->status())->not->toBe(403);
});

it('denies creditors aging report without report-view-audit permission', function () {
    $this->get(route('reports.creditors-ledger.aging-report'))->assertForbidden();
});

it('allows creditors aging report with report-view-audit permission', function () {
    $this->user->givePermissionTo('report-view-audit');
    $response = $this->get(route('reports.creditors-ledger.aging-report'));
    expect($response->status())->not->toBe(403);
});

it('denies cash detail without report-view-audit permission', function () {
    $this->get(route('reports.cash-detail.index'))->assertForbidden();
});

it('allows cash detail with report-view-audit permission', function () {
    $this->user->givePermissionTo('report-view-audit');
    $this->get(route('reports.cash-detail.index'))->assertSuccessful();
});

it('denies percentage expense without report-view-audit permission', function () {
    $this->get(route('reports.percentage-expense.index'))->assertForbidden();
});

it('allows percentage expense with report-view-audit permission', function () {
    $this->user->givePermissionTo('report-view-audit');
    $this->get(route('reports.percentage-expense.index'))->assertSuccessful();
});

it('denies advance tax without report-view-audit permission', function () {
    $this->get(route('reports.advance-tax.index'))->assertForbidden();
});

it('allows advance tax with report-view-audit permission', function () {
    $this->user->givePermissionTo('report-view-audit');
    $this->get(route('reports.advance-tax.index'))->assertSuccessful();
});

it('denies custom settlement without report-view-audit permission', function () {
    $this->get(route('reports.custom-settlement.index'))->assertForbidden();
});

it('allows custom settlement with report-view-audit permission', function () {
    $this->user->givePermissionTo('report-view-audit');
    $this->get(route('reports.custom-settlement.index'))->assertSuccessful();
});
