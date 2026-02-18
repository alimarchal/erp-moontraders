<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'report-sales-daily-sales',
        'report-sales-credit-sales',
        'report-sales-fmr-amr-comparison',
        'report-sales-settlement',
        'report-sales-goods-issue',
        'report-sales-roi',
        'report-sales-scheme-discount',
        'report-sales-shop-list',
        'report-sales-sku-rates',
    ] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies daily sales index without report-sales-daily-sales permission', function () {
    $this->get(route('reports.daily-sales.index'))->assertForbidden();
});

it('allows daily sales index with report-sales-daily-sales permission', function () {
    $this->user->givePermissionTo('report-sales-daily-sales');
    $this->get(route('reports.daily-sales.index'))->assertSuccessful();
});

it('denies daily sales product-wise without report-sales-daily-sales permission', function () {
    $this->get(route('reports.daily-sales.product-wise'))->assertForbidden();
});

it('allows daily sales product-wise with report-sales-daily-sales permission', function () {
    $this->user->givePermissionTo('report-sales-daily-sales');
    $this->get(route('reports.daily-sales.product-wise'))->assertSuccessful();
});

it('denies daily sales salesman-wise without report-sales-daily-sales permission', function () {
    $this->get(route('reports.daily-sales.salesman-wise'))->assertForbidden();
});

it('allows daily sales salesman-wise with report-sales-daily-sales permission', function () {
    $this->user->givePermissionTo('report-sales-daily-sales');
    $this->get(route('reports.daily-sales.salesman-wise'))->assertSuccessful();
});

it('denies daily sales van-stock without report-sales-daily-sales permission', function () {
    $this->get(route('reports.daily-sales.van-stock'))->assertForbidden();
});

it('allows daily sales van-stock with report-sales-daily-sales permission', function () {
    $this->user->givePermissionTo('report-sales-daily-sales');
    $this->get(route('reports.daily-sales.van-stock'))->assertSuccessful();
});

it('denies sales settlement report without report-sales-settlement permission', function () {
    $this->get(route('reports.sales-settlement.index'))->assertForbidden();
});

it('allows sales settlement report with report-sales-settlement permission', function () {
    $this->user->givePermissionTo('report-sales-settlement');
    $this->get(route('reports.sales-settlement.index'))->assertSuccessful();
});

it('denies goods issue report without report-sales-goods-issue permission', function () {
    $this->get(route('reports.goods-issue.index'))->assertForbidden();
});

it('allows goods issue report with report-sales-goods-issue permission', function () {
    $this->user->givePermissionTo('report-sales-goods-issue');
    $this->get(route('reports.goods-issue.index'))->assertSuccessful();
});

it('denies fmr amr comparison without report-sales-fmr-amr-comparison permission', function () {
    $this->get(route('reports.fmr-amr-comparison.index'))->assertForbidden();
});

it('allows fmr amr comparison with report-sales-fmr-amr-comparison permission', function () {
    $this->user->givePermissionTo('report-sales-fmr-amr-comparison');
    $this->get(route('reports.fmr-amr-comparison.index'))->assertSuccessful();
});

it('denies shop list without report-sales-shop-list permission', function () {
    $this->get(route('reports.shop-list.index'))->assertForbidden();
});

it('allows shop list with report-sales-shop-list permission', function () {
    $this->user->givePermissionTo('report-sales-shop-list');
    $this->get(route('reports.shop-list.index'))->assertSuccessful();
});

it('denies sku rates without report-sales-sku-rates permission', function () {
    $this->get(route('reports.sku-rates.index'))->assertForbidden();
});

it('allows sku rates with report-sales-sku-rates permission', function () {
    $this->user->givePermissionTo('report-sales-sku-rates');
    $this->get(route('reports.sku-rates.index'))->assertSuccessful();
});

it('denies roi report without report-sales-roi permission', function () {
    $this->get(route('reports.roi.index'))->assertForbidden();
});

it('allows roi report with report-sales-roi permission', function () {
    $this->user->givePermissionTo('report-sales-roi');
    $this->get(route('reports.roi.index'))->assertSuccessful();
});

it('denies scheme discount report without report-sales-scheme-discount permission', function () {
    $this->get(route('reports.scheme-discount.index'))->assertForbidden();
});

it('allows scheme discount report with report-sales-scheme-discount permission', function () {
    $this->user->givePermissionTo('report-sales-scheme-discount');
    $this->get(route('reports.scheme-discount.index'))->assertSuccessful();
});

it('denies credit sales customer-history without report-sales-credit-sales permission', function () {
    $this->get(route('reports.credit-sales.customer-history'))->assertForbidden();
});

it('allows credit sales customer-history with report-sales-credit-sales permission', function () {
    $this->user->givePermissionTo('report-sales-credit-sales');
    $this->get(route('reports.credit-sales.customer-history'))->assertSuccessful();
});

it('denies credit sales salesman-history without report-sales-credit-sales permission', function () {
    $this->get(route('reports.credit-sales.salesman-history'))->assertForbidden();
});

it('allows credit sales salesman-history with report-sales-credit-sales permission', function () {
    $this->user->givePermissionTo('report-sales-credit-sales');
    $this->get(route('reports.credit-sales.salesman-history'))->assertSuccessful();
});
