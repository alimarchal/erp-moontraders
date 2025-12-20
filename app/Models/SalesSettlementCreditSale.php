<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesSettlementCreditSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_settlement_id',
        'customer_id',
        'employee_id',
        'invoice_number',
        'sale_amount',
        'payment_received',
        'previous_balance',
        'new_balance',
        'notes',
    ];

    protected $casts = [
        'sale_amount' => 'decimal:2',
        'payment_received' => 'decimal:2',
        'previous_balance' => 'decimal:2',
        'new_balance' => 'decimal:2',
    ];

    public function salesSettlement(): BelongsTo
    {
        return $this->belongsTo(SalesSettlement::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
