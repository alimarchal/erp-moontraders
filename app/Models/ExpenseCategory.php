<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a voucher is for (toner, grocery, rent...), with the usual expense account of that kind of cost.
 */
class ExpenseCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'default_expense_account_id', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function defaultExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_expense_account_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(ExpenseVoucher::class);
    }
}
