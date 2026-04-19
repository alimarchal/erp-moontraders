<?php

namespace App\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    protected $fillable = [
        'currency_code',
        'currency_name',
        'currency_symbol',
        'exchange_rate',
        'is_base_currency',
        'is_active',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:6',
        'is_base_currency' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function chartOfAccounts()
    {
        return $this->hasMany(ChartOfAccount::class);
    }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }
}
