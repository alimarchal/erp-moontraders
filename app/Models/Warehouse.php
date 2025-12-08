<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    /** @use HasFactory<\Database\Factories\WarehouseFactory> */
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
}
