<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'is_powder',
        'is_active',
    ];

    protected $casts = [
        'weight' => 'decimal:3',
        'uom_conversion_factor' => 'decimal:3',
        'reorder_level' => 'decimal:2',
        'unit_sell_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_powder' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the category that owns the product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
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
}
