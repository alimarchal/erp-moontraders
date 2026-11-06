<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliationItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'bank_reconciliation_id',
        'bank_statement_entry_id',
        'journal_entry_detail_id',
        'match_type',
        'matched_by',
        'matched_at',
    ];

    protected $casts = [
        'matched_at' => 'datetime',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(BankStatementEntry::class, 'bank_statement_entry_id');
    }

    public function journalEntryDetail(): BelongsTo
    {
        return $this->belongsTo(JournalEntryDetail::class);
    }
}
