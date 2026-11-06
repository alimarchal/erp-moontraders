<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAdvanceRecovery extends Model
{
    protected $fillable = ['employee_advance_id', 'payroll_slip_id', 'amount'];

    public function advance(): BelongsTo
    {
        return $this->belongsTo(EmployeeAdvance::class, 'employee_advance_id');
    }

    public function slip(): BelongsTo
    {
        return $this->belongsTo(PayrollSlip::class, 'payroll_slip_id');
    }
}
