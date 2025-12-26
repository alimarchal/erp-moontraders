<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesSettlementExpense extends Model
{
    protected $fillable = [
        'sales_settlement_id',
        'expense_date',
        'expense_account_id',
        'amount',
        'receipt_number',
        'description',
        'attachment_id',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function salesSettlement(): BelongsTo
    {
        return $this->belongsTo(SalesSettlement::class);
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }

    /**
     * Get commonly used expense accounts for quick selection
     * These are the default expense accounts shown in the settlement form
     *
     * @return array<int, array{account_id: int, account_code: string, label: string}>
     */
    public static function getCommonExpenseAccounts(): array
    {
        return [
            ['account_id' => 72, 'account_code' => '5272', 'label' => 'Toll Tax'],
            ['account_id' => 70, 'account_code' => '5252', 'label' => 'AMR Powder'],
            ['account_id' => 71, 'account_code' => '5262', 'label' => 'AMR Liquid'],
            ['account_id' => 74, 'account_code' => '5292', 'label' => 'Scheme Discount Expense'],
            ['account_id' => 20, 'account_code' => '1161', 'label' => 'Advance Tax'],
            ['account_id' => 73, 'account_code' => '5282', 'label' => 'Food/Salesman/Loader Charges'],
            ['account_id' => 76, 'account_code' => '5223', 'label' => 'Percentage Expense'],
            ['account_id' => 58, 'account_code' => '5221', 'label' => 'Miscellaneous Expenses'],
        ];
    }
}
