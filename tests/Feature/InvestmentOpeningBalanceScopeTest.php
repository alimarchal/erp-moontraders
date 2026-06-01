<?php

use App\Models\InvestmentOpeningBalance;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'investment-opening-balance-list',
        'investment-opening-balance-create',
        'investment-opening-balance-edit',
        'investment-opening-balance-delete',
    ] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->supplier = Supplier::factory()->create([
        'supplier_name' => 'Scoped Supplier',
        'disabled' => false,
    ]);

    $this->otherSupplier = Supplier::factory()->create([
        'supplier_name' => 'Other Supplier',
        'disabled' => false,
    ]);

    $this->user = User::factory()->create([
        'supplier_id' => $this->supplier->id,
    ]);

    $this->user->givePermissionTo([
        'investment-opening-balance-list',
        'investment-opening-balance-create',
        'investment-opening-balance-edit',
        'investment-opening-balance-delete',
    ]);
});

it('only lists the authenticated users supplier balances for scoped users', function () {
    InvestmentOpeningBalance::create([
        'supplier_id' => $this->supplier->id,
        'date' => '2026-05-01',
        'description' => 'BANK_OPENING_AMOUNT',
        'amount' => 1000,
    ]);

    InvestmentOpeningBalance::create([
        'supplier_id' => $this->otherSupplier->id,
        'date' => '2026-05-01',
        'description' => 'OTHER_SUPPLIER_AMOUNT',
        'amount' => 2000,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('investment-opening-balances.index'));

    $response->assertOk()
        ->assertSee('Scoped Supplier')
        ->assertSee('1,000.00')
        ->assertDontSee('Other Supplier')
        ->assertDontSee('2,000.00');

    expect($response->viewData('suppliers'))->toHaveCount(1);
});

it('blocks scoped users from creating an opening balance for another supplier', function () {
    $this->actingAs($this->user)
        ->post(route('investment-opening-balances.store'), [
            'supplier_id' => $this->otherSupplier->id,
            'date' => '2026-05-01',
            'description' => 'BANK_OPENING_AMOUNT',
            'amount' => 1000,
        ])
        ->assertForbidden();
});

it('shows all supplier options to super admins without selecting a default supplier', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => 'Yes']);
    $superAdmin->givePermissionTo('investment-opening-balance-create');

    $response = $this->actingAs($superAdmin)
        ->get(route('investment-opening-balances.create'));

    $response->assertOk()
        ->assertSee('Scoped Supplier')
        ->assertSee('Other Supplier');

    expect($response->viewData('suppliers'))->toHaveCount(2);
});
