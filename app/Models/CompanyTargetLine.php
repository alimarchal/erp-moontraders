<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyTargetLine extends Model
{
    protected $fillable = [
        'company_target_id',
        'employee_id',
        'target_label',
        'target_amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
        ];
    }

    public function companyTarget(): BelongsTo
    {
        return $this->belongsTo(CompanyTarget::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
