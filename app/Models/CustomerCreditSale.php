<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCreditSale extends Model
{
    protected $table = 'customer_credit_sales';

    protected $fillable = [
        'sales_settlement_id',
        'employee_id',
        'customer_id',
        'invoice_number',
        'sale_amount',
        'recovery_amount',
        'previous_balance',
        'new_balance',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'sale_amount' => 'decimal:2',
            'recovery_amount' => 'decimal:2',
            'previous_balance' => 'decimal:2',
            'new_balance' => 'decimal:2',
        ];
    }

    public function salesSettlement(): BelongsTo
    {
        return $this->belongsTo(SalesSettlement::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerLedger(): BelongsTo
    {
        return $this->belongsTo(CustomerLedger::class, 'id', 'credit_sale_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
