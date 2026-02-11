<?php

use App\Models\User;
use App\Models\Vehicle;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'report-view-inventory']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-view-inventory');
});

test('van stock ledger index page loads for authenticated user', function () {
    $this->actingAs($this->user)
        ->get(route('reports.van-stock-ledger.index'))
        ->assertSuccessful();
});

test('van stock ledger summary page loads for authenticated user', function () {
    $this->actingAs($this->user)
        ->get(route('reports.van-stock-ledger.summary'))
        ->assertSuccessful();
});

test('van stock ledger vehicle page loads for authenticated user', function () {
    $vehicle = Vehicle::factory()->create();

    $this->actingAs($this->user)
        ->get(route('reports.van-stock-ledger.vehicle-ledger', $vehicle))
        ->assertSuccessful();
});

test('van stock ledger requires authentication', function () {
    $this->get(route('reports.van-stock-ledger.index'))
        ->assertRedirect(route('login'));
});
