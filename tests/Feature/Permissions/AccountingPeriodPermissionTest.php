<?php

use App\Models\AccountingPeriod;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['accounting-period-list', 'accounting-period-create', 'accounting-period-edit', 'accounting-period-delete', 'accounting-period-close', 'accounting-period-open'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without accounting-period-list permission', function () {
    $this->get(route('accounting-periods.index'))->assertForbidden();
});

it('allows index with accounting-period-list permission', function () {
    $this->user->givePermissionTo('accounting-period-list');
    $this->get(route('accounting-periods.index'))->assertSuccessful();
});

it('denies create without accounting-period-create permission', function () {
    $this->get(route('accounting-periods.create'))->assertForbidden();
});

it('allows create with accounting-period-create permission', function () {
    $this->user->givePermissionTo('accounting-period-create');
    $this->get(route('accounting-periods.create'))->assertSuccessful();
});

it('denies store without accounting-period-create permission', function () {
    $this->post(route('accounting-periods.store'), [])->assertForbidden();
});

it('denies show without accounting-period-list permission', function () {
    $period = AccountingPeriod::factory()->create();
    $this->get(route('accounting-periods.show', $period))->assertForbidden();
});

it('denies edit without accounting-period-edit permission', function () {
    $period = AccountingPeriod::factory()->create();
    $this->get(route('accounting-periods.edit', $period))->assertForbidden();
});

it('denies update without accounting-period-edit permission', function () {
    $period = AccountingPeriod::factory()->create();
    $this->put(route('accounting-periods.update', $period), [])->assertForbidden();
});

it('denies destroy without accounting-period-delete permission', function () {
    $period = AccountingPeriod::factory()->create();
    $this->delete(route('accounting-periods.destroy', $period))->assertForbidden();
});
