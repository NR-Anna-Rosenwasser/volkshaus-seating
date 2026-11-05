<?php

namespace App\Services;

use App\Enums\AssignedBy;
use App\Enums\AttendeeStatus;
use App\Enums\AuditActor;
use App\Enums\OrderStatus;
use App\Exceptions\SeatConflict;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Seat;
use App\Support\SelectionWindow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Buyers saving their seats. Conflicts are resolved at commit time by row
 * locks, there are no temporary holds.
 */
class SeatSelection
{
    public function __construct(private readonly SelectionWindow $window) {}

    /**
     * Wheelchair orders are arranged by hand and never choose for themselves.
     */
    public function mayChoose(Order $order): bool
    {
        return $this->window->isOpen()
            && $order->status === OrderStatus::Active
            && ! $order->needs_wheelchair
            && $order->seatsNeeded() > 0;
    }

    /**
     * @param  list<string>  $seatIds
     *
     * @throws SeatConflict
     * @throws ValidationException
     */
    public function save(Order $order, array $seatIds): void
    {
        DB::transaction(function () use ($order, $seatIds): void {
            Order::lockByHieventsId($order->hievents_order_id);

            // Webhooks may have changed the order since the request began.
            $order->refresh();
            $attendees = $order->attendees()
                ->where('status', AttendeeStatus::Active)
                ->where('needs_seat', true)
                ->orderBy('id')
                ->get();

            if (count($seatIds) !== $attendees->count()) {
                throw ValidationException::withMessages(['seats' => 'Die Anzahl Plätze stimmt nicht.']);
            }

            $seats = Seat::query()
                ->where(fn ($query) => $query
                    ->whereIn('id', $seatIds)
                    ->orWhereIn('attendee_id', $order->attendees()->select('attendees.id')))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if (count(array_diff($seatIds, $seats->keys()->all())) > 0) {
                throw ValidationException::withMessages(['seats' => 'Diese Plätze gibt es nicht.']);
            }

            $this->assertAvailable($seats, $seatIds, $attendees->modelKeys());
            $this->assign($order, $seats, $seatIds, $attendees);

            $order->update(['seats_confirmed_at' => now()]);
        });
    }

    /**
     * @param  Collection<string, Seat>  $seats
     * @param  list<string>  $seatIds
     * @param  list<int>  $attendeeIds
     */
    private function assertAvailable(Collection $seats, array $seatIds, array $attendeeIds): void
    {
        $conflicts = collect($seatIds)
            ->reject(function (string $id) use ($seats, $attendeeIds): bool {
                $seat = $seats[$id];

                return $seat->block_reason === null
                    && ($seat->attendee_id === null || in_array($seat->attendee_id, $attendeeIds, true));
            })
            ->values()
            ->all();

        if ($conflicts !== []) {
            throw new SeatConflict($conflicts);
        }
    }

    /**
     * @param  Collection<string, Seat>  $seats
     * @param  list<string>  $seatIds
     * @param  Collection<int, Attendee>  $attendees
     */
    private function assign(Order $order, Collection $seats, array $seatIds, Collection $attendees): void
    {
        foreach ($seats as $seat) {
            if ($seat->attendee_id !== null && ! in_array($seat->id, $seatIds, true)) {
                $this->audit('seat_released', $seat->id, $seat->attendee_id);
                $seat->update(['attendee_id' => null, 'assigned_at' => null, 'assigned_by' => null]);
            }
        }

        $seated = $seats->pluck('attendee_id')->filter()->all();
        $waiting = $attendees->reject(fn ($attendee): bool => in_array($attendee->id, $seated, true))->values();

        collect($seatIds)
            ->reject(fn (string $id): bool => $seats[$id]->attendee_id !== null)
            ->values()
            ->each(function (string $id, int $index) use ($seats, $waiting): void {
                $seats[$id]->update([
                    'attendee_id' => $waiting[$index]->id,
                    'assigned_at' => now(),
                    'assigned_by' => AssignedBy::Buyer,
                ]);
                $this->audit('seat_assigned', $id, $waiting[$index]->id);
            });
    }

    private function audit(string $action, string $seatId, int $attendeeId): void
    {
        AuditLog::create([
            'actor' => AuditActor::Buyer,
            'action' => $action,
            'seat_id' => $seatId,
            'attendee_id' => $attendeeId,
        ]);
    }
}
