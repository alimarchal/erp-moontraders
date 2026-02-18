<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'report-audit-cash-detail',
        'report-audit-custom-settlement',
        'report-audit-creditors-ledger',
        'report-audit-claim-register',
        'report-audit-advance-tax',
        'report-audit-percentage-expense',
    ] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies creditors ledger without report-audit-creditors-ledger permission', function () {
    $this->get(route('reports.creditors-ledger.index'))->assertForbidden();
});

it('allows creditors ledger with report-audit-creditors-ledger permission', function () {
    $this->user->givePermissionTo('report-audit-creditors-ledger');
    $this->get(route('reports.creditors-ledger.index'))->assertSuccessful();
});

it('denies creditors salesman-creditors without report-audit-creditors-ledger permission', function () {
    $this->get(route('reports.creditors-ledger.salesman-creditors'))->assertForbidden();
});

it('allows creditors salesman-creditors with report-audit-creditors-ledger permission', function () {
    $this->user->givePermissionTo('report-audit-creditors-ledger');
    $response = $this->get(route('reports.creditors-ledger.salesman-creditors'));
    expect($response->status())->not->toBe(403);
});

it('denies creditors aging report without report-audit-creditors-ledger permission', function () {
    $this->get(route('reports.creditors-ledger.aging-report'))->assertForbidden();
});

it('allows creditors aging report with report-audit-creditors-ledger permission', function () {
    $this->user->givePermissionTo('report-audit-creditors-ledger');
    $response = $this->get(route('reports.creditors-ledger.aging-report'));
    expect($response->status())->not->toBe(403);
});

it('denies cash detail without report-audit-cash-detail permission', function () {
    $this->get(route('reports.cash-detail.index'))->assertForbidden();
});

it('allows cash detail with report-audit-cash-detail permission', function () {
    $this->user->givePermissionTo('report-audit-cash-detail');
    $this->get(route('reports.cash-detail.index'))->assertSuccessful();
});

it('denies percentage expense without report-audit-percentage-expense permission', function () {
    $this->get(route('reports.percentage-expense.index'))->assertForbidden();
});

it('allows percentage expense with report-audit-percentage-expense permission', function () {
    $this->user->givePermissionTo('report-audit-percentage-expense');
    $this->get(route('reports.percentage-expense.index'))->assertSuccessful();
});

it('denies advance tax without report-audit-advance-tax permission', function () {
    $this->get(route('reports.advance-tax.index'))->assertForbidden();
});

it('allows advance tax with report-audit-advance-tax permission', function () {
    $this->user->givePermissionTo('report-audit-advance-tax');
    $this->get(route('reports.advance-tax.index'))->assertSuccessful();
});

it('denies custom settlement without report-audit-custom-settlement permission', function () {
    $this->get(route('reports.custom-settlement.index'))->assertForbidden();
});

it('allows custom settlement with report-audit-custom-settlement permission', function () {
    $this->user->givePermissionTo('report-audit-custom-settlement');
    $this->get(route('reports.custom-settlement.index'))->assertSuccessful();
});

it('denies claim register without report-audit-claim-register permission', function () {
    $this->get(route('reports.claim-register.index'))->assertForbidden();
});

it('allows claim register with report-audit-claim-register permission', function () {
    $this->user->givePermissionTo('report-audit-claim-register');
    $this->get(route('reports.claim-register.index'))->assertSuccessful();
});
