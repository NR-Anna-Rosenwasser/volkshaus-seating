<?php

namespace App\Models;

use App\Enums\AttendeeStatus;
use Database\Factories\AttendeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'order_id', 'hievents_attendee_id', 'hievents_product_id',
    'first_name', 'last_name', 'status', 'needs_seat',
])]
class Attendee extends Model
{
    /** @use HasFactory<AttendeeFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'status' => AttendeeStatus::class,
            'needs_seat' => 'boolean',
        ];
    }

    public function scopeWithoutSeat(Builder $query): void
    {
        $query->where('status', AttendeeStatus::Active)->where('needs_seat', true)->whereDoesntHave('seat');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seat(): HasOne
    {
        return $this->hasOne(Seat::class);
    }
}
