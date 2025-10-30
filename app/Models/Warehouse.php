<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouse extends Model
{
    /** @use HasFactory<\Database\Factories\WarehouseFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'is_group_warehouse',
        'chart_of_account_id',
        'is_rejected_warehouse',
        'company',
        'phone_no',
        'mobile_no',
        'address_line_1',
        'address_line_2',
        'city',
        'state_province',
        'postal_code',
        'country',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_group_warehouse' => 'boolean',
        'is_rejected_warehouse' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the chart of account associated with the warehouse.
     */
    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class);
    }
}
