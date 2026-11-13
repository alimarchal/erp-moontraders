<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One standing allowance or deduction of an employee. The calculation is the category's own unless one is chosen here;
 * "% of another allowance" names that allowance in base_component_id.
 */
class EmployeeSalaryComponent extends Model
{
    protected $fillable = ['employee_id', 'salary_component_id', 'calculation', 'base_component_id', 'value'];

    protected function casts(): array
    {
        return ['value' => 'decimal:2'];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }

    public function baseComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'base_component_id');
    }

    public function effectiveCalculation(): string
    {
        $calculation = $this->calculation ?: $this->component?->calculation ?: SalaryComponent::CALC_FIXED;

        if ($calculation === SalaryComponent::CALC_PERCENT_COMPONENT && ! $this->base_component_id) {
            return SalaryComponent::CALC_FIXED;
        }

        return in_array($calculation, SalaryComponent::STANDING_CALCULATIONS, true) ? $calculation : SalaryComponent::CALC_FIXED;
    }
}
