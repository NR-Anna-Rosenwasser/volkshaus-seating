<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable([
    'hievents_order_id', 'hievents_public_id', 'short_id',
    'email', 'first_name', 'last_name', 'locale', 'status',
    'token_hash', 'seats_confirmed_at', 'reminders_sent', 'last_reminder_at', 'wheelchair_notified_at', 'created_at',
])]
#[Hidden(['token_hash'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'seats_confirmed_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'wheelchair_notified_at' => 'datetime',
            'reminders_sent' => 'integer',
        ];
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    public function seats(): HasManyThrough
    {
        return $this->hasManyThrough(Seat::class, Attendee::class);
    }

    public function mailLogs(): HasMany
    {
        return $this->hasMany(MailLog::class);
    }
}
