<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerEmployeeAccountTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_employee_account_id',
        'transaction_date',
        'transaction_type',
        'reference_number',
        'sales_settlement_id',
        'invoice_number',
        'description',
        'debit',
        'credit',
        'payment_method',
        'cheque_number',
        'cheque_date',
        'bank_account_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'cheque_date' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerEmployeeAccount::class, 'customer_employee_account_id');
    }

    public function salesSettlement(): BelongsTo
    {
        return $this->belongsTo(SalesSettlement::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scopes
     */
    public function scopeByDateRange($query, $from, $to)
    {
        return $query->whereBetween('transaction_date', [$from, $to]);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Static helper to calculate balance for an account
     */
    public static function calculateBalance(int $accountId): float
    {
        $result = self::where('customer_employee_account_id', $accountId)
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
            ->first();

        return $result ? (float) $result->balance : 0.0;
    }
}
