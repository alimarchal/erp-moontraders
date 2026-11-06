<?php

namespace App\Models;

use App\Traits\UserTracking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * One month's salary for one company (distribution or Moon Traders). draft -> pending -> approved
 * (posted to the ledger) -> paid.
 */
class PayrollRun extends Model
{
    use HasFactory, SoftDeletes, UserTracking;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'uuid', 'run_number', 'period_month', 'supplier_id', 'company_id', 'status', 'total_earnings', 'total_deductions', 'total_net',
        'notes', 'submitted_by', 'submitted_at', 'checked_by', 'checked_at', 'rejection_reason', 'journal_entry_id', 'paid_method',
        'paid_bank_account_id', 'paid_on', 'paid_by', 'payment_journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date', 'paid_on' => 'date', 'submitted_at' => 'datetime', 'checked_at' => 'datetime',
            'total_earnings' => 'decimal:2', 'total_deductions' => 'decimal:2', 'total_net' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (PayrollRun $run) => $run->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return Str::isUuid((string) $value) ? parent::resolveRouteBinding($value, $field) : null;
    }

    /**
     * Records a user may see: admins and holders of the "all" permission see everything, holders of the
     * "company" permission see their own company's, everyone else only what they created themselves.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAllSupplierAccess() || $user->can('payroll-view-all')) {
            return $query;
        }

        if ($user->can('payroll-view-company')) {
            return $query->where('supplier_id', $user->supplier_id ?? 0);
        }

        return $query->where('created_by', $user->id);
    }

    public function canBeSeenBy(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft', self::STATUS_PENDING => 'Pending approval', self::STATUS_APPROVED => 'Approved',
            self::STATUS_PAID => 'Paid', self::STATUS_REJECTED => 'Rejected',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst($this->status);
    }

    /** Slips can be changed until the run is sent to the checker (and again after a rejection). */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
    }

    public function ownerLabel(): string
    {
        return (string) ($this->supplier?->supplier_name ?? $this->company?->company_name ?? 'Moon Traders');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function slips(): HasMany
    {
        return $this->hasMany(PayrollSlip::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function paymentJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'payment_journal_entry_id');
    }

    public function paidBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'paid_bank_account_id');
    }
}
