<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankReconciliation extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'bank_account_id',
        'period_from',
        'period_to',
        'statement_date',
        'statement_balance',
        'opening_balance',
        'book_balance',
        'difference',
        'reconciled_total',
        'status',
        'notes',
        'created_by',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'statement_date' => 'date',
        'statement_balance' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'book_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'reconciled_total' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BankReconciliationItem::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(BankStatementEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
