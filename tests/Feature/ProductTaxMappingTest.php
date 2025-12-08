<?php

use App\Models\Product;
use App\Models\ProductTaxMapping;
use App\Models\TaxCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('product tax mappings index page can be rendered', function () {
    $response = $this->get(route('product-tax-mappings.index'));

    $response->assertStatus(200);
    $response->assertViewIs('settings.product-tax-mappings.index');
    $response->assertViewHas('mappings');
    $response->assertViewHas('products');
    $response->assertViewHas('taxCodes');
});

test('product tax mappings index displays mappings', function () {
    $product = Product::factory()->create([
        'product_code' => 'PROD-001',
        'product_name' => 'Test Product',
    ]);

    $taxCode = TaxCode::factory()->create([
        'tax_code' => 'GST-18',
        'name' => 'GST @ 18%',
    ]);

    $mapping = ProductTaxMapping::factory()->create([
        'product_id' => $product->id,
        'tax_code_id' => $taxCode->id,
        'transaction_type' => 'sales',
    ]);

    $response = $this->get(route('product-tax-mappings.index'));

    $response->assertSee($product->product_code);
    $response->assertSee($taxCode->tax_code);
    $response->assertSee('Sales');
});

test('product tax mapping create page can be rendered', function () {
    $response = $this->get(route('product-tax-mappings.create'));

    $response->assertStatus(200);
    $response->assertViewIs('settings.product-tax-mappings.create');
    $response->assertViewHas('products');
    $response->assertViewHas('taxCodes');
});

test('product tax mapping can be created', function () {
    $product = Product::factory()->create();
    $taxCode = TaxCode::factory()->create();

    $data = [
        'product_id' => $product->id,
        'tax_code_id' => $taxCode->id,
        'transaction_type' => 'sales',
        'is_active' => true,
    ];

    $response = $this->post(route('product-tax-mappings.store'), $data);

    $response->assertRedirect(route('product-tax-mappings.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('product_tax_mappings', [
        'product_id' => $product->id,
        'tax_code_id' => $taxCode->id,
        'transaction_type' => 'sales',
    ]);
});

test('product tax mapping show page can be rendered', function () {
    $product = Product::factory()->create();
    $taxCode = TaxCode::factory()->create();
    $mapping = ProductTaxMapping::factory()->create([
        'product_id' => $product->id,
        'tax_code_id' => $taxCode->id,
    ]);

    $response = $this->get(route('product-tax-mappings.show', $mapping));

    $response->assertStatus(200);
    $response->assertViewIs('settings.product-tax-mappings.show');
    $response->assertViewHas('mapping');
});

test('product tax mapping edit page can be rendered', function () {
    $product = Product::factory()->create();
    $taxCode = TaxCode::factory()->create();
    $mapping = ProductTaxMapping::factory()->create([
        'product_id' => $product->id,
        'tax_code_id' => $taxCode->id,
    ]);

    $response = $this->get(route('product-tax-mappings.edit', $mapping));

    $response->assertStatus(200);
    $response->assertViewIs('settings.product-tax-mappings.edit');
    $response->assertViewHas('mapping');
    $response->assertViewHas('products');
    $response->assertViewHas('taxCodes');
});

test('product tax mapping can be updated', function () {
    $product = Product::factory()->create();
    $taxCode = TaxCode::factory()->create();
    $mapping = ProductTaxMapping::factory()->create([
        'product_id' => $product->id,
        'tax_code_id' => $taxCode->id,
        'transaction_type' => 'sales',
    ]);

    $data = [
        'product_id' => $product->id,
        'tax_code_id' => $taxCode->id,
        'transaction_type' => 'both',
        'is_active' => true,
    ];

    $response = $this->put(route('product-tax-mappings.update', $mapping), $data);

    $response->assertRedirect(route('product-tax-mappings.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('product_tax_mappings', [
        'id' => $mapping->id,
        'transaction_type' => 'both',
    ]);
});

test('product tax mapping can be deleted', function () {
    $product = Product::factory()->create();
    $taxCode = TaxCode::factory()->create();
    $mapping = ProductTaxMapping::factory()->create([
        'product_id' => $product->id,
        'tax_code_id' => $taxCode->id,
    ]);

    $response = $this->delete(route('product-tax-mappings.destroy', $mapping));

    $response->assertRedirect(route('product-tax-mappings.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('product_tax_mappings', [
        'id' => $mapping->id,
    ]);
});

test('product tax mapping requires valid product', function () {
    $taxCode = TaxCode::factory()->create();

    $data = [
        'product_id' => 9999,
        'tax_code_id' => $taxCode->id,
        'transaction_type' => 'sales',
        'is_active' => true,
    ];

    $response = $this->post(route('product-tax-mappings.store'), $data);

    $response->assertSessionHasErrors('product_id');
});

test('product tax mapping requires valid tax code', function () {
    $product = Product::factory()->create();

    $data = [
        'product_id' => $product->id,
        'tax_code_id' => 9999,
        'transaction_type' => 'sales',
        'is_active' => true,
    ];

    $response = $this->post(route('product-tax-mappings.store'), $data);

    $response->assertSessionHasErrors('tax_code_id');
});

test('product tax mapping can be filtered by product', function () {
    $product1 = Product::factory()->create(['product_code' => 'PROD-001']);
    $product2 = Product::factory()->create(['product_code' => 'PROD-002']);
    $taxCode = TaxCode::factory()->create();

    ProductTaxMapping::factory()->create([
        'product_id' => $product1->id,
        'tax_code_id' => $taxCode->id,
    ]);
    ProductTaxMapping::factory()->create([
        'product_id' => $product2->id,
        'tax_code_id' => $taxCode->id,
    ]);

    $response = $this->get(route('product-tax-mappings.index', ['filter' => ['product_id' => $product1->id]]));

    $response->assertStatus(200);
    // Verify the filtered mappings
    expect($response->viewData('mappings'))->toHaveCount(1);
    expect($response->viewData('mappings')->first()->product_id)->toBe($product1->id);
});

test('product tax mapping can be filtered by transaction type', function () {
    $product = Product::factory()->create();
    $taxCode = TaxCode::factory()->create();

    ProductTaxMapping::factory()->create([
        'product_id' => $product->id,
        'tax_code_id' => $taxCode->id,
        'transaction_type' => 'sales',
    ]);
    ProductTaxMapping::factory()->create([
        'product_id' => $product->id,
        'tax_code_id' => $taxCode->id,
        'transaction_type' => 'purchase',
    ]);

    $response = $this->get(route('product-tax-mappings.index', ['filter' => ['transaction_type' => 'sales']]));

    $response->assertStatus(200);
    $response->assertSee('Sales');
});
