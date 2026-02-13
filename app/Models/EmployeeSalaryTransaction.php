<?php

namespace App\Models;

use App\Traits\UserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read float $balance
 */
class EmployeeSalaryTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeSalaryTransactionFactory> */
    use HasFactory, SoftDeletes, UserTracking;

    protected $fillable = [
        'employee_id',
        'supplier_id',
        'transaction_date',
        'reference_number',
        'transaction_type',
        'description',
        'salary_month',
        'period_start',
        'period_end',
        'debit',
        'credit',
        'debit_account_id',
        'credit_account_id',
        'payment_method',
        'cheque_number',
        'cheque_date',
        'bank_account_id',
        'status',
        'sales_settlement_id',
        'employee_salary_id',
        'journal_entry_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'cheque_date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function transactionTypeOptions(): array
    {
        return [
            'Salary' => 'Salary',
            'Advance' => 'Advance',
            'AdvanceRecovery' => 'Advance Recovery',
            'Deduction' => 'Deduction',
            'Bonus' => 'Bonus',
            'Loan' => 'Loan',
            'LoanRecovery' => 'Loan Recovery',
            'Expense' => 'Expense',
            'ExpenseReimbursement' => 'Expense Reimbursement',
            'Shortage' => 'Shortage',
            'ShortageRecovery' => 'Shortage Recovery',
            'Incentive' => 'Incentive',
            'OvertimePay' => 'Overtime Pay',
            'FineDeduction' => 'Fine/Deduction',
            'SalaryPayment' => 'Salary Payment',
            'Adjustment' => 'Adjustment',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'Pending' => 'Pending',
            'Approved' => 'Approved',
            'Paid' => 'Paid',
            'Cancelled' => 'Cancelled',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function paymentMethodOptions(): array
    {
        return [
            'cash' => 'Cash',
            'cheque' => 'Cheque',
            'bank_transfer' => 'Bank Transfer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'credit_account_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function salesSettlement(): BelongsTo
    {
        return $this->belongsTo(SalesSettlement::class);
    }

    public function employeeSalary(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalary::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->debit - (float) $this->credit;
    }

    public function isDebit(): bool
    {
        return (float) $this->debit > 0;
    }

    public function isCredit(): bool
    {
        return (float) $this->credit > 0;
    }
}
