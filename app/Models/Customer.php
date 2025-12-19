<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Supported customer classifications.
     *
     * @var string[]
     */
    public const CHANNEL_TYPES = [
        'General Store',
        'Wholesale',
        'Pharmacy',
        'Bakery',
        'Minimart',
        'Hotel & Accommodation',
        'Petromart',
        '3rd Party',
        'Other',
    ];

    /**
     * Supported priority tiers.
     *
     * @var string[]
     */
    public const CUSTOMER_CATEGORIES = ['A', 'B', 'C', 'D'];

    protected $fillable = [
        'customer_code',
        'customer_name',
        'business_name',
        'phone',
        'email',
        'address',
        'sub_locality',
        'city',
        'state',
        'country',
        'channel_type',
        'customer_category',
        'credit_limit',
        'payment_terms',
        'credit_used',
        // receivable_balance, payable_balance, lifetime_value - calculated dynamically
        'receivable_account_id',
        'payable_account_id',
        'notes',
        'last_sale_date',
        'sales_rep_id',
        'is_active',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'credit_used' => 'decimal:2',
        // receivable_balance, payable_balance, lifetime_value - removed (calculated dynamically)
        'last_sale_date' => 'date',
        'is_active' => 'boolean',
        'payment_terms' => 'integer',
    ];

    // Relationships
    public function receivableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'receivable_account_id');
    }

    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payable_account_id');
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function employeeAccounts(): HasMany
    {
        return $this->hasMany(CustomerEmployeeAccount::class);
    }

    public function accountTransactions(): HasMany
    {
        return $this->hasManyThrough(
            CustomerEmployeeAccountTransaction::class,
            CustomerEmployeeAccount::class
        );
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByChannelType($query, string $channelType)
    {
        return $query->where('channel_type', $channelType);
    }

    public function scopeByCity($query, string $city)
    {
        return $query->where('city', $city);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('customer_category', $category);
    }

    // Helper methods
    public function getAvailableCredit(): float
    {
        return max(0, $this->credit_limit - $this->credit_used);
    }

    public function hasAvailableCredit(float $amount): bool
    {
        return $this->getAvailableCredit() >= $amount;
    }

    public function getNetBalance(): float
    {
        return $this->getReceivableBalanceAttribute();
    }

    /**
     * Get total receivable balance from all employee accounts
     */
    public function getReceivableBalanceAttribute(): float
    {
        return CustomerEmployeeAccountTransaction::whereHas('account', function ($query) {
            $query->where('customer_id', $this->id);
        })
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
            ->value('balance') ?? 0.0;
    }

    /**
     * Get balance with specific employee
     */
    public function getBalanceWithEmployee(int $employeeId): float
    {
        return CustomerEmployeeAccount::getBalance($this->id, $employeeId);
    }

    public function updateCreditUsed(): void
    {
        $this->update([
            'credit_used' => $this->receivable_balance,
        ]);
    }
}
