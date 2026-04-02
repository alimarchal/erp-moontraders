<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesSettlementExcessAmount extends Model
{
    protected $fillable = [
        'sales_settlement_id',
        'amount',
        'description',
    ];

    public function salesSettlement(): BelongsTo
    {
        return $this->belongsTo(SalesSettlement::class);
    }
}
