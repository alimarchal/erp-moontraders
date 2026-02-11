<?php

use App\Models\TaxCode;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

    foreach (['tax-list', 'tax-create', 'tax-edit', 'tax-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['tax-list', 'tax-create', 'tax-edit', 'tax-delete']);
    $this->actingAs($this->user);
});

test('tax rates index page can be rendered', function () {
    $response = $this->get(route('tax-rates.index'));

    $response->assertStatus(200);
    $response->assertViewIs('settings.tax-rates.index');
    $response->assertViewHas('taxRates');
    $response->assertViewHas('taxCodes');
});

test('tax rates index displays tax rates', function () {
    $taxCode = TaxCode::factory()->create([
        'tax_code' => 'GST-18',
        'name' => 'GST @ 18%',
    ]);

    $taxRate = TaxRate::factory()->create([
        'tax_code_id' => $taxCode->id,
        'rate' => 18.00,
        'effective_from' => now()->subDays(30),
        'is_active' => true,
    ]);

    $response = $this->get(route('tax-rates.index'));

    $response->assertSee($taxCode->tax_code);
    $response->assertSee('18.00');
});

test('tax rate create page can be rendered', function () {
    $response = $this->get(route('tax-rates.create'));

    $response->assertStatus(200);
    $response->assertViewIs('settings.tax-rates.create');
    $response->assertViewHas('taxCodes');
});

test('tax rate can be created', function () {
    $taxCode = TaxCode::factory()->create();

    $data = [
        'tax_code_id' => $taxCode->id,
        'rate' => 18.00,
        'effective_from' => now()->format('Y-m-d'),
        'effective_to' => null,
        'region' => null,
        'is_active' => true,
    ];

    $response = $this->post(route('tax-rates.store'), $data);

    $response->assertRedirect(route('tax-rates.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('tax_rates', [
        'tax_code_id' => $taxCode->id,
        'rate' => 18.00,
        'is_active' => true,
    ]);
});

test('tax rate show page can be rendered', function () {
    $taxCode = TaxCode::factory()->create();
    $taxRate = TaxRate::factory()->create(['tax_code_id' => $taxCode->id]);

    $response = $this->get(route('tax-rates.show', $taxRate));

    $response->assertStatus(200);
    $response->assertViewIs('settings.tax-rates.show');
    $response->assertViewHas('taxRate');
    $response->assertSee(number_format($taxRate->rate, 2));
});

test('tax rate edit page can be rendered', function () {
    $taxCode = TaxCode::factory()->create();
    $taxRate = TaxRate::factory()->create(['tax_code_id' => $taxCode->id]);

    $response = $this->get(route('tax-rates.edit', $taxRate));

    $response->assertStatus(200);
    $response->assertViewIs('settings.tax-rates.edit');
    $response->assertViewHas('taxRate');
    $response->assertViewHas('taxCodes');
});

test('tax rate can be updated', function () {
    $taxCode = TaxCode::factory()->create();
    $taxRate = TaxRate::factory()->create([
        'tax_code_id' => $taxCode->id,
        'rate' => 18.00,
    ]);

    $data = [
        'tax_code_id' => $taxCode->id,
        'rate' => 12.00,
        'effective_from' => now()->format('Y-m-d'),
        'effective_to' => null,
        'region' => 'Region A',
        'is_active' => true,
    ];

    $response = $this->put(route('tax-rates.update', $taxRate), $data);

    $response->assertRedirect(route('tax-rates.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('tax_rates', [
        'id' => $taxRate->id,
        'rate' => 12.00,
        'region' => 'Region A',
    ]);
});

test('tax rate can be deleted', function () {
    $taxCode = TaxCode::factory()->create();
    $taxRate = TaxRate::factory()->create(['tax_code_id' => $taxCode->id]);

    $response = $this->delete(route('tax-rates.destroy', $taxRate));

    $response->assertRedirect(route('tax-rates.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('tax_rates', [
        'id' => $taxRate->id,
    ]);
});

test('tax rate requires valid tax code', function () {
    $data = [
        'tax_code_id' => 9999,
        'rate' => 18.00,
        'effective_from' => now()->format('Y-m-d'),
        'is_active' => true,
    ];

    $response = $this->post(route('tax-rates.store'), $data);

    $response->assertSessionHasErrors('tax_code_id');
});

test('tax rate requires rate field', function () {
    $taxCode = TaxCode::factory()->create();

    $data = [
        'tax_code_id' => $taxCode->id,
        'effective_from' => now()->format('Y-m-d'),
        'is_active' => true,
    ];

    $response = $this->post(route('tax-rates.store'), $data);

    $response->assertSessionHasErrors('rate');
});

test('tax rate can be filtered by tax code', function () {
    $taxCode1 = TaxCode::factory()->create(['tax_code' => 'GST-18']);
    $taxCode2 = TaxCode::factory()->create(['tax_code' => 'VAT-12']);

    TaxRate::factory()->create(['tax_code_id' => $taxCode1->id, 'rate' => 18]);
    TaxRate::factory()->create(['tax_code_id' => $taxCode2->id, 'rate' => 12]);

    $response = $this->get(route('tax-rates.index', ['filter' => ['tax_code_id' => $taxCode1->id]]));

    $response->assertStatus(200);
    // Verify the filtered tax rates
    expect($response->viewData('taxRates'))->toHaveCount(1);
    expect($response->viewData('taxRates')->first()->tax_code_id)->toBe($taxCode1->id);
});
