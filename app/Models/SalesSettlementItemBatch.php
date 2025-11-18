<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesSettlementItemBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_settlement_item_id',
        'stock_batch_id',
        'batch_code',
        'quantity_issued',
        'quantity_sold',
        'quantity_returned',
        'quantity_shortage',
        'unit_cost',
        'selling_price',
        'is_promotional',
    ];

    protected $casts = [
        'quantity_issued' => 'decimal:3',
        'quantity_sold' => 'decimal:3',
        'quantity_returned' => 'decimal:3',
        'quantity_shortage' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_promotional' => 'boolean',
    ];

    public function salesSettlementItem(): BelongsTo
    {
        return $this->belongsTo(SalesSettlementItem::class);
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }
}
