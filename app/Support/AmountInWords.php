<?php

namespace App\Support;

use NumberFormatter;

/**
 * Spells an amount for a payment voucher, e.g. 1500 => "one thousand five hundred rupees only".
 * Uses the intl spell-out formatter when the extension is loaded, otherwise a small built-in speller
 * that gives the same words (short scale: thousand, million, billion).
 */
final class AmountInWords
{
    private const ONES = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
        'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    private const TENS = [2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty', 6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety'];

    private const SCALES = [1_000_000_000_000 => 'trillion', 1_000_000_000 => 'billion', 1_000_000 => 'million', 1_000 => 'thousand'];

    public static function rupees(float $amount): string
    {
        $paisaTotal = (int) round(abs($amount) * 100);
        $rupees = intdiv($paisaTotal, 100);
        $paisa = $paisaTotal % 100;

        $words = self::spell($rupees).' rupees';

        if ($paisa > 0) {
            $words .= ' and '.self::spell($paisa).' paisa';
        }

        return $words.' only';
    }

    /**
     * A whole number in lower-case English words.
     */
    public static function spell(int $number): string
    {
        if (extension_loaded('intl')) {
            return (string) (new NumberFormatter('en', NumberFormatter::SPELLOUT))->format($number);
        }

        return self::spellWithoutIntl($number);
    }

    /**
     * The same words as intl's spell-out, for a server without the extension.
     */
    public static function spellWithoutIntl(int $number): string
    {
        if ($number < 20) {
            return self::ONES[$number];
        }

        if ($number < 100) {
            $tens = self::TENS[intdiv($number, 10)];

            return $number % 10 === 0 ? $tens : $tens.'-'.self::ONES[$number % 10];
        }

        if ($number < 1000) {
            $rest = $number % 100;

            return self::ONES[intdiv($number, 100)].' hundred'.($rest > 0 ? ' '.self::spellWithoutIntl($rest) : '');
        }

        foreach (self::SCALES as $size => $name) {
            if ($number >= $size) {
                $rest = $number % $size;

                return self::spellWithoutIntl(intdiv($number, $size)).' '.$name.($rest > 0 ? ' '.self::spellWithoutIntl($rest) : '');
            }
        }

        return (string) $number;
    }
}
