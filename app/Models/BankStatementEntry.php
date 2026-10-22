<?php

namespace App\Models;

use Database\Factories\BankStatementEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankStatementEntry extends Model
{
    /** @use HasFactory<BankStatementEntryFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_UNCLASSIFIED = 'unclassified';

    public const STATUS_CLASSIFIED = 'classified';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_POSTED = 'posted';

    public const STATUS_RECONCILED = 'reconciled';

    public const STATUS_FLAGGED = 'flagged';

    public const STATUS_REVERSED = 'reversed';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_UNCLASSIFIED => 'Unclassified',
        self::STATUS_CLASSIFIED => 'Classified',
        self::STATUS_PENDING_APPROVAL => 'Pending approval',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_POSTED => 'Posted',
        self::STATUS_RECONCILED => 'Reconciled',
        self::STATUS_FLAGGED => 'Flagged',
        self::STATUS_REVERSED => 'Reversed',
    ];

    protected $fillable = [
        'bank_account_id',
        'import_id',
        'bank_reconciliation_id',
        'statement_ref_no',
        'posting_date',
        'value_date',
        'reference',
        'narration',
        'description',
        'debit',
        'credit',
        'running_balance',
        'party_name_in_statement',
        'bank_party_alias_id',
        'company_id',
        'counterparty_type',
        'counterparty_id',
        'bank_entry_category_id',
        'classification_source',
        'needs_review',
        'receipt_no',
        'status',
        'approval_level_required',
        'approval_round',
        'journal_entry_id',
        'journal_entry_detail_id',
        'source_statement',
        'pdf_page',
        'dedupe_hash',
        'classified_by',
        'classified_at',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'posted_by',
        'posted_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'value_date' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'running_balance' => 'decimal:2',
        'needs_review' => 'boolean',
        'classified_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    /**
     * Hash that makes a statement line unique: the same line imported twice is skipped, while two
     * genuinely identical lines in one file stay distinct through the occurrence counter.
     */
    public static function makeDedupeHash(int $bankAccountId, string $date, ?string $reference, float $debit, float $credit, ?string $narration, int $occurrence = 1): string
    {
        return hash('sha256', implode('|', [
            $bankAccountId,
            $date,
            mb_strtolower(trim((string) $reference)),
            number_format($debit, 2, '.', ''),
            number_format($credit, 2, '.', ''),
            mb_strtolower(trim((string) $narration)),
            $occurrence,
        ]));
    }

    public function getDirectionAttribute(): string
    {
        return (float) $this->credit > 0 ? 'credit' : 'debit';
    }

    public function getAmountAttribute(): float
    {
        return (float) $this->credit > 0 ? (float) $this->credit : (float) $this->debit;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    /**
     * A posted or reconciled line is part of the books and cannot be edited, only reversed.
     */
    public function isLocked(): bool
    {
        return in_array($this->status, [self::STATUS_POSTED, self::STATUS_RECONCILED], true);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'import_id');
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function alias(): BelongsTo
    {
        return $this->belongsTo(BankPartyAlias::class, 'bank_party_alias_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'company_id');
    }

    public function counterparty(): MorphTo
    {
        return $this->morphTo();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BankEntryCategory::class, 'bank_entry_category_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function journalEntryDetail(): BelongsTo
    {
        return $this->belongsTo(JournalEntryDetail::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(BankEntryApproval::class)->orderBy('acted_at')->orderBy('id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(BankEntryAuditLog::class)->latest('created_at')->latest('id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function cheques(): HasMany
    {
        return $this->hasMany(BankCheque::class);
    }

    public function reconciliationItems(): HasMany
    {
        return $this->hasMany(BankReconciliationItem::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function classifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'classified_by');
    }
}
