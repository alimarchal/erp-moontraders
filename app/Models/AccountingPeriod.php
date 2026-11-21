<?php

namespace App\Models;

use Database\Factories\AccountingPeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    /** @use HasFactory<AccountingPeriodFactory> */
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
     * The period that keeps entries off this day, if any. Periods overlap (a fiscal year and its
     * quarters), so a day is closed when any period covering it is not open, e.g. a closed Q1
     * inside an open fiscal year.
     */
    public static function closedCovering(string $date): ?self
    {
        return self::query()
            ->where('status', '!=', self::STATUS_OPEN)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderByDesc('start_date')
            ->first(['name', 'status']);
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
