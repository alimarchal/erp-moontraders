<?php

use App\Helpers\SvgChart;

it('writes money the Pakistani way', function () {
    expect(SvgChart::money(12500000))->toBe('1.25 Cr')
        ->and(SvgChart::money(4520000))->toBe('45.2 Lac')
        ->and(SvgChart::money(-8200))->toBe('-8k')
        ->and(SvgChart::money(950))->toBe('950');
});

it('draws every chart type as a valid SVG with its labels', function () {
    $charts = [
        SvgChart::columns(['A', 'B'], [['name' => 'Sales', 'color' => '#2563eb', 'values' => [100, 250]], ['name' => 'Cost', 'color' => '#f97316', 'values' => [60, 90]]]),
        SvgChart::colorColumns(['A', 'B'], [100, 250]),
        SvgChart::bars(['Alpha', 'Beta'], [300, 120]),
        SvgChart::achievement(['Alpha', 'Beta'], [140, 55]),
        SvgChart::donut(['Cash', 'Credit'], [60, 40], SvgChart::PALETTE, 360, 210, '100', 'sales'),
    ];

    foreach ($charts as $svg) {
        expect(simplexml_load_string($svg))->not->toBeFalse();
    }

    expect($charts[2])->toContain('Alpha')
        ->and($charts[4])->toContain('60.0%');
});

it('draws a single slice as a full ring and an empty donut as a message', function () {
    expect(SvgChart::donut(['Only'], [100]))->toContain('<circle')
        ->and(SvgChart::donut(['None'], [0]))->toContain('No data');
});
