<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    /** @use HasFactory<\Database\Factories\AttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'description',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    // Relationships
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
