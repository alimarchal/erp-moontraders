<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payment_number',
        'supplier_id',
        'bank_account_id',
        'payment_date',
        'payment_method',
        'reference_number',
        'amount',
        'description',
        'status',
        'journal_entry_id',
        'posted_at',
        'posted_by',
        'reversed_at',
        'reversed_by',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    // Relationships
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedBy()
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function grnAllocations()
    {
        return $this->hasMany(PaymentGrnAllocation::class);
    }

    public function grns()
    {
        return $this->belongsToMany(GoodsReceiptNote::class, 'payment_grn_allocations', 'supplier_payment_id', 'grn_id')
            ->withPivot('allocated_amount')
            ->withTimestamps();
    }

    // Query Scopes for Spatie Query Builder
    public function scopePaymentDateFrom($query, $date)
    {
        return $query->where('payment_date', '>=', $date);
    }

    public function scopePaymentDateTo($query, $date)
    {
        return $query->where('payment_date', '<=', $date);
    }
}
