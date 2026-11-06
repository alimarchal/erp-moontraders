<?php

use App\Support\PhpLimits;

it('turns PHP shorthand sizes into bytes', function (string $value, int $bytes) {
    expect(PhpLimits::bytes($value))->toBe($bytes);
})->with([
    'megabytes' => ['8M', 8388608],
    'kilobytes' => ['512K', 524288],
    'gigabytes' => ['1G', 1073741824],
    'plain bytes' => ['2048', 2048],
    'unlimited' => ['-1', 0],
    'empty' => ['', 0],
]);
