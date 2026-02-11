<?php

use App\Models\TaxCode;
use App\Models\TaxRate;
use App\Models\TaxTransaction;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'tax-list']);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without tax-list permission', function () {
    $this->get(route('tax-transactions.index'))->assertForbidden();
});

it('allows index with tax-list permission', function () {
    $this->user->givePermissionTo('tax-list');
    $this->get(route('tax-transactions.index'))->assertSuccessful();
});

it('denies show without tax-list permission', function () {
    $taxCode = TaxCode::factory()->create();
    $taxRate = TaxRate::factory()->create(['tax_code_id' => $taxCode->id]);
    $transaction = TaxTransaction::factory()->create([
        'tax_code_id' => $taxCode->id,
        'tax_rate_id' => $taxRate->id,
    ]);
    $this->get(route('tax-transactions.show', $transaction))->assertForbidden();
});

it('allows show with tax-list permission', function () {
    $this->user->givePermissionTo('tax-list');
    $taxCode = TaxCode::factory()->create();
    $taxRate = TaxRate::factory()->create(['tax_code_id' => $taxCode->id]);
    $transaction = TaxTransaction::factory()->create([
        'tax_code_id' => $taxCode->id,
        'tax_rate_id' => $taxRate->id,
    ]);
    $this->get(route('tax-transactions.show', $transaction))->assertSuccessful();
});
