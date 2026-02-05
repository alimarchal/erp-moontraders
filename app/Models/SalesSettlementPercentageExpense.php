<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesSettlementPercentageExpense extends Model
{
    protected $fillable = [
        'sales_settlement_id',
        'customer_id',
        'invoice_number',
        'amount',
        'notes',
    ];

    public function salesSettlement()
    {
        return $this->belongsTo(SalesSettlement::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
