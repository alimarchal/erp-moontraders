<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerEmployeeAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_number',
        'customer_id',
        'employee_id',
        'opened_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'opened_date' => 'date',
    ];

    /**
     * Relationships
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CustomerEmployeeAccountTransaction::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scopes
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get balance for this account
     */
    public function getBalanceAttribute(): float
    {
        return $this->transactions()
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
            ->value('balance') ?? 0.0;
    }

    /**
     * Static method to get balance for specific customer-employee pair
     */
    public static function getBalance(int $customerId, int $employeeId): float
    {
        $account = self::where('customer_id', $customerId)
            ->where('employee_id', $employeeId)
            ->first();

        if (! $account) {
            return 0.0;
        }

        return $account->balance;
    }

    /**
     * Generate next account number
     */
    public static function generateAccountNumber(): string
    {
        $lastAccount = self::orderBy('id', 'desc')->first();
        $nextNumber = $lastAccount ? ($lastAccount->id + 1) : 1;

        return 'ACC-'.str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
