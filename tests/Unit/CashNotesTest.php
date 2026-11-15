<?php

use App\Support\CashNotes;

it('totals the notes and coins, treating negative values as zero', function () {
    $notes = CashNotes::fromInput(['5000' => 2, '100' => '3', 'coins' => '5.5', '20' => -4, '10' => 'abc']);

    expect($notes->total())->toBe(10305.5)
        ->and($notes->count(5000))->toBe(2)
        ->and($notes->count(20))->toBe(0)
        ->and($notes->count(10))->toBe(0)
        ->and($notes->coins())->toBe(5.5)
        ->and($notes->isEmpty())->toBeFalse();
});

it('is empty when nothing was counted', function () {
    expect(CashNotes::fromInput([])->isEmpty())->toBeTrue();
});

it('exposes the counts as prefixed columns', function () {
    $columns = CashNotes::fromInput(['5000' => 2, 'coins' => 12.5])->columns('cleared_denom_');

    expect($columns)->toHaveKey('cleared_denom_5000', 2)
        ->and($columns['cleared_denom_10'])->toBe(0)
        ->and($columns['cleared_denom_coins'])->toBe(12.5);
});

it('round-trips through columns for arrays and objects', function () {
    $original = CashNotes::fromInput(['1000' => 4, '50' => 1, 'coins' => 7]);

    $fromArray = CashNotes::fromColumns($original->columns());
    $fromObject = CashNotes::fromColumns((object) $original->columns('x_'), 'x_');

    expect($fromArray->columns())->toEqual($original->columns())
        ->and($fromObject->total())->toBe($original->total());
});

it('adds and subtracts, allowing negative counts', function () {
    $a = CashNotes::fromInput(['500' => 1, 'coins' => 10]);
    $b = CashNotes::fromInput(['500' => 3, 'coins' => 4]);

    $difference = $a->minus($b);

    expect($difference->count(500))->toBe(-2)
        ->and($difference->coins())->toBe(6.0)
        ->and($difference->total())->toBe(-994.0)
        ->and($a->plus($b)->count(500))->toBe(4)
        ->and($a->plus($b)->total())->toBe(2014.0);
});

it('is not empty when opposite counts cancel in the sum', function () {
    $diff = CashNotes::fromInput(['5000' => 1])->minus(CashNotes::fromInput(['1000' => 1]));

    expect($diff->isEmpty())->toBeFalse();
});

it('rounds coins and totals to two decimals', function () {
    $sum = CashNotes::fromInput(['coins' => 0.1])->plus(CashNotes::fromInput(['coins' => 0.2]));

    expect($sum->total())->toBe(0.3)
        ->and(CashNotes::fromInput(['coins' => '5.555'])->coins())->toBe(5.56);
});
