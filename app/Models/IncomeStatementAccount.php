<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomeStatementAccount extends Model
{
    protected $table = 'vw_income_statement';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'account_id';

    protected $guarded = [];

    protected $casts = [
        'balance' => 'decimal:2',
    ];
}
