<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGrnAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_payment_id',
        'grn_id',
        'allocated_amount',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
    ];

    public function payment()
    {
        return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id');
    }

    public function grn()
    {
        return $this->belongsTo(GoodsReceiptNote::class, 'grn_id');
    }
}
