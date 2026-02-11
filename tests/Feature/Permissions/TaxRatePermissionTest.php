<?php

use App\Models\TaxCode;
use App\Models\TaxRate;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['tax-list', 'tax-create', 'tax-edit', 'tax-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without tax-list permission', function () {
    $this->get(route('tax-rates.index'))->assertForbidden();
});

it('allows index with tax-list permission', function () {
    $this->user->givePermissionTo('tax-list');
    $this->get(route('tax-rates.index'))->assertSuccessful();
});

it('denies create without tax-create permission', function () {
    $this->get(route('tax-rates.create'))->assertForbidden();
});

it('allows create with tax-create permission', function () {
    $this->user->givePermissionTo('tax-create');
    $this->get(route('tax-rates.create'))->assertSuccessful();
});

it('denies store without tax-create permission', function () {
    $this->post(route('tax-rates.store'), [])->assertForbidden();
});

it('denies show without tax-list permission', function () {
    $taxCode = TaxCode::factory()->create();
    $taxRate = TaxRate::factory()->create(['tax_code_id' => $taxCode->id]);
    $this->get(route('tax-rates.show', $taxRate))->assertForbidden();
});

it('denies edit without tax-edit permission', function () {
    $taxCode = TaxCode::factory()->create();
    $taxRate = TaxRate::factory()->create(['tax_code_id' => $taxCode->id]);
    $this->get(route('tax-rates.edit', $taxRate))->assertForbidden();
});

it('denies update without tax-edit permission', function () {
    $taxCode = TaxCode::factory()->create();
    $taxRate = TaxRate::factory()->create(['tax_code_id' => $taxCode->id]);
    $this->put(route('tax-rates.update', $taxRate), [])->assertForbidden();
});

it('denies destroy without tax-delete permission', function () {
    $taxCode = TaxCode::factory()->create();
    $taxRate = TaxRate::factory()->create(['tax_code_id' => $taxCode->id]);
    $this->delete(route('tax-rates.destroy', $taxRate))->assertForbidden();
});
