<?php

namespace App\Models;

use App\Traits\UserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A bonus, commission, overtime, fine... decided in advance for one employee and one month.
 */
class PayrollAdjustment extends Model
{
    use HasFactory, SoftDeletes, UserTracking;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    protected $fillable = ['employee_id', 'salary_component_id', 'period_month', 'amount', 'note', 'status', 'applied_payroll_run_id'];

    protected function casts(): array
    {
        return ['period_month' => 'date', 'amount' => 'decimal:2'];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }

    public function appliedRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'applied_payroll_run_id');
    }
}
