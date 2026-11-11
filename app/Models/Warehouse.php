<?php

namespace App\Models;

use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'warehouse_name',
        'disabled',
        'is_group',
        'parent_warehouse_id',
        'lft',
        'rgt',
        'company_id',
        'warehouse_type_id',
        'is_rejected_warehouse',
        'default_in_transit_warehouse_id',
        'account_id',
        'email_id',
        'phone_no',
        'mobile_no',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'pin',
    ];

    protected $casts = [
        'disabled' => 'boolean',
        'is_group' => 'boolean',
        'is_rejected_warehouse' => 'boolean',
    ];

    /**
     * Get the company that owns the warehouse
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the warehouse type
     */
    public function warehouseType(): BelongsTo
    {
        return $this->belongsTo(WarehouseType::class);
    }

    /**
     * Get the parent warehouse (self-referencing)
     */
    public function parentWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'parent_warehouse_id');
    }

    /**
     * Get the child warehouses
     */
    public function childWarehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'parent_warehouse_id');
    }

    /**
     * Get the default in-transit warehouse
     */
    public function defaultInTransitWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_in_transit_warehouse_id');
    }

    /**
     * Get the accounting account associated with the warehouse
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    /**
     * Stock rows held in this warehouse, one per product.
     */
    public function currentStocks(): HasMany
    {
        return $this->hasMany(CurrentStock::class);
    }

    /**
     * Units still on hand in this warehouse, over every product.
     */
    public function stockOnHand(): float
    {
        return (float) $this->currentStocks()->sum('quantity_on_hand');
    }

    /**
     * A warehouse that still holds stock cannot be deactivated: the stock would become unreachable.
     */
    public function hasStock(): bool
    {
        return $this->currentStocks()->where('quantity_on_hand', '>', 0)->exists()
            || CurrentStockByBatch::where('warehouse_id', $this->id)->where('quantity_on_hand', '>', 0)->exists();
    }

    /**
     * Sentence shown when deactivation is refused because of remaining stock.
     */
    public function stockBlocksDeactivationMessage(): string
    {
        $products = $this->currentStocks()->where('quantity_on_hand', '>', 0)->count();
        $units = rtrim(rtrim(number_format($this->stockOnHand(), 2), '0'), '.');

        return "Warehouse '{$this->warehouse_name}' still holds stock ({$units} units across {$products} product(s)). Transfer or issue all of it first; a warehouse can only be deactivated when its inventory is empty.";
    }
}
