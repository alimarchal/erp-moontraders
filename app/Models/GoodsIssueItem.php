<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsIssueItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_issue_id',
        'product_id',
        'quantity_issued',
        'unit_cost',
        'uom_id',
    ];

    protected $casts = [
        'quantity_issued' => 'decimal:3',
        'unit_cost' => 'decimal:2',
    ];

    public function goodsIssue(): BelongsTo
    {
        return $this->belongsTo(GoodsIssue::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function getTotalValue(): float
    {
        return $this->quantity_issued * $this->unit_cost;
    }
}
