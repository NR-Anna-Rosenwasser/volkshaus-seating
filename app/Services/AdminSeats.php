<?php

namespace App\Services;

use App\Enums\AssignedBy;
use App\Enums\AttendeeStatus;
use App\Enums\AuditActor;
use App\Enums\BlockReason;
use App\Enums\MailType;
use App\Exceptions\SeatConflict;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Seat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changes made by hand. Allowed after the deadline too, and with the same
 * locking as buyers: the order first, then seats in id order.
 *
 * Wheelchair seats stay blocked until someone sits there, so they are the one
 * kind of blocked seat that can be assigned directly. Any other block has to
 * be lifted first, so reserved seats can't be given away by accident.
 */
class AdminSeats
{
    public function __construct(private readonly BuyerMailer $mailer) {}

    /**
     * @param  list<string>  $seatIds
     * @return array{changed: int, skipped: list<string>}
     */
    public function block(array $seatIds, BlockReason $reason, ?string $note): array
    {
        return DB::transaction(function () use ($seatIds, $reason, $note): array {
            [$assigned, $seats] = $this->lockSeats($seatIds)->partition(fn (Seat $seat): bool => $seat->attendee_id !== null);

            foreach ($seats as $seat) {
                $this->audit('seat_blocked', $seat, $seat->attendee_id, ['block_reason' => $seat->block_reason?->value], ['block_reason' => $reason->value]);
                $seat->update(['block_reason' => $reason, 'block_note' => $note ?: null]);
            }

            return ['changed' => $seats->count(), 'skipped' => $assigned->pluck('id')->all()];
        });
    }

    /**
     * @param  list<string>  $seatIds
     */
    public function unblock(array $seatIds): int
    {
        return DB::transaction(function () use ($seatIds): int {
            $seats = $this->lockSeats($seatIds)->filter(fn (Seat $seat): bool => $seat->block_reason !== null);

            foreach ($seats as $seat) {
                $this->audit('seat_unblocked', $seat, null, ['block_reason' => $seat->block_reason->value]);
                $seat->update(['block_reason' => null, 'block_note' => null]);
            }

            return $seats->count();
        });
    }

    /**
     * Moves one person. Everyone else in the group stays where they are.
     *
     * @throws SeatConflict
     * @throws ValidationException
     */
    public function move(string $fromSeatId, string $toSeatId): Order
    {
        $attendee = Seat::findOrFail($fromSeatId)->attendee ?? throw ValidationException::withMessages(['seat' => 'Dieser Platz ist nicht belegt.']);
        $order = $attendee->order;

        DB::transaction(function () use ($order, $attendee, $fromSeatId, $toSeatId): void {
            Order::lockByHieventsId($order->hievents_order_id);

            $seats = $this->lockSeats([$fromSeatId, $toSeatId])->keyBy('id');
            $from = $seats[$fromSeatId];
            $to = $seats[$toSeatId] ?? throw ValidationException::withMessages(['seat' => 'Diesen Platz gibt es nicht.']);

            if ($from->attendee_id !== $attendee->id) {
                throw ValidationException::withMessages(['seat' => 'Dieser Platz wurde gerade geändert.']);
            }

            $this->assertAssignable($to, [$attendee->id]);

            $from->update($from->vacantAttributes());
            $this->take($to, $attendee->id);
            $this->audit('seat_moved', $to, $attendee->id, ['seat_id' => $from->id]);
        });

        $this->confirm($order);

        return $order;
    }

    /**
     * Makes these exactly the order's seats. May be fewer than it needs, so
     * a group can be seated step by step.
     *
     * @param  list<string>  $seatIds
     *
     * @throws SeatConflict
     * @throws ValidationException
     */
    public function assign(Order $order, array $seatIds): void
    {
        DB::transaction(function () use ($order, $seatIds): void {
            Order::lockByHieventsId($order->hievents_order_id);

            $attendees = $order->attendees()
                ->where('status', AttendeeStatus::Active)
                ->where('needs_seat', true)
                ->orderBy('id')
                ->get();

            if (count($seatIds) > $attendees->count()) {
                throw ValidationException::withMessages(['seats' => "Diese Bestellung braucht nur {$attendees->count()} Plätze."]);
            }

            $seats = Seat::query()
                ->where(fn ($query) => $query
                    ->whereKey($seatIds)
                    ->orWhereIn('attendee_id', $order->attendees()->select('attendees.id')))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if (array_diff($seatIds, $seats->keys()->all()) !== []) {
                throw ValidationException::withMessages(['seats' => 'Diese Plätze gibt es nicht.']);
            }

            foreach ($seatIds as $seatId) {
                $this->assertAssignable($seats[$seatId], $attendees->modelKeys());
            }

            foreach ($seats->except($seatIds) as $seat) {
                $this->audit('seat_released', $seat, $seat->attendee_id);
                $seat->update($seat->vacantAttributes());
            }

            $seated = $seats->only($seatIds)->pluck('attendee_id')->filter()->all();
            $waiting = $attendees->reject(fn (Attendee $attendee): bool => in_array($attendee->id, $seated, true))->values();

            $seats->only($seatIds)
                ->whereNull('attendee_id')
                ->values()
                ->each(function (Seat $seat, int $index) use ($waiting): void {
                    $this->take($seat, $waiting[$index]->id);
                    $this->audit('seat_assigned', $seat, $waiting[$index]->id);
                });
        });

        $this->confirm($order);
    }

    /**
     * Seats that can be given to this order, for the picker.
     *
     * @return Collection<int, Seat>
     */
    public function assignableSeats(Order $order): Collection
    {
        return Seat::query()
            ->where(fn ($query) => $query
                ->where(fn ($free) => $free
                    ->whereNull('attendee_id')
                    ->where(fn ($open) => $open->whereNull('block_reason')->orWhere('block_reason', BlockReason::Wheelchair)))
                ->orWhereIn('attendee_id', $order->attendees()->select('attendees.id')))
            ->orderBy('section')->orderBy('row')->orderBy('number')
            ->get();
    }

    /**
     * Wheelchair orders are arranged in person, so they get no automatic mail.
     */
    private function confirm(Order $order): void
    {
        if (! $order->needs_wheelchair && $order->seats()->exists()) {
            $this->mailer->send($order, MailType::Confirmation);
        }
    }

    /**
     * @param  list<int>  $ownAttendeeIds
     */
    private function assertAssignable(Seat $seat, array $ownAttendeeIds): void
    {
        $blocked = $seat->block_reason !== null && $seat->block_reason !== BlockReason::Wheelchair;
        $taken = $seat->attendee_id !== null && ! in_array($seat->attendee_id, $ownAttendeeIds, true);

        if ($blocked || $taken) {
            throw new SeatConflict([$seat->id]);
        }
    }

    private function take(Seat $seat, int $attendeeId): void
    {
        $seat->update([
            'attendee_id' => $attendeeId,
            'assigned_at' => now(),
            'assigned_by' => AssignedBy::Admin,
            'block_reason' => null,
            'block_note' => null,
        ]);
    }

    /**
     * @param  list<string>  $seatIds
     * @return Collection<int, Seat>
     */
    private function lockSeats(array $seatIds): Collection
    {
        return Seat::whereKey($seatIds)->orderBy('id')->lockForUpdate()->get();
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function audit(string $action, Seat $seat, ?int $attendeeId, ?array $before = null, ?array $after = null): void
    {
        AuditLog::create([
            'actor' => AuditActor::Admin,
            'action' => $action,
            'seat_id' => $seat->id,
            'attendee_id' => $attendeeId,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
