<?php

use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('report-sales-summary-roi');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-sales-summary-roi');
});

test('summary roi report sets fixed incentive and expiry claimed values for engro supplier', function () {
    $engroSupplier = Supplier::factory()->create([
        'supplier_name' => 'Engro Foods',
        'short_name' => 'Engro',
        'disabled' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.summary-roi.index', [
            'filter' => [
                'supplier_id' => $engroSupplier->id,
            ],
        ]));

    $response->assertSuccessful()
        ->assertViewHas('incentiveClaimed', 208652.0)
        ->assertViewHas('expiryClaimed', 260000.0)
        ->assertSee('Incentive Claimed')
        ->assertSee('Expiry Claimed')
        ->assertSee('208,652.00')
        ->assertSee('260,000.00');
});

test('summary roi report sets incentive and expiry claimed as zero for non engro supplier', function () {
    $otherSupplier = Supplier::factory()->create([
        'supplier_name' => 'Nestle Pakistan',
        'short_name' => 'Nestle',
        'disabled' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.summary-roi.index', [
            'filter' => [
                'supplier_id' => $otherSupplier->id,
            ],
        ]));

    $response->assertSuccessful()
        ->assertViewHas('incentiveClaimed', 0.0)
        ->assertViewHas('expiryClaimed', 0.0)
        ->assertSee('Incentive Claimed')
        ->assertSee('Expiry Claimed')
        ->assertSee('0.00');
});
