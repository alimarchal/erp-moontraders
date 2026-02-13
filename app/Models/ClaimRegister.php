<?php

namespace App\Models;

use App\Traits\UserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClaimRegister extends Model
{
    /** @use HasFactory<\Database\Factories\ClaimRegisterFactory> */
    use HasFactory, SoftDeletes, UserTracking;

    protected $fillable = [
        'supplier_id',
        'transaction_date',
        'reference_number',
        'description',
        'claim_month',
        'date_of_dispatch',
        'transaction_type',
        'debit',
        'credit',
        'debit_account_id',
        'credit_account_id',
        'payment_method',
        'bank_account_id',
        'notes',
        'journal_entry_id',
        'posted_at',
        'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'date_of_dispatch' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public static function transactionTypeOptions(): array
    {
        return [
            'claim' => 'Claim (Debit)',
            'recovery' => 'Recovery (Credit)',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'credit_account_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }
}
