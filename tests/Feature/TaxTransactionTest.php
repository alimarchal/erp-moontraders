<?php

use App\Models\TaxCode;
use App\Models\TaxRate;
use App\Models\TaxTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('tax transactions index page can be rendered', function () {
    $response = $this->get(route('tax-transactions.index'));

    $response->assertStatus(200);
    $response->assertViewIs('settings.tax-transactions.index');
    $response->assertViewHas('transactions');
    $response->assertViewHas('taxCodes');
});

test('tax transactions index displays transactions', function () {
    $taxCode = TaxCode::factory()->create([
        'tax_code' => 'GST-18',
        'name' => 'GST @ 18%',
    ]);

    $taxRate = TaxRate::factory()->create([
        'tax_code_id' => $taxCode->id,
        'rate' => 18.00,
    ]);

    $transaction = TaxTransaction::factory()->create([
        'tax_code_id' => $taxCode->id,
        'tax_rate_id' => $taxRate->id,
        'taxable_amount' => 1000.00,
        'tax_rate' => 18.00,
        'tax_amount' => 180.00,
        'tax_direction' => 'payable',
    ]);

    $response = $this->get(route('tax-transactions.index'));

    $response->assertSee($taxCode->tax_code);
    $response->assertSee('1,000.00');
    $response->assertSee('180.00');
});

test('tax transaction show page can be rendered', function () {
    $taxCode = TaxCode::factory()->create();
    $taxRate = TaxRate::factory()->create(['tax_code_id' => $taxCode->id]);
    $transaction = TaxTransaction::factory()->create([
        'tax_code_id' => $taxCode->id,
        'tax_rate_id' => $taxRate->id,
    ]);

    $response = $this->get(route('tax-transactions.show', $transaction));

    $response->assertStatus(200);
    $response->assertViewIs('settings.tax-transactions.show');
    $response->assertViewHas('transaction');
});

test('tax transaction create route does not exist', function () {
    // The create route doesn't exist, so trying to access it will match the show route with ID 'create'
    // which will fail to find a transaction
    $response = $this->get('/settings/tax-transactions/create');

    // Expect 404 or redirect, not 200
    expect($response->status())->not->toBe(200);
});

test('tax transaction store returns 405', function () {
    $data = [
        'tax_code_id' => 1,
        'taxable_amount' => 1000.00,
        'tax_rate' => 18.00,
        'tax_amount' => 180.00,
    ];

    $response = $this->post('/settings/tax-transactions', $data);

    $response->assertStatus(405); // Method Not Allowed
});

test('tax transaction edit page returns 404', function () {
    $taxCode = TaxCode::factory()->create();
    $taxRate = TaxRate::factory()->create(['tax_code_id' => $taxCode->id]);
    $transaction = TaxTransaction::factory()->create([
        'tax_code_id' => $taxCode->id,
        'tax_rate_id' => $taxRate->id,
    ]);

    $response = $this->get("/settings/tax-transactions/{$transaction->id}/edit");

    $response->assertStatus(404);
});

test('tax transaction update returns 405', function () {
    $taxCode = TaxCode::factory()->create();
    $taxRate = TaxRate::factory()->create(['tax_code_id' => $taxCode->id]);
    $transaction = TaxTransaction::factory()->create([
        'tax_code_id' => $taxCode->id,
        'tax_rate_id' => $taxRate->id,
    ]);

    $data = [
        'tax_amount' => 200.00,
    ];

    $response = $this->put("/settings/tax-transactions/{$transaction->id}", $data);

    $response->assertStatus(405); // Method Not Allowed
});

test('tax transaction destroy returns 405', function () {
    $taxCode = TaxCode::factory()->create();
    $taxRate = TaxRate::factory()->create(['tax_code_id' => $taxCode->id]);
    $transaction = TaxTransaction::factory()->create([
        'tax_code_id' => $taxCode->id,
        'tax_rate_id' => $taxRate->id,
    ]);

    $response = $this->delete("/settings/tax-transactions/{$transaction->id}");

    $response->assertStatus(405); // Method Not Allowed
});

test('tax transactions can be filtered by tax code', function () {
    $taxCode1 = TaxCode::factory()->create(['tax_code' => 'GST-18']);
    $taxCode2 = TaxCode::factory()->create(['tax_code' => 'VAT-12']);
    $taxRate1 = TaxRate::factory()->create(['tax_code_id' => $taxCode1->id]);
    $taxRate2 = TaxRate::factory()->create(['tax_code_id' => $taxCode2->id]);

    TaxTransaction::factory()->create([
        'tax_code_id' => $taxCode1->id,
        'tax_rate_id' => $taxRate1->id,
    ]);
    TaxTransaction::factory()->create([
        'tax_code_id' => $taxCode2->id,
        'tax_rate_id' => $taxRate2->id,
    ]);

    $response = $this->get(route('tax-transactions.index', ['filter' => ['tax_code_id' => $taxCode1->id]]));

    $response->assertStatus(200);
    // Verify the filtered transaction exists
    expect($response->viewData('transactions'))->toHaveCount(1);
    expect($response->viewData('transactions')->first()->tax_code_id)->toBe($taxCode1->id);
});

test('tax transactions can be filtered by direction', function () {
    $taxCode = TaxCode::factory()->create();
    $taxRate = TaxRate::factory()->create(['tax_code_id' => $taxCode->id]);

    TaxTransaction::factory()->create([
        'tax_code_id' => $taxCode->id,
        'tax_rate_id' => $taxRate->id,
        'tax_direction' => 'payable',
    ]);
    TaxTransaction::factory()->create([
        'tax_code_id' => $taxCode->id,
        'tax_rate_id' => $taxRate->id,
        'tax_direction' => 'receivable',
    ]);

    $response = $this->get(route('tax-transactions.index', ['filter' => ['tax_direction' => 'payable']]));

    $response->assertStatus(200);
    $response->assertSee('Payable');
});

test('tax transaction show displays all details correctly', function () {
    $taxCode = TaxCode::factory()->create(['tax_code' => 'GST-18']);
    $taxRate = TaxRate::factory()->create([
        'tax_code_id' => $taxCode->id,
        'rate' => 18.00,
    ]);

    $transaction = TaxTransaction::factory()->create([
        'tax_code_id' => $taxCode->id,
        'tax_rate_id' => $taxRate->id,
        'taxable_amount' => 1000.00,
        'tax_rate' => 18.00,
        'tax_amount' => 180.00,
        'tax_direction' => 'payable',
        'transaction_date' => now(),
    ]);

    $response = $this->get(route('tax-transactions.show', $transaction));

    $response->assertSee($taxCode->tax_code);
    $response->assertSee('1,000.00');
    $response->assertSee('180.00');
    $response->assertSee('18.00%');
    $response->assertSee('Payable');
});
