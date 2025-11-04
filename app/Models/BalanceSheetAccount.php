<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BalanceSheetAccount extends Model
{
    protected $table = 'vw_balance_sheet';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'account_id';

    protected $guarded = [];

    protected $casts = [
        'balance' => 'decimal:2',
    ];
}
