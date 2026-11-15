<?php

use App\Support\AmountInWords;

it('spells whole rupees', function () {
    expect(AmountInWords::rupees(1500))->toBe('one thousand five hundred rupees only');
});

it('spells rupees and paisa', function () {
    expect(AmountInWords::rupees(101369.50))->toBe('one hundred one thousand three hundred sixty-nine rupees and fifty paisa only');
});

it('spells zero and large amounts', function () {
    expect(AmountInWords::rupees(0))->toBe('zero rupees only')
        ->and(AmountInWords::rupees(2000000.05))->toBe('two million rupees and five paisa only');
});

it('spells without intl the same words as intl', function () {
    $formatter = new NumberFormatter('en', NumberFormatter::SPELLOUT);

    foreach ([0, 7, 15, 20, 42, 100, 101, 999, 1000, 1500, 21017, 101369, 1000000, 2345678901] as $number) {
        expect(AmountInWords::spellWithoutIntl($number))->toBe($formatter->format($number));
    }
})->skip(! extension_loaded('intl'), 'intl is not loaded');
