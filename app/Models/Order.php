<?php

namespace App\Models;

use App\Enums\AttendeeStatus;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'event_id', 'hievents_order_id', 'hievents_public_id', 'short_id',
    'email', 'first_name', 'last_name', 'locale', 'status',
    'token_hash', 'seats_confirmed_at', 'reminders_sent', 'last_reminder_at',
    'token_version', 'needs_wheelchair', 'wheelchair_notified_at', 'created_at',
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
            'token_version' => 'integer',
            'wheelchair_notified_at' => 'datetime',
            'reminders_sent' => 'integer',
        ];
    }

    /**
     * Serialises all changes to one order until the transaction ends. Seat
     * rows are locked after this, always in id order.
     */
    public static function lockByHieventsId(int $eventId, string $hieventsOrderId): void
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["hievents-order-{$eventId}-{$hieventsOrderId}"]);
    }

    public function lock(): void
    {
        static::lockByHieventsId($this->event_id, $this->hievents_order_id);
    }

    /**
     * Orders that pick their own seats. Wheelchair orders are arranged by hand.
     */
    public function scopeChoosingForThemselves(Builder $query): void
    {
        $query->where('status', OrderStatus::Active)->where('needs_wheelchair', false);
    }

    /**
     * Orders with at least one seated ticket that has no seat yet.
     */
    public function scopeMissingSeats(Builder $query): void
    {
        $query->whereHas('attendees', fn (Builder $attendees) => $attendees->withoutSeat());
    }

    public function scopeWithTicketCount(Builder $query): void
    {
        $query->withCount(['attendees as tickets' => fn (Builder $attendees) => $attendees
            ->where('status', AttendeeStatus::Active)
            ->where('needs_seat', true)]);
    }

    /**
     * Replaces the personal link, so older ones stop working.
     */
    public function issueToken(): string
    {
        $this->update(['token_version' => $this->token_version + 1]);

        return $this->currentToken();
    }

    public function selectionUrl(): string
    {
        return route('selection.show', $this->currentToken());
    }

    /**
     * Derived, not random: several mails carry the same link, yet only its
     * hash is stored. Changing APP_KEY invalidates every link.
     */
    private function currentToken(): string
    {
        $key = (string) config('app.key');
        $mac = hash_hmac('sha256', "selection-link|{$this->id}|{$this->token_version}", $key, true);
        $token = rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
        $hash = hash('sha256', $token);

        if ($this->token_hash !== $hash) {
            $this->update(['token_hash' => $hash]);
        }

        return $token;
    }

    public function seatsNeeded(): int
    {
        return $this->attendees()
            ->where('status', AttendeeStatus::Active)
            ->where('needs_seat', true)
            ->count();
    }

    /**
     * Wording for buyers. An order with one seated ticket is one person, so
     * it gets «du», everything else «ihr».
     */
    public function say(string $solo, string $group): string
    {
        return $this->seatsNeeded() === 1 ? $solo : $group;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
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
