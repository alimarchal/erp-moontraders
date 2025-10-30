<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CostCenter extends Model
{
    /** @use HasFactory<\Database\Factories\CostCenterFactory> */
    use HasFactory;

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
}
