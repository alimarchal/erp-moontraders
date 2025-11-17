<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VanStockBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'product_id',
        'opening_balance',
        'quantity_on_hand',
        'average_cost',
        'last_updated',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:3',
        'quantity_on_hand' => 'decimal:3',
        'average_cost' => 'decimal:2',
        'last_updated' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getTotalValue(): float
    {
        return $this->quantity_on_hand * $this->average_cost;
    }
}
