<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRecallItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_recall_id',
        'product_id',
        'stock_batch_id',
        'grn_item_id',
        'quantity_recalled',
        'unit_cost',
        'total_value',
        'reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_recalled' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'total_value' => 'decimal:2',
        ];
    }

    public function productRecall(): BelongsTo
    {
        return $this->belongsTo(ProductRecall::class);
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
}
