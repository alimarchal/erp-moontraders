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

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptNoteItem::class, 'grn_id')->orderBy('line_no');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }
}
