<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesSettlementAdvanceTax extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_settlement_id',
        'customer_id',
        'sale_amount',
        'tax_rate',
        'tax_amount',
        'invoice_number',
        'notes',
    ];

    protected $casts = [
        'sale_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

    public function salesSettlement(): BelongsTo
    {
        return $this->belongsTo(SalesSettlement::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
