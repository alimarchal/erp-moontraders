<?php

namespace App\Models;

use App\Traits\UserTracking;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An incentive, commission, overtime, fine... decided in advance for one employee: for one month only (one time), or
 * every month from period_month until end_month or until stopped (recurring).
 */
class PayrollAdjustment extends Model
{
    use HasFactory, SoftDeletes, UserTracking;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    protected $fillable = ['employee_id', 'salary_component_id', 'period_month', 'amount', 'note', 'is_recurring', 'end_month', 'status', 'applied_payroll_run_id'];

    protected function casts(): array
    {
        return ['period_month' => 'date', 'end_month' => 'date', 'amount' => 'decimal:2', 'is_recurring' => 'boolean'];
    }

    /**
     * A one-time item still waiting for its payroll. A recurring item stays pending while it runs; each month's slip
     * line records where it was paid.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Items that cover the given month, applied or not: one-time items of that month and recurring items running in it.
     *
     * @param  Builder<PayrollAdjustment>  $query
     */
    public function scopeCoversMonth(Builder $query, CarbonInterface $month): void
    {
        $date = $month->copy()->startOfMonth()->toDateString();

        $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $one) => $one->where('is_recurring', false)->whereDate('period_month', $date))
            ->orWhere(fn (Builder $every) => $every->where('is_recurring', true)->whereDate('period_month', '<=', $date)
                ->where(fn (Builder $open) => $open->whereNull('end_month')->orWhereDate('end_month', '>=', $date))));
    }

    /**
     * Items that belong on the given month's payslip: one-time items of that month not yet applied, and recurring items
     * that started on or before it and have not ended.
     *
     * @param  Builder<PayrollAdjustment>  $query
     */
    public function scopeDueIn(Builder $query, CarbonInterface $month): void
    {
        $date = $month->copy()->startOfMonth()->toDateString();

        $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $one) => $one->where('is_recurring', false)->whereDate('period_month', $date)->where('status', self::STATUS_PENDING))
            ->orWhere(fn (Builder $every) => $every->where('is_recurring', true)->whereDate('period_month', '<=', $date)
                ->where(fn (Builder $open) => $open->whereNull('end_month')->orWhereDate('end_month', '>=', $date))));
    }

    /**
     * Human words for when it is paid: "Nov 2026 only", "Every month from Nov 2026" or "Nov 2026 – Mar 2027".
     */
    public function scheduleLabel(): string
    {
        if (! $this->is_recurring) {
            return $this->period_month->format('M Y').' only';
        }

        return $this->end_month
            ? 'Every month, '.$this->period_month->format('M Y').' – '.$this->end_month->format('M Y')
            : 'Every month from '.$this->period_month->format('M Y');
    }

    public function slipLines(): HasMany
    {
        return $this->hasMany(PayrollSlipLine::class);
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
