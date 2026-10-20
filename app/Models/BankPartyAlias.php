<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankPartyAlias extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'alias_name',
        'canonical_name',
        'company_id',
        'counterparty_type',
        'counterparty_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $alias) {
            $alias->alias_name = self::normalize($alias->alias_name);
        });
    }

    /**
     * Upper-case and collapse whitespace so "Jameel  Ahmed" and "JAMEEL AHMED" are one alias.
     */
    public static function normalize(?string $name): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', (string) $name)));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function counterparty(): MorphTo
    {
        return $this->morphTo();
    }
}
