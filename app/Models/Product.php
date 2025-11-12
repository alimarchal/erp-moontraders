<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Supported valuation methods for costing.
     *
     * @var string[]
     */
    public const VALUATION_METHODS = ['FIFO', 'LIFO', 'Average', 'Standard'];

    protected $fillable = [
        'product_code',
        'product_name',
        'description',
        'category_id',
        'supplier_id',
        'uom_id',
        'sales_uom_id',
        'uom_conversion_factor',
        'weight',
        'pack_size',
        'barcode',
        'brand',
        'valuation_method',
        'reorder_level',
        'unit_sell_price',
        'cost_price',
        'inventory_account_id',
        'cogs_account_id',
        'sales_revenue_account_id',
        'is_active',
    ];

    protected $casts = [
        'weight' => 'decimal:3',
        'uom_conversion_factor' => 'decimal:3',
        'reorder_level' => 'decimal:2',
        'unit_sell_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the category that owns the product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * Get the supplier that owns the product.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Get the base unit of measurement (inventory tracking).
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    /**
     * Get the sales unit of measurement.
     */
    public function salesUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'sales_uom_id');
    }

    /**
     * Get the inventory account.
     */
    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'inventory_account_id');
    }

    /**
     * Get the COGS account.
     */
    public function cogsAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'cogs_account_id');
    }

    /**
     * Get the sales revenue account.
     */
    public function salesRevenueAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'sales_revenue_account_id');
    }
}
