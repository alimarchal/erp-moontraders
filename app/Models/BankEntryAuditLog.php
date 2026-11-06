<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankEntryAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'bank_statement_entry_id',
        'action',
        'old_values',
        'new_values',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Append one line to an entry's audit trail.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function record(BankStatementEntry $entry, string $action, ?array $old = null, ?array $new = null, ?int $userId = null): self
    {
        return self::create([
            'bank_statement_entry_id' => $entry->id,
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'user_id' => $userId ?? auth()->id(),
            'created_at' => now(),
        ]);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(BankStatementEntry::class, 'bank_statement_entry_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
