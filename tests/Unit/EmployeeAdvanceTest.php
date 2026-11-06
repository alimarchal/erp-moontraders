<?php

use App\Models\EmployeeAdvance;
use App\Models\SalaryComponent;

it('knows its balance and what payroll takes this month', function () {
    $advance = new EmployeeAdvance(['amount' => 10000, 'recovered_amount' => 7500, 'installment_amount' => 4000]);

    expect($advance->balance())->toBe(2500.0)
        ->and($advance->dueThisMonth())->toBe(2500.0);
});

it('takes the instalment while more than one instalment is left', function () {
    expect((new EmployeeAdvance(['amount' => 10000, 'recovered_amount' => 0, 'installment_amount' => 4000]))->dueThisMonth())->toBe(4000.0);
});

it('takes everything left when the advance has no instalment', function () {
    expect((new EmployeeAdvance(['amount' => 10000, 'recovered_amount' => 1000]))->dueThisMonth())->toBe(9000.0);
});

it('tells earnings from deductions', function () {
    expect((new SalaryComponent(['type' => 'earning']))->isEarning())->toBeTrue()
        ->and((new SalaryComponent(['type' => 'deduction']))->isEarning())->toBeFalse();
});
