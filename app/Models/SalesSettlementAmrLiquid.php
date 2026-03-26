<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesSettlementAmrLiquid extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $record) {
            if (! $record->isDirty('is_disposed')) {
                return;
            }

            if ($record->is_disposed && ! $record->disposed_at) {
                $record->disposed_at = now();
            }

            if (! $record->is_disposed) {
                $record->disposed_at = null;
            }
        });
    }

    protected $fillable = [
        'sales_settlement_id',
        'product_id',
        'stock_batch_id',
        'batch_code',
        'quantity',
        'amount',
        'notes',
        'is_disposed',
        'disposed_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'amount' => 'decimal:2',
        'is_disposed' => 'boolean',
        'disposed_at' => 'datetime',
    ];

    public function salesSettlement(): BelongsTo
    {
        return $this->belongsTo(SalesSettlement::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }
}
