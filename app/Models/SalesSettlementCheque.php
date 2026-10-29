<?php

namespace App\Models;

use App\Traits\HasSettlementAttachment;
use App\Traits\UserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesSettlementCheque extends Model
{
    use HasFactory, HasSettlementAttachment, UserTracking;

    protected $fillable = [
        'attachment',
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
        'cleared_amount',
        'notes',
        'created_by',
        'updated_by',
        'status_updated_at',
    ];

    protected function attachmentSection(): string
    {
        return 'cheques';
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cleared_amount' => 'decimal:2',
            'cheque_date' => 'date',
            'cleared_date' => 'date',
            'status_updated_at' => 'datetime',
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

    public function histories(): HasMany
    {
        return $this->hasMany(SalesSettlementChequeHistory::class)->latest('id');
    }

    /**
     * What the bank actually paid: the cleared amount, or the cheque amount when none was recorded.
     */
    public function clearedAmountOrFull(): float
    {
        return (float) ($this->cleared_amount ?? $this->amount);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCleared(): bool
    {
        return $this->status === 'cleared';
    }

    public function isBounced(): bool
    {
        return $this->status === 'bounced';
    }
}
