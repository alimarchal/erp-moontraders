<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceiptNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'grn_number',
        'receipt_date',
        'supplier_id',
        'warehouse_id',
        'purchase_order_id',
        'supplier_invoice_number',
        'supplier_invoice_date',
        'total_quantity',
        'total_amount',
        'tax_amount',
        'freight_charges',
        'other_charges',
        'grand_total',
        'status',
        'received_by',
        'verified_by',
        'posted_at',
        'reversed_at',
        'reversed_by',
        'journal_entry_id',
        'notes',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'supplier_invoice_date' => 'date',
        'total_quantity' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'freight_charges' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptNoteItem::class, 'grn_id')->orderBy('line_no');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(\App\Models\PaymentGrnAllocation::class, 'grn_id');
    }

    public function payments()
    {
        return $this->belongsToMany(\App\Models\SupplierPayment::class, 'payment_grn_allocations', 'grn_id', 'supplier_payment_id')
            ->withPivot('allocated_amount');
    }

    public function getPaymentStatusAttribute(): string
    {
        $totalPaid = $this->paymentAllocations()
            ->whereHas('payment', function ($q) {
                $q->where('status', 'posted');
            })
            ->sum('allocated_amount');

        if ($totalPaid == 0) {
            return 'unpaid';
        } elseif ($totalPaid >= $this->grand_total) {
            return 'paid';
        } else {
            return 'partial';
        }
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->paymentAllocations()
            ->whereHas('payment', function ($q) {
                $q->where('status', 'posted');
            })
            ->sum('allocated_amount');
    }

    public function getBalanceAttribute(): float
    {
        return $this->grand_total - $this->total_paid;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function scopeReceiptDateFrom($query, $date)
    {
        return $query->whereDate('receipt_date', '>=', $date);
    }

    public function scopeReceiptDateTo($query, $date)
    {
        return $query->whereDate('receipt_date', '<=', $date);
    }

    public function scopePaymentStatus($query, $status)
    {
        if ($status === 'unpaid') {
            return $query->whereDoesntHave('paymentAllocations', function ($q) {
                $q->whereHas('payment', function ($subQ) {
                    $subQ->where('status', 'posted');
                });
            });
        } elseif ($status === 'paid') {
            return $query->whereHas('paymentAllocations', function ($q) {
                $q->whereHas('payment', function ($subQ) {
                    $subQ->where('status', 'posted');
                });
            })
                ->whereRaw('grand_total <= (SELECT COALESCE(SUM(allocated_amount), 0) FROM payment_grn_allocations WHERE grn_id = goods_receipt_notes.id AND supplier_payment_id IN (SELECT id FROM supplier_payments WHERE status = \'posted\'))');
        } elseif ($status === 'partial') {
            return $query->whereHas('paymentAllocations', function ($q) {
                $q->whereHas('payment', function ($subQ) {
                    $subQ->where('status', 'posted');
                });
            })
                ->whereRaw('grand_total > (SELECT COALESCE(SUM(allocated_amount), 0) FROM payment_grn_allocations WHERE grn_id = goods_receipt_notes.id AND supplier_payment_id IN (SELECT id FROM supplier_payments WHERE status = \'posted\'))');
        }

        return $query;
    }
}
