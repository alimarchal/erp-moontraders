<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    /** @use HasFactory<\Database\Factories\AccountingPeriodFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Helper to return map of statuses for forms.
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_OPEN => 'Open',
            self::STATUS_CLOSED => 'Closed',
            self::STATUS_ARCHIVED => 'Archived',
        ];
    }

    /**
     * Determine if the period is currently active.
     */
    public function isCurrent(): bool
    {
        $today = now()->toDateString();

        return $this->status === self::STATUS_OPEN
            && $this->start_date->toDateString() <= $today
            && $this->end_date->toDateString() >= $today;
    }
}
