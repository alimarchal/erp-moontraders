<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollSlip extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'salary_type', 'basic_salary', 'days_in_month', 'absent_days',
        'total_earnings', 'total_deductions', 'net_pay', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2', 'absent_days' => 'decimal:2', 'total_earnings' => 'decimal:2',
            'total_deductions' => 'decimal:2', 'net_pay' => 'decimal:2',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollSlipLine::class)->orderBy('id');
    }

    public function recoveries(): HasMany
    {
        return $this->hasMany(EmployeeAdvanceRecovery::class);
    }
}
