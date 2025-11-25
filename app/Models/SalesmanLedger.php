<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesmanLedger extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'transaction_date',
        'employee_id',
        'transaction_type',
        'reference_number',
        'description',
        'debit',
        'credit',
        'balance',
        'sales_settlement_id',
        'customer_id',
        'supplier_id',
        'cash_amount',
        'cheque_amount',
        'credit_amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'balance' => 'decimal:2',
            'cash_amount' => 'decimal:2',
            'cheque_amount' => 'decimal:2',
            'credit_amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salesSettlement(): BelongsTo
    {
        return $this->belongsTo(SalesSettlement::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDebit(): bool
    {
        return $this->debit > 0;
    }

    public function isCredit(): bool
    {
        return $this->credit > 0;
    }

    public function getTotalCollected(): float
    {
        return (float) ($this->cash_amount + $this->cheque_amount);
    }

    public function getNetAmount(): float
    {
        return $this->getTotalCollected() - (float) $this->credit_amount;
    }
}
