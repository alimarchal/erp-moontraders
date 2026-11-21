<?php

use App\Http\Requests\StoreSalesSettlementRequest;
use App\Http\Requests\UpdateSalesSettlementRequest;
use Illuminate\Support\Facades\Validator;

/**
 * A mistyped year or month (19-12 instead of 19-11) booked a settlement a month ahead, so its
 * sales, credit and GL sat in the future until it was reverted.
 */
it('refuses a settlement date in the future', function (string $requestClass) {
    $request = $requestClass::create('/', 'POST');
    $rules = ['settlement_date' => $request->rules()['settlement_date']];

    $future = Validator::make(['settlement_date' => now()->addDay()->toDateString()], $rules, $request->messages());
    $today = Validator::make(['settlement_date' => now()->toDateString()], $rules, $request->messages());

    expect($future->errors()->first('settlement_date'))->toBe('The settlement date cannot be in the future. Check the day, month and year.')
        ->and($today->passes())->toBeTrue();
})->with([
    'new settlement' => [StoreSalesSettlementRequest::class],
    'edited settlement' => [UpdateSalesSettlementRequest::class],
]);
