<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesSettlementItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_settlement_id',
        'goods_issue_item_id',
        'product_id',
        'quantity_issued',
        'quantity_sold',
        'quantity_returned',
        'quantity_shortage',
        'unit_selling_price',
        'total_sales_value',
        'unit_cost',
        'total_cogs',
    ];

    protected $casts = [
        'quantity_issued' => 'decimal:3',
        'quantity_sold' => 'decimal:3',
        'quantity_returned' => 'decimal:3',
        'quantity_shortage' => 'decimal:3',
        'unit_selling_price' => 'decimal:2',
        'total_sales_value' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cogs' => 'decimal:2',
    ];

    public function salesSettlement(): BelongsTo
    {
        return $this->belongsTo(SalesSettlement::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function goodsIssueItem(): BelongsTo
    {
        return $this->belongsTo(GoodsIssueItem::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(SalesSettlementItemBatch::class);
    }

    public function getGrossProfit(): float
    {
        return $this->total_sales_value - $this->total_cogs;
    }

    public function getGrossProfitMargin(): float
    {
        return $this->total_sales_value > 0
            ? ($this->getGrossProfit() / $this->total_sales_value) * 100
            : 0;
    }
}
