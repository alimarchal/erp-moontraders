<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_name',
        'account_number',
        'bank_name',
        'branch',
        'iban',
        'swift_code',
        'chart_of_account_id',
        'register_chart_of_account_id',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    /**
     * The Bank Register's own ledger for this account (the live ledger is chartOfAccount).
     */
    public function registerLedger(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'register_chart_of_account_id');
    }
}
