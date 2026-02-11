<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['report-view-financial', 'report-view-inventory', 'report-view-sales', 'report-view-audit'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies reports index without any report permission', function () {
    $this->get(route('reports.index'))->assertForbidden();
});

it('allows reports index with report-view-financial permission', function () {
    $this->user->givePermissionTo('report-view-financial');
    $this->get(route('reports.index'))->assertSuccessful();
});

it('allows reports index with report-view-inventory permission', function () {
    $this->user->givePermissionTo('report-view-inventory');
    $this->get(route('reports.index'))->assertSuccessful();
});

it('allows reports index with report-view-sales permission', function () {
    $this->user->givePermissionTo('report-view-sales');
    $this->get(route('reports.index'))->assertSuccessful();
});

it('allows reports index with report-view-audit permission', function () {
    $this->user->givePermissionTo('report-view-audit');
    $this->get(route('reports.index'))->assertSuccessful();
});
