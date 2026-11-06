<?php

namespace App\Models;

use App\Services\ExpenseVoucherAttachmentService;
use App\Traits\UserTracking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ExpenseVoucher extends Model
{
    use HasFactory, SoftDeletes, UserTracking;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const TYPE_CASH = 'cash';

    public const TYPE_CREDIT = 'credit';

    public const TYPE_ADVANCE = 'advance';

    public const PAYEE_VENDOR = 'vendor';

    public const PAYEE_EMPLOYEE = 'employee';

    public const PAYEE_OTHER = 'other';

    public const PAID = 'paid';

    public const PARTIAL = 'partial';

    public const UNPAID = 'unpaid';

    /** Expense account that general (Moon Traders) vouchers default to. */
    public const DEFAULT_ACCOUNT_CODE = '5201';

    protected $fillable = [
        'uuid',
        'voucher_number',
        'voucher_date',
        'supplier_id',
        'company_id',
        'expense_vendor_id',
        'employee_id',
        'vehicle_id',
        'expense_category_id',
        'payee_type',
        'payment_type',
        'payment_status',
        'paid_amount',
        'advance_installment',
        'vendor_bill_no',
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

    /**
     * Every voucher gets a random uuid; links use it instead of the running id.
     */
    protected static function booted(): void
    {
        static::creating(function (ExpenseVoucher $voucher) {
            $voucher->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * A link that is not a uuid (an old numeric id, for example) is simply not found.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return Str::isUuid((string) $value) ? parent::resolveRouteBinding($value, $field) : null;
    }

    protected function casts(): array
    {
        return [
            'voucher_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'advance_installment' => 'decimal:2',
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
     * Every file of the voucher (receipts, bills, photos).
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ExpenseVoucherAttachment::class)->orderBy('id');
    }

    /**
     * Protected URL of one attachment (the file itself is on a private disk).
     */
    public function attachmentUrl(ExpenseVoucherAttachment $attachment): ?string
    {
        return ExpenseVoucherAttachmentService::enabled() ? route('expense-vouchers.attachment', [$this, $attachment]) : null;
    }

    /**
     * Vouchers a user may see: admins and holders of the "all" permission see everything, holders of the
     * "company" permission see their own company's, everyone else only the vouchers they made.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAllSupplierAccess() || $user->can('expense-voucher-view-all')) {
            return $query;
        }

        if ($user->can('expense-voucher-view-company')) {
            return $query->where('supplier_id', $user->supplier_id ?? 0);
        }

        return $query->where('created_by', $user->id);
    }

    public function canBeSeenBy(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    public function isCredit(): bool
    {
        return $this->payment_type === self::TYPE_CREDIT;
    }

    public function isAdvance(): bool
    {
        return $this->payment_type === self::TYPE_ADVANCE;
    }

    /** Money still owed to the shop on a credit voucher. */
    public function outstanding(): float
    {
        return $this->isCredit() ? round((float) $this->amount - (float) $this->paid_amount, 2) : 0.0;
    }

    /** @return array<string, string> */
    public static function paymentTypeLabels(): array
    {
        return [self::TYPE_CASH => 'Cash', self::TYPE_CREDIT => 'Credit (pay later)', self::TYPE_ADVANCE => 'Advance salary'];
    }

    public function expenseVendor(): BelongsTo
    {
        return $this->belongsTo(ExpenseVendor::class)->withTrashed();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function advance(): HasOne
    {
        return $this->hasOne(EmployeeAdvance::class);
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
