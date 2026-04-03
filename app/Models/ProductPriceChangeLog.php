<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceChangeLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'changed_by',
        'price_type',
        'old_price',
        'new_price',
        'impacted_batch_ids',
        'impacted_batch_count',
        'changed_at',
    ];

    protected $casts = [
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'impacted_batch_ids' => 'array',
        'impacted_batch_count' => 'integer',
        'changed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
