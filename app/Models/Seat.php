<?php

namespace App\Models;

use App\Enums\AssignedBy;
use App\Enums\BlockReason;
use App\Enums\SeatState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['block_reason', 'block_note', 'attendee_id', 'assigned_at', 'assigned_by'])]
class Seat extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'row' => 'integer',
            'number' => 'integer',
            'segment' => 'integer',
            'x' => 'float',
            'y' => 'float',
            'flags' => 'array',
            'block_reason' => BlockReason::class,
            'assigned_at' => 'datetime',
            'assigned_by' => AssignedBy::class,
        ];
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    /**
     * Seats that can hold an attendee, whether currently free or not.
     */
    public function scopeSellable(Builder $query): void
    {
        $query->whereNull('block_reason');
    }

    public function state(): SeatState
    {
        return match (true) {
            $this->block_reason !== null => SeatState::Blocked,
            $this->attendee_id !== null => SeatState::Taken,
            default => SeatState::Free,
        };
    }

    public function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->flags ?? [], true);
    }
}
