<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerLedger extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'transaction_date',
        'customer_id',
        'transaction_type',
        'reference_number',
        'description',
        'debit',
        'credit',
        'balance',
        'sales_settlement_id',
        'employee_id',
        'credit_sale_id',
        'payment_method',
        'cheque_number',
        'cheque_date',
        'bank_account_id',
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
            'cheque_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesSettlement(): BelongsTo
    {
        return $this->belongsTo(SalesSettlement::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creditSale(): BelongsTo
    {
        return $this->belongsTo(CustomerCreditSale::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
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

    public function getAmount(): float
    {
        return (float) ($this->debit > 0 ? $this->debit : $this->credit);
    }
}
