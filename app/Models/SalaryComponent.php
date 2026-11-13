<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An allowance (earning) or a deduction. "fixed" is a set amount per employee, "percent_of_basic" a percentage of the
 * basic salary, "percent_of_component" a percentage of another allowance, "input" an amount decided each month
 * (overtime, bonus, fine...). The calculation here is the default; each employee's set-up may choose another.
 */
class SalaryComponent extends Model
{
    public const TYPE_EARNING = 'earning';

    public const TYPE_DEDUCTION = 'deduction';

    public const CALC_FIXED = 'fixed';

    public const CALC_PERCENT = 'percent_of_basic';

    public const CALC_PERCENT_COMPONENT = 'percent_of_component';

    public const CALC_INPUT = 'input';

    /** Ways a standing (every-month) amount can be worked out for an employee. */
    public const STANDING_CALCULATIONS = [self::CALC_FIXED, self::CALC_PERCENT, self::CALC_PERCENT_COMPONENT];

    /** @return array<string, string> */
    public static function calculationLabels(): array
    {
        return [
            self::CALC_FIXED => 'Fixed amount', self::CALC_PERCENT => '% of basic', self::CALC_PERCENT_COMPONENT => '% of another allowance',
            self::CALC_INPUT => 'Decided each month',
        ];
    }

    public function isStanding(): bool
    {
        return $this->calculation !== self::CALC_INPUT;
    }

    protected $fillable = ['code', 'name', 'type', 'calculation', 'default_value', 'account_id', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['default_value' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    public function isEarning(): bool
    {
        return $this->type === self::TYPE_EARNING;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
