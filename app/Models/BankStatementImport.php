<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_account_id',
        'file_name',
        'file_path',
        'period_from',
        'period_to',
        'opening_balance',
        'closing_balance',
        'control_total_debit',
        'control_total_credit',
        'total_rows',
        'imported_rows',
        'skipped_rows',
        'error_rows',
        'status',
        'preview',
        'imported_by',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'control_total_debit' => 'decimal:2',
        'control_total_credit' => 'decimal:2',
        'preview' => 'array',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(BankStatementEntry::class, 'import_id');
    }
}
