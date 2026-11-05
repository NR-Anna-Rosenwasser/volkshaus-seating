<?php

namespace App\Services;

use App\Enums\AttendeeStatus;
use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\Order;
use App\Models\Seat;

/**
 * The numbers the admin works with. These contain nothing public-facing
 * beyond totals, but only the admin area shows them.
 */
class SeatStats
{
    /**
     * @return array{
     *     sold: int, assigned: int, unassigned: int, free: int, sellable: int,
     *     blocked: array<string, int>, overbooked: int, ordersWithoutSeats: int
     * }
     */
    public function snapshot(): array
    {
        $sold = Attendee::query()
            ->where('status', AttendeeStatus::Active)
            ->where('needs_seat', true)
            ->whereHas('order', fn ($query) => $query->where('status', OrderStatus::Active));

        $soldCount = (clone $sold)->count();
        $assigned = Seat::whereNotNull('attendee_id')->count();
        $sellable = Seat::sellable()->count();

        // Unseated wheelchair orders go on wheelchair seats, which stay blocked until then.
        $waitingForWheelchairSeats = (clone $sold)
            ->whereDoesntHave('seat')
            ->whereHas('order', fn ($query) => $query->where('needs_wheelchair', true))
            ->count();

        return [
            'sold' => $soldCount,
            'assigned' => $assigned,
            'unassigned' => max($soldCount - $assigned, 0),
            'free' => Seat::sellable()->whereNull('attendee_id')->count(),
            'sellable' => $sellable,
            'blocked' => Seat::whereNotNull('block_reason')
                ->selectRaw('block_reason, count(*) as seats')
                ->groupBy('block_reason')
                ->pluck('seats', 'block_reason')
                ->all(),
            'overbooked' => max($soldCount - $waitingForWheelchairSeats - $sellable, 0),
            'ordersWithoutSeats' => Order::choosingForThemselves()->missingSeats()->count(),
        ];
    }
}
