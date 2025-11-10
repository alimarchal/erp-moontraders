<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptNoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'grn_id',
        'line_no',
        'product_id',
        'uom_id',
        'quantity_ordered',
        'quantity_received',
        'quantity_accepted',
        'quantity_rejected',
        'unit_cost',
        'total_cost',
        'selling_price',
        'batch_number',
        'lot_number',
        'manufacturing_date',
        'expiry_date',
        'promotional_campaign_id',
        'is_promotional',
        'promotional_price',
        'promotional_discount_percent',
        'must_sell_before',
        'priority_order',
        'quality_status',
        'storage_location',
        'notes',
    ];

    protected $casts = [
        'quantity_ordered' => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'quantity_accepted' => 'decimal:2',
        'quantity_rejected' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'manufacturing_date' => 'date',
        'expiry_date' => 'date',
        'is_promotional' => 'boolean',
        'promotional_price' => 'decimal:2',
        'promotional_discount_percent' => 'decimal:2',
        'must_sell_before' => 'date',
    ];

    public function grn(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class, 'grn_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function promotionalCampaign(): BelongsTo
    {
        return $this->belongsTo(PromotionalCampaign::class);
    }

    public function isApproved(): bool
    {
        return $this->quality_status === 'approved';
    }

    public function isPriority(): bool
    {
        return $this->priority_order < 50;
    }

    public function getEffectiveSellingPrice(): ?float
    {
        // Priority: promotional_price > selling_price > product.unit_price
        if ($this->is_promotional && $this->promotional_price) {
            return $this->promotional_price;
        }

        if ($this->selling_price) {
            return $this->selling_price;
        }

        return $this->product->unit_price ?? null;
    }

    public function getMargin(): float
    {
        $sellingPrice = $this->getEffectiveSellingPrice();
        if (!$sellingPrice) {
            return 0;
        }

        return $sellingPrice - $this->unit_cost;
    }
}
