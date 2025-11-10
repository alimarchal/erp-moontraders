<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrentStock extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'current_stock';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity_on_hand',
        'quantity_reserved',
        'average_cost',
        'total_value',
        'total_batches',
        'promotional_batches',
        'priority_batches',
        'last_updated',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:2',
        'quantity_reserved' => 'decimal:2',
        'average_cost' => 'decimal:2',
        'total_value' => 'decimal:2',
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

    public function hasStock(): bool
    {
        return $this->quantity_on_hand > 0;
    }

    public function isAvailable(float $quantity): bool
    {
        return $this->quantity_available >= $quantity;
    }
}
