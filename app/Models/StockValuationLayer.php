<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockValuationLayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'stock_batch_id',
        'stock_movement_id',
        'grn_item_id',
        'receipt_date',
        'quantity_received',
        'quantity_remaining',
        'unit_cost',
        'total_value',
        'value_remaining',
        'priority_order',
        'must_sell_before',
        'is_promotional',
        'is_depleted',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'must_sell_before' => 'date',
        'quantity_received' => 'decimal:2',
        'quantity_remaining' => 'decimal:2',
        // The column is decimal(15,6); a decimal:2 cast rounded every cost read
        // through Eloquent, which is how adjustment layers ended up at 393.40
        // against the GRN's 393.397.
        'unit_cost' => 'decimal:6',
        'is_promotional' => 'boolean',
        'is_depleted' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function grnItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNoteItem::class, 'grn_item_id');
    }

    public function hasStock(): bool
    {
        return $this->quantity_remaining > 0;
    }

    public function markDepleted(): void
    {
        $this->update(['is_depleted' => true, 'quantity_remaining' => 0]);
    }
}
