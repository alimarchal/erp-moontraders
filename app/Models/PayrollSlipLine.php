<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSlipLine extends Model
{
    public const SOURCE_BASIC = 'basic';

    public const SOURCE_ALLOWANCE = 'allowance';

    public const SOURCE_ABSENT = 'absent';

    public const SOURCE_ADVANCE = 'advance';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_ADJUSTMENT = 'adjustment';

    protected $fillable = ['payroll_slip_id', 'salary_component_id', 'employee_advance_id', 'payroll_adjustment_id', 'kind', 'source', 'label', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function slip(): BelongsTo
    {
        return $this->belongsTo(PayrollSlip::class, 'payroll_slip_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }

    public function advance(): BelongsTo
    {
        return $this->belongsTo(EmployeeAdvance::class, 'employee_advance_id');
    }
}
