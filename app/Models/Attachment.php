<?php

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'attachable_type',
        'attachable_id',
        'label',
        'uploaded_by',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'description',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public const LABELS = ['receipt' => 'Receipt', 'cheque' => 'Cheque', 'slip' => 'Deposit slip', 'statement' => 'Statement', 'other' => 'Other'];

    /**
     * True when the file belongs to a sales settlement and is only linked here: it lives on the settlement's disk
     * and must never be deleted from this side.
     */
    public function isSettlementFile(): bool
    {
        return str_starts_with((string) $this->file_path, config('settlement_attachments.root_folder').'/');
    }

    // Relationships
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
