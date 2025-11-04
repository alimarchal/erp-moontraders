<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountBalance extends Model
{
    protected $table = 'vw_account_balances';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'account_id';

    protected $guarded = [];

    protected $casts = [
        'total_debits' => 'decimal:2',
        'total_credits' => 'decimal:2',
        'balance' => 'decimal:2',
        'is_group' => 'boolean',
        'is_active' => 'boolean',
    ];
}
