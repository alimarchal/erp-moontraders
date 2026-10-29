<?php

namespace App\Models;

use App\Services\ExpenseVoucherAttachmentService;
use App\Traits\UserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseVoucher extends Model
{
    use HasFactory, SoftDeletes, UserTracking;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** Expense account that general (Moon Traders) vouchers default to. */
    public const DEFAULT_ACCOUNT_CODE = '5201';

    protected $fillable = [
        'voucher_number',
        'voucher_date',
        'supplier_id',
        'company_id',
        'party_name',
        'expense_account_id',
        'amount',
        'narration',
        'attachment',
        'status',
        'submitted_by',
        'submitted_at',
        'checked_by',
        'checked_at',
        'rejection_reason',
        'journal_entry_id',
        'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'voucher_date' => 'date',
            'amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'checked_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING => 'Pending approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
        ];
    }

    /**
     * Protected URL of the attachment (the file itself is on a private disk).
     */
    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment && ExpenseVoucherAttachmentService::enabled() ? route('expense-vouchers.attachment', $this) : null;
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst($this->status);
    }

    /**
     * The maker may change a voucher until it is waiting for, or has passed, the checker.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** Moon Traders itself (companies table), for an expense that belongs to no distribution. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
