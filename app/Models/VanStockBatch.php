<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VanStockBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'product_id',
        'goods_issue_item_id',
        'goods_issue_number',
        'quantity_on_hand',
        'unit_cost',
        'selling_price',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function goodsIssueItem(): BelongsTo
    {
        return $this->belongsTo(GoodsIssueItem::class);
    }
}
