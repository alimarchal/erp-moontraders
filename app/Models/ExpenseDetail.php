<?php

namespace App\Models;

use App\Traits\UserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseDetail extends Model
{
    use HasFactory, SoftDeletes, UserTracking;

    protected $fillable = [
        'category',
        'supplier_id',
        'transaction_date',
        'description',
        'amount',
        'vehicle_id',
        'vehicle_type',
        'driver_employee_id',
        'liters',
        'employee_id',
        'employee_no',
        'debit',
        'credit',
        'debit_account_id',
        'credit_account_id',
        'notes',
        'journal_entry_id',
        'posted_at',
        'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'liters' => 'decimal:2',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public static function categoryOptions(): array
    {
        return [
            'stationary' => 'Stationary',
            'tcs' => 'TCS',
            'tonner_it' => 'Tonner & IT',
            'salaries' => 'Salaries',
            'fuel' => 'Fuel',
            'van_work' => 'Van Work',
        ];
    }

    /**
     * Map category to Chart of Account name for debit (expense) side.
     */
    public static function categoryAccountMap(): array
    {
        return [
            'stationary' => 'Print and Stationery',
            'tcs' => 'TCS Expense',
            'tonner_it' => 'Tonner & IT Expense',
            'salaries' => 'Salary',
            'fuel' => 'Fuel Expense',
            'van_work' => 'Van Work Expense',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driverEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_employee_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'credit_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }
}
