<?php

namespace App\Models;

use App\Traits\UserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Salary an employee has taken in advance. Created when the advance voucher is approved; payroll
 * takes the instalment (or the whole balance) out of the next salary and records it here.
 */
class EmployeeAdvance extends Model
{
    use HasFactory, SoftDeletes, UserTracking;

    public const STATUS_OPEN = 'open';

    public const STATUS_RECOVERED = 'recovered';

    protected $fillable = [
        'employee_id', 'expense_voucher_id', 'supplier_id', 'company_id', 'advance_date', 'amount', 'installment_amount',
        'recovered_amount', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return ['advance_date' => 'date', 'amount' => 'decimal:2', 'installment_amount' => 'decimal:2', 'recovered_amount' => 'decimal:2'];
    }

    public function balance(): float
    {
        return round((float) $this->amount - (float) $this->recovered_amount, 2);
    }

    /** What payroll should take this month: the instalment, or the whole balance when there is none. */
    public function dueThisMonth(): float
    {
        $balance = $this->balance();

        return $this->installment_amount ? min((float) $this->installment_amount, $balance) : $balance;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(ExpenseVoucher::class, 'expense_voucher_id');
    }

    public function recoveries(): HasMany
    {
        return $this->hasMany(EmployeeAdvanceRecovery::class);
    }
}
