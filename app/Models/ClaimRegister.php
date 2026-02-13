<?php

namespace App\Models;

use App\Traits\UserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClaimRegister extends Model
{
    /** @use HasFactory<\Database\Factories\ClaimRegisterFactory> */
    use HasFactory, SoftDeletes, UserTracking;


    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
