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

    /** Cash a salesman handed in above what his settlements expected (4250): his own money, paid with the salary. */
    public const SOURCE_EXCESS = 'excess';

    /** Cash a salesman was short by in the month's settlements (booked to Short Amount 5293), recovered from his salary. */
    public const SOURCE_SHORT = 'short';

    /** Lines payroll writes by itself and rewrites on every recalculation. */
    public const AUTOMATIC_SOURCES = [
        self::SOURCE_BASIC, self::SOURCE_ALLOWANCE, self::SOURCE_ABSENT, self::SOURCE_ADVANCE, self::SOURCE_ADJUSTMENT,
        self::SOURCE_EXCESS, self::SOURCE_SHORT,
    ];

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
