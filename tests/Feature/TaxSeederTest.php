<?php

use App\Models\TaxCode;
use Database\Seeders\AccountTypeSeeder;
use Database\Seeders\ChartOfAccountSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\TaxCodeSeeder;
use Database\Seeders\TaxRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds unilever pakistan withholding tax metadata with an active 0.10 rate', function () {
    $this->seed(AccountTypeSeeder::class);
    $this->seed(CurrencySeeder::class);
    $this->seed(ChartOfAccountSeeder::class);
    $this->seed(TaxCodeSeeder::class);

    $this->seed(TaxRateSeeder::class);

    $taxCode = TaxCode::query()->where('tax_code', 'WHT-0.1')->first();

    expect($taxCode)->not->toBeNull();
    expect($taxCode->name)->toContain('Unilever Pakistan');
    expect($taxCode->description)->toContain('Unilever Pakistan');
    expect($taxCode->description)->toContain('only');

    $this->assertDatabaseHas('tax_rates', [
        'tax_code_id' => $taxCode->id,
        'rate' => 0.10,
        'effective_from' => '2024-01-01',
        'effective_to' => null,
        'is_active' => true,
    ]);
});
