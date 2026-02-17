<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_code',
        'product_id',
        'supplier_id',
        'receipt_date',
        'supplier_batch_number',
        'lot_number',
        'manufacturing_date',
        'expiry_date',
        'promotional_campaign_id',
        'is_promotional',
        'promotional_selling_price',
        'promotional_discount_percent',
        'must_sell_before',
        'priority_order',
        'selling_strategy',
        'unit_cost',
        'selling_price',
        'status',
        'is_active',
        'notes',
        'storage_location',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'manufacturing_date' => 'date',
        'expiry_date' => 'date',
        'must_sell_before' => 'date',
        'is_promotional' => 'boolean',
        'promotional_selling_price' => 'decimal:2',
        'promotional_discount_percent' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function promotionalCampaign(): BelongsTo
    {
        return $this->belongsTo(PromotionalCampaign::class);
    }

    public function grnItem()
    {
        return $this->hasOne(GoodsReceiptNoteItem::class, 'batch_number', 'batch_code');
    }

    public function grn()
    {
        return $this->hasOneThrough(
            GoodsReceiptNote::class,
            GoodsReceiptNoteItem::class,
            'batch_number',
            'id',
            'batch_code',
            'grn_id'
        );
    }

    public function currentStockByBatch(): HasMany
    {
        return $this->hasMany(CurrentStockByBatch::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date < now();
    }

    public function isPriority(): bool
    {
        return $this->priority_order < 50;
    }
}
