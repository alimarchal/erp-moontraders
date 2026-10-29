<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesSettlementChequeHistory extends Model
{
    protected $fillable = [
        'sales_settlement_cheque_id',
        'from_status',
        'to_status',
        'cleared_date',
        'cleared_amount',
        'notes',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'cleared_date' => 'date',
            'cleared_amount' => 'decimal:2',
        ];
    }

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(SalesSettlementCheque::class, 'sales_settlement_cheque_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
