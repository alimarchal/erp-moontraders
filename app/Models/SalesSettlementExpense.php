<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesSettlementExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_settlement_id',
        'expense_account_id',
        'amount',
        'expense_date',
        'description',
    ];

    public function salesSettlement()
    {
        return $this->belongsTo(SalesSettlement::class);
    }

    public function expenseAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    // Alias for consistency if needed, or update controller to use expenseAccount
    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }
}
