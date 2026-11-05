<?php

namespace App\Models;

use App\Enums\AttendeeStatus;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'hievents_order_id', 'hievents_public_id', 'short_id',
    'email', 'first_name', 'last_name', 'locale', 'status',
    'token_hash', 'seats_confirmed_at', 'reminders_sent', 'last_reminder_at',
    'needs_wheelchair', 'wheelchair_notified_at', 'created_at',
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
            'needs_wheelchair' => 'boolean',
            'wheelchair_notified_at' => 'datetime',
            'reminders_sent' => 'integer',
        ];
    }

    /**
     * Serialises all changes to one order until the transaction ends. Seat
     * rows are locked after this, always in id order.
     */
    public static function lockByHieventsId(string $hieventsOrderId): void
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["hievents-order-{$hieventsOrderId}"]);
    }

    /**
     * Replaces the personal link, so an older one stops working. Only the
     * hash is stored: the returned token cannot be recovered later.
     */
    public function issueToken(): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $this->update(['token_hash' => hash('sha256', $token)]);

        return $token;
    }

    public function seatsNeeded(): int
    {
        return $this->attendees()
            ->where('status', AttendeeStatus::Active)
            ->where('needs_seat', true)
            ->count();
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
