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
        'total_amount',
    ];

    protected $casts = [
        'denom_coins' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function ($denomination) {
            $denomination->total_amount =
                ($denomination->denom_5000 * 5000) +
                ($denomination->denom_1000 * 1000) +
                ($denomination->denom_500 * 500) +
                ($denomination->denom_100 * 100) +
                ($denomination->denom_50 * 50) +
                ($denomination->denom_20 * 20) +
                ($denomination->denom_10 * 10) +
                ($denomination->denom_coins);
        });
    }

    public function salesSettlement(): BelongsTo
    {
        return $this->belongsTo(SalesSettlement::class);
    }
}
