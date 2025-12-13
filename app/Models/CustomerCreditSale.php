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
        'supplier_id',
        'customer_id',
        'invoice_number',
        'sale_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sale_amount' => 'decimal:2',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerLedger(): BelongsTo
    {
        return $this->belongsTo(CustomerLedger::class, 'id', 'credit_sale_id');
    }
}
