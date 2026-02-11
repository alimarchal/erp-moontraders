<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'report-view-sales']);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies daily sales index without report-view-sales permission', function () {
    $this->get(route('reports.daily-sales.index'))->assertForbidden();
});

it('allows daily sales index with report-view-sales permission', function () {
    $this->user->givePermissionTo('report-view-sales');
    $this->get(route('reports.daily-sales.index'))->assertSuccessful();
});

it('denies daily sales product-wise without report-view-sales permission', function () {
    $this->get(route('reports.daily-sales.product-wise'))->assertForbidden();
});

it('allows daily sales product-wise with report-view-sales permission', function () {
    $this->user->givePermissionTo('report-view-sales');
    $this->get(route('reports.daily-sales.product-wise'))->assertSuccessful();
});

it('denies daily sales salesman-wise without report-view-sales permission', function () {
    $this->get(route('reports.daily-sales.salesman-wise'))->assertForbidden();
});

it('allows daily sales salesman-wise with report-view-sales permission', function () {
    $this->user->givePermissionTo('report-view-sales');
    $this->get(route('reports.daily-sales.salesman-wise'))->assertSuccessful();
});

it('denies daily sales van-stock without report-view-sales permission', function () {
    $this->get(route('reports.daily-sales.van-stock'))->assertForbidden();
});

it('allows daily sales van-stock with report-view-sales permission', function () {
    $this->user->givePermissionTo('report-view-sales');
    $this->get(route('reports.daily-sales.van-stock'))->assertSuccessful();
});

it('denies sales settlement report without report-view-sales permission', function () {
    $this->get(route('reports.sales-settlement.index'))->assertForbidden();
});

it('allows sales settlement report with report-view-sales permission', function () {
    $this->user->givePermissionTo('report-view-sales');
    $this->get(route('reports.sales-settlement.index'))->assertSuccessful();
});

it('denies goods issue report without report-view-sales permission', function () {
    $this->get(route('reports.goods-issue.index'))->assertForbidden();
});

it('allows goods issue report with report-view-sales permission', function () {
    $this->user->givePermissionTo('report-view-sales');
    $this->get(route('reports.goods-issue.index'))->assertSuccessful();
});

it('denies fmr amr comparison without report-view-sales permission', function () {
    $this->get(route('reports.fmr-amr-comparison.index'))->assertForbidden();
});

it('allows fmr amr comparison with report-view-sales permission', function () {
    $this->user->givePermissionTo('report-view-sales');
    $this->get(route('reports.fmr-amr-comparison.index'))->assertSuccessful();
});

it('denies shop list without report-view-sales permission', function () {
    $this->get(route('reports.shop-list.index'))->assertForbidden();
});

it('allows shop list with report-view-sales permission', function () {
    $this->user->givePermissionTo('report-view-sales');
    $this->get(route('reports.shop-list.index'))->assertSuccessful();
});

it('denies sku rates without report-view-sales permission', function () {
    $this->get(route('reports.sku-rates.index'))->assertForbidden();
});

it('allows sku rates with report-view-sales permission', function () {
    $this->user->givePermissionTo('report-view-sales');
    $this->get(route('reports.sku-rates.index'))->assertSuccessful();
});

it('denies roi report without report-view-sales permission', function () {
    $this->get(route('reports.roi.index'))->assertForbidden();
});

it('allows roi report with report-view-sales permission', function () {
    $this->user->givePermissionTo('report-view-sales');
    $this->get(route('reports.roi.index'))->assertSuccessful();
});

it('denies scheme discount report without report-view-sales permission', function () {
    $this->get(route('reports.scheme-discount.index'))->assertForbidden();
});

it('allows scheme discount report with report-view-sales permission', function () {
    $this->user->givePermissionTo('report-view-sales');
    $this->get(route('reports.scheme-discount.index'))->assertSuccessful();
});

it('denies credit sales customer-history without report-view-sales permission', function () {
    $this->get(route('reports.credit-sales.customer-history'))->assertForbidden();
});

it('allows credit sales customer-history with report-view-sales permission', function () {
    $this->user->givePermissionTo('report-view-sales');
    $this->get(route('reports.credit-sales.customer-history'))->assertSuccessful();
});

it('denies credit sales salesman-history without report-view-sales permission', function () {
    $this->get(route('reports.credit-sales.salesman-history'))->assertForbidden();
});

it('allows credit sales salesman-history with report-view-sales permission', function () {
    $this->user->givePermissionTo('report-view-sales');
    $this->get(route('reports.credit-sales.salesman-history'))->assertSuccessful();
});
