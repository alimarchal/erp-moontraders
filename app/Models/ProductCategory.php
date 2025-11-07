<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_code',
        'category_name',
        'description',
        'parent_id',
        'default_inventory_account_id',
        'default_cogs_account_id',
        'default_sales_revenue_account_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the products for the category.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    /**
     * Get the child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'parent_id');
    }

    /**
     * Get the default inventory account.
     */
    public function defaultInventoryAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_inventory_account_id');
    }

    /**
     * Get the default COGS account.
     */
    public function defaultCogsAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_cogs_account_id');
    }

    /**
     * Get the default sales revenue account.
     */
    public function defaultSalesRevenueAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_sales_revenue_account_id');
    }
}