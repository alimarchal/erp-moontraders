<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralLedgerEntry extends Model
{
    protected $table = 'vw_general_ledger';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'journal_entry_id';

    protected $guarded = [];

    protected $casts = [
        'entry_date' => 'datetime',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'fx_rate_to_base' => 'decimal:6',
    ];
}
