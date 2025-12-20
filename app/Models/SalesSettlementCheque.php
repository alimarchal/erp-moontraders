<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesSettlementCheque extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_settlement_id',
        'customer_id',
        'bank_account_id',
        'cheque_number',
        'amount',
        'bank_name',
        'cheque_date',
        'account_holder_name',
        'status',
        'cleared_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cheque_date' => 'date',
            'cleared_date' => 'date',
        ];
    }

    public function salesSettlement(): BelongsTo
    {
        return $this->belongsTo(SalesSettlement::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * Check if cheque is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if cheque is cleared
     */
    public function isCleared(): bool
    {
        return $this->status === 'cleared';
    }

    /**
     * Check if cheque is bounced
     */
    public function isBounced(): bool
    {
        return $this->status === 'bounced';
    }
}
