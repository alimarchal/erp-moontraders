<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankEntryApproval extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'bank_statement_entry_id',
        'round',
        'level',
        'action',
        'user_id',
        'comment',
        'acted_at',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
        'round' => 'integer',
        'level' => 'integer',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(BankStatementEntry::class, 'bank_statement_entry_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
