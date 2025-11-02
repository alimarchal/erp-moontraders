<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CostCenter extends Model
{
    /** @use HasFactory<\Database\Factories\CostCenterFactory> */
    use HasFactory;

    public const TYPE_COST_CENTER = 'cost_center';
    public const TYPE_PROJECT = 'project';

    protected $fillable = [
        'parent_id',
        'code',
        'name',
        'description',
        'type',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function parent()
    {
        return $this->belongsTo(CostCenter::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(CostCenter::class, 'parent_id');
    }

    public function journalEntryDetails()
    {
        return $this->hasMany(JournalEntryDetail::class);
    }

    /**
     * Map of available cost center types for forms/validation.
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_COST_CENTER => 'Cost Center',
            self::TYPE_PROJECT => 'Project',
        ];
    }
}
