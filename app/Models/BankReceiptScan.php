<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A receipt, slip or cheque copy scanned the day it arrives, before its bank line is typed in or imported.
 * It waits in the scan inbox until it is linked to a statement entry, so a paper receipt is never lost.
 */
class BankReceiptScan extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_LINKED = 'linked';

    protected $fillable = [
        'bank_account_id',
        'scan_date',
        'amount',
        'direction',
        'note',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'status',
        'bank_statement_entry_id',
        'uploaded_by',
        'linked_by',
        'linked_at',
    ];

    protected $casts = [
        'scan_date' => 'date',
        'amount' => 'decimal:2',
        'file_size' => 'integer',
        'linked_at' => 'datetime',
    ];

    public function isImage(): bool
    {
        return str_starts_with((string) $this->file_type, 'image/');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(BankStatementEntry::class, 'bank_statement_entry_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
