<?php

namespace App\Models;

use App\Traits\UserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A shop, workshop or other party Moon Traders pays for its daily expenses. One shared catalogue:
 * any voucher maker can pick a shop or add a new one while raising the voucher.
 */
class ExpenseVendor extends Model
{
    use HasFactory, SoftDeletes, UserTracking;

    /** @var array<string, string> */
    public const CATEGORIES = [
        'shop' => 'Shop',
        'workshop' => 'Workshop / Garage',
        'fuel' => 'Fuel Station',
        'utility' => 'Utility / Service',
        'transport' => 'Transport',
        'rent' => 'Rent / Landlord',
        'other' => 'Other',
    ];

    protected $fillable = [
        'uuid', 'name', 'category', 'phone', 'city', 'address', 'contact_person', 'tax_number',
        'default_expense_account_id', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (ExpenseVendor $vendor) => $vendor->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return Str::isUuid((string) $value) ? parent::resolveRouteBinding($value, $field) : null;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    public function defaultExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_expense_account_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(ExpenseVoucher::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ExpenseVendorPayment::class);
    }

    /** What is still owed to this shop on approved credit vouchers. */
    public function outstanding(): float
    {
        return (float) $this->vouchers()
            ->where('status', ExpenseVoucher::STATUS_APPROVED)
            ->where('payment_type', ExpenseVoucher::TYPE_CREDIT)
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as due')
            ->value('due');
    }
}
