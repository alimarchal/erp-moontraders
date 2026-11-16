<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One status change of a cheque. Rows are an audit trail: they are written once and never changed or deleted.
 */
class SalesSettlementChequeHistory extends Model
{
    protected $fillable = [
        'sales_settlement_cheque_id',
        'from_status',
        'to_status',
        'cleared_date',
        'cleared_amount',
        'cleared_via',
        'bank_account_id',
        'cheque_clearing_id',
        'journal_entry_id',
        'reversed_journal_entry_id',
        'bank_charge',
        'denominations',
        'notes',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'cleared_date' => 'date',
            'cleared_amount' => 'decimal:2',
            'bank_charge' => 'decimal:2',
            'denominations' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Cheque history rows cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Cheque history rows cannot be deleted.'));
    }

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(SalesSettlementCheque::class, 'sales_settlement_cheque_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function reversedJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_journal_entry_id');
    }
}
