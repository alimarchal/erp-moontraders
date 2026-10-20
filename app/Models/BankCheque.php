<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankCheque extends Model
{
    use HasFactory, SoftDeletes;

    public const DIRECTIONS = ['issued' => 'Issued', 'received' => 'Received'];

    public const STATUSES = ['pending' => 'Pending', 'cleared' => 'Cleared', 'bounced' => 'Bounced', 'cancelled' => 'Cancelled', 'stale' => 'Stale'];

    protected $fillable = [
        'bank_account_id',
        'cheque_no',
        'direction',
        'cheque_date',
        'amount',
        'company_id',
        'counterparty_type',
        'counterparty_id',
        'payee_name',
        'status',
        'cleared_date',
        'bank_statement_entry_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'cheque_date' => 'date',
        'cleared_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function counterparty(): MorphTo
    {
        return $this->morphTo();
    }

    public function statementEntry(): BelongsTo
    {
        return $this->belongsTo(BankStatementEntry::class, 'bank_statement_entry_id');
    }
}
