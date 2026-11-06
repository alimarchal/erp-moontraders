<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseVoucherAttachment extends Model
{
    protected $fillable = ['expense_voucher_id', 'file_name', 'file_path', 'file_type', 'file_size', 'created_by'];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(ExpenseVoucher::class, 'expense_voucher_id');
    }
}
