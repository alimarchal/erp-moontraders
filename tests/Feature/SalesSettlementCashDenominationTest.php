<?php

use App\Models\SalesSettlement;
use App\Models\SalesSettlementCashDenomination;

test('cash denomination relationship works', function () {
    // Create a settlement
    $settlement = SalesSettlement::factory()->create();

    // Create cash denomination
    $denomination = SalesSettlementCashDenomination::create([
        'sales_settlement_id' => $settlement->id,
        'denom_5000' => 2,
        'denom_1000' => 5,
        'denom_500' => 10,
        'denom_100' => 20,
        'denom_50' => 5,
        'denom_20' => 10,
        'denom_10' => 5,
        'denom_coins' => 125.50,
    ]);

    // Test relationship
    expect($settlement->cashDenominations()->count())->toBe(1);
    expect($settlement->cashDenominations->first()->denom_5000)->toBe(2);

    // Test total amount calculation
    $expectedTotal = (2 * 5000) + (5 * 1000) + (10 * 500) + (20 * 100) + (5 * 50) + (10 * 20) + (5 * 10) + 125.50;
    expect($settlement->cashDenominations->first()->total_amount)->toBe((float) $expectedTotal);

    // Test accessor on settlement
    expect($settlement->total_cash_denomination_amount)->toBe((float) $expectedTotal);
});
