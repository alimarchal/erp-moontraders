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
        'stock_uom_id',
        'purchase_uom_id',
        'qty_in_purchase_uom',
        'uom_conversion_factor',
        'qty_in_stock_uom',
        'unit_price_per_case',
        'extended_value',
        'discount_value',
        'fmr_allowance',
        'discounted_value_before_tax',
        'excise_duty',
        'sales_tax_value',
        'advance_income_tax',
        'total_value_with_taxes',
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
        'qty_in_purchase_uom' => 'decimal:2',
        'uom_conversion_factor' => 'decimal:4',
        'qty_in_stock_uom' => 'decimal:2',
        'unit_price_per_case' => 'decimal:2',
        'extended_value' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'fmr_allowance' => 'decimal:2',
        'discounted_value_before_tax' => 'decimal:2',
        'excise_duty' => 'decimal:2',
        'sales_tax_value' => 'decimal:2',
        'advance_income_tax' => 'decimal:2',
        'total_value_with_taxes' => 'decimal:2',
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

    public function stockUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'stock_uom_id');
    }

    public function purchaseUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'purchase_uom_id');
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
        // Priority: promotional_price > selling_price > product.unit_sell_price
        if ($this->is_promotional && $this->promotional_price) {
            return $this->promotional_price;
        }

        if ($this->selling_price) {
            return $this->selling_price;
        }

        return $this->product->unit_sell_price ?? null;
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
