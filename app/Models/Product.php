<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_code',
        'product_name',
        'description',
        'category_id',
        'supplier_id',
        'uom_id',
        'weight',
        'pack_size',
        'barcode',
        'brand',
        'valuation_method',
        'reorder_level',
        'unit_price',
        'cost_price',
        'inventory_account_id',
        'cogs_account_id',
        'sales_revenue_account_id',
        'is_active',
    ];

    protected $casts = [
        'weight' => 'decimal:3',
        'reorder_level' => 'decimal:2',
        'unit_price' => 'decimal:2',
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
     * Get the unit of measurement for the product.
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
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
