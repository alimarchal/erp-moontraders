<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An allowance (earning) or a deduction. "fixed" is a set amount per employee, "percent_of_basic" a
 * percentage of the basic salary, "input" an amount typed in each month (overtime, fine, tax...).
 */
class SalaryComponent extends Model
{
    public const TYPE_EARNING = 'earning';

    public const TYPE_DEDUCTION = 'deduction';

    public const CALC_FIXED = 'fixed';

    public const CALC_PERCENT = 'percent_of_basic';

    public const CALC_INPUT = 'input';

    protected $fillable = ['code', 'name', 'type', 'calculation', 'default_value', 'account_id', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['default_value' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function isEarning(): bool
    {
        return $this->type === self::TYPE_EARNING;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
