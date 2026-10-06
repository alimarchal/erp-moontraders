<?php

namespace App\Models;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'title',
        'type',
        'status',
        'supplier_id',
        'description',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'review_remarks',
    ];

    protected function casts(): array
    {
        return [
            'type' => TicketType::class,
            'status' => TicketStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TicketItem::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TicketHistory::class)->orderBy('id');
    }

    public function isPending(): bool
    {
        return $this->status === TicketStatus::Pending;
    }

    /**
     * Admins see every ticket, a company (supplier) user sees only their own
     * company's tickets, anyone else sees the tickets they raised.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isTicketAdmin()) {
            return $query;
        }

        if ($user->supplier_id) {
            return $query->where('supplier_id', $user->supplier_id);
        }

        return $query->where('created_by', $user->id);
    }

    public function canBeSeenBy(User $user): bool
    {
        return self::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }
}
