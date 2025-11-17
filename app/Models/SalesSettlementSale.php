<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesSettlementSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_settlement_id',
        'customer_id',
        'sale_type',
        'invoice_number',
        'sale_amount',
        'cheque_number',
        'cheque_date',
        'bank_name',
        'payment_status',
        'notes',
    ];

    protected $casts = [
        'sale_amount' => 'decimal:2',
        'cheque_date' => 'date',
    ];

    public function salesSettlement(): BelongsTo
    {
        return $this->belongsTo(SalesSettlement::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isCash(): bool
    {
        return $this->sale_type === 'cash';
    }

    public function isCredit(): bool
    {
        return $this->sale_type === 'credit';
    }

    public function isCheque(): bool
    {
        return $this->sale_type === 'cheque';
    }
}
