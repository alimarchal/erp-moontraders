<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_adjustment_id',
        'product_id',
        'stock_batch_id',
        'grn_item_id',
        'system_quantity',
        'actual_quantity',
        'adjustment_quantity',
        'unit_cost',
        'adjustment_value',
        'uom_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:3',
            'actual_quantity' => 'decimal:3',
            'adjustment_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'adjustment_value' => 'decimal:2',
        ];
    }

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }

    public function grnItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNoteItem::class, 'grn_item_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }
}
