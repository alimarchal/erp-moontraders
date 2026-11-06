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
 * A (usually month-end) payment to a shop that clears one or more of its credit vouchers.
 */
class ExpenseVendorPayment extends Model
{
    use HasFactory, SoftDeletes, UserTracking;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'uuid', 'payment_number', 'payment_date', 'expense_vendor_id', 'supplier_id', 'company_id', 'method', 'bank_account_id',
        'amount', 'reference', 'narration', 'status', 'checked_by', 'checked_at', 'rejection_reason', 'journal_entry_id',
    ];

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'amount' => 'decimal:2', 'checked_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (ExpenseVendorPayment $payment) => $payment->uuid ??= (string) Str::uuid());
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
        if ($user->hasAllSupplierAccess() || $user->can('expense-vendor-payment-view-all')) {
            return $query;
        }

        if ($user->can('expense-vendor-payment-view-company')) {
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
        return [self::STATUS_PENDING => 'Pending approval', self::STATUS_APPROVED => 'Approved', self::STATUS_REJECTED => 'Rejected'];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst($this->status);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(ExpenseVendor::class, 'expense_vendor_id')->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ExpenseVendorPaymentAllocation::class);
    }
}
