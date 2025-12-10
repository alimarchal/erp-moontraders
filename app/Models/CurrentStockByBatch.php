<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrentStockByBatch extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'current_stock_by_batch';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'stock_batch_id',
        'quantity_on_hand',
        'unit_cost',
        'selling_price',
        'is_promotional',
        'promotional_price',
        'priority_order',
        'must_sell_before',
        'expiry_date',
        'status',
        'last_updated',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'promotional_price' => 'decimal:2',
        'is_promotional' => 'boolean',
        'must_sell_before' => 'date',
        'expiry_date' => 'date',
        'last_updated' => 'datetime',
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

    public function hasStock(): bool
    {
        return $this->quantity_on_hand > 0 && $this->status === 'active';
    }

    public function isPriority(): bool
    {
        return $this->priority_order < 50;
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (! $this->expiry_date) {
            return false;
        }

        return $this->expiry_date <= now()->addDays($days);
    }
}
