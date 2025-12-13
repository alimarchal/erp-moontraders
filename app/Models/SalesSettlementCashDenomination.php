<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesSettlementCashDenomination extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_settlement_id',
        'denom_5000',
        'denom_1000',
        'denom_500',
        'denom_100',
        'denom_50',
        'denom_20',
        'denom_10',
        'denom_coins',
    ];

    protected $casts = [
        'denom_coins' => 'decimal:2',
    ];

    public function salesSettlement(): BelongsTo
    {
        return $this->belongsTo(SalesSettlement::class);
    }

    /**
     * Calculate total cash amount from denominations
     */
    public function getTotalAmountAttribute(): float
    {
        return (float) (
            ($this->denom_5000 * 5000) +
            ($this->denom_1000 * 1000) +
            ($this->denom_500 * 500) +
            ($this->denom_100 * 100) +
            ($this->denom_50 * 50) +
            ($this->denom_20 * 20) +
            ($this->denom_10 * 10) +
            $this->denom_coins
        );
    }
}
