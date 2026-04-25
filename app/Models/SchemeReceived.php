<?php

namespace App\Models;

use App\Traits\UserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchemeReceived extends Model
{
    use HasFactory, SoftDeletes, UserTracking;

    protected $fillable = [
        'supplier_id',
        'category',
        'transaction_date',
        'description',
        'amount',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public static function categoryOptions(): array
    {
        return [
            'tts_received' => 'TTS Received',
            'promo_received' => 'Promo Received',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
