<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalary extends Model
{
    public const TYPE_BASIC = 'basic';

    public const TYPE_FIXED = 'fixed';

    protected $fillable = ['employee_id', 'salary_type', 'basic_salary', 'pay_method', 'notes', 'updated_by'];

    protected function casts(): array
    {
        return ['basic_salary' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
