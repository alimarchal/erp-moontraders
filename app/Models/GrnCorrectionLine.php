<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One GRN line as it was before and after a correction.
 */
class GrnCorrectionLine extends Model
{
    protected $fillable = [
        'grn_correction_id',
        'goods_receipt_note_item_id',
        'stock_batch_id',
        'old_product_id',
        'new_product_id',
        'old_conversion_factor',
        'new_conversion_factor',
        'old_quantity',
        'new_quantity',
        'old_unit_cost',
        'new_unit_cost',
        'line_value',
        'stock_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'old_conversion_factor' => 'decimal:4',
            'new_conversion_factor' => 'decimal:4',
            'old_quantity' => 'decimal:2',
            'new_quantity' => 'decimal:2',
            'old_unit_cost' => 'decimal:6',
            'new_unit_cost' => 'decimal:6',
            'line_value' => 'decimal:4',
        ];
    }

    public function correction(): BelongsTo
    {
        return $this->belongsTo(GrnCorrection::class, 'grn_correction_id');
    }

    public function grnItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNoteItem::class, 'goods_receipt_note_item_id');
    }

    public function oldProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'old_product_id');
    }

    public function newProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'new_product_id');
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }
}
