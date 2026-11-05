<?php

namespace App\Services;

use App\Enums\AttendeeStatus;
use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\Seat;
use App\Support\VenueLayout;
use Illuminate\Support\Str;

/**
 * The lists for the event night.
 */
class SeatExports
{
    public const TYPES = ['platzliste', 'namensliste', 'sperrliste'];

    public function __construct(private readonly VenueLayout $layout) {}

    /**
     * @return array{title: string, headers: list<string>, rows: list<list<string>>}
     */
    public function make(string $type): array
    {
        return match ($type) {
            'platzliste' => $this->bySeat(),
            'namensliste' => $this->byName(),
            'sperrliste' => $this->blocked(),
        };
    }

    /**
     * @return array{title: string, headers: list<string>, rows: list<list<string>>}
     */
    private function bySeat(): array
    {
        $rows = Seat::query()
            ->whereNotNull('attendee_id')
            ->with('attendee.order')
            ->get()
            ->sortBy($this->seatOrder())
            ->map(fn (Seat $seat): array => [
                $this->layout->sectionLabel($seat->section),
                (string) $seat->row,
                (string) $seat->number,
                (string) $seat->attendee->last_name,
                (string) $seat->attendee->first_name,
                (string) $seat->attendee->order->short_id,
            ])
            ->values()
            ->all();

        return ['title' => 'Platzliste', 'headers' => ['Bereich', 'Reihe', 'Platz', 'Nachname', 'Vorname', 'Bestellung'], 'rows' => $rows];
    }

    /**
     * Everyone with a ticket, including those still without a seat: that is
     * who the info desk will have to help.
     *
     * @return array{title: string, headers: list<string>, rows: list<list<string>>}
     */
    private function byName(): array
    {
        $rows = Attendee::query()
            ->where('status', AttendeeStatus::Active)
            ->where('needs_seat', true)
            ->whereHas('order', fn ($query) => $query->where('status', OrderStatus::Active))
            ->with(['seat', 'order'])
            ->get()
            ->sortBy(fn (Attendee $attendee): string => Str::lower(Str::ascii("{$attendee->last_name} {$attendee->first_name}")))
            ->map(fn (Attendee $attendee): array => [
                (string) $attendee->last_name,
                (string) $attendee->first_name,
                $attendee->seat ? $this->layout->seatLabel($attendee->seat) : 'Kein Platz',
                (string) $attendee->order->short_id,
                $attendee->order->needs_wheelchair ? 'Rollstuhl' : '',
            ])
            ->values()
            ->all();

        return ['title' => 'Namensliste', 'headers' => ['Nachname', 'Vorname', 'Platz', 'Bestellung', 'Hinweis'], 'rows' => $rows];
    }

    /**
     * @return array{title: string, headers: list<string>, rows: list<list<string>>}
     */
    private function blocked(): array
    {
        $rows = Seat::query()
            ->whereNotNull('block_reason')
            ->get()
            ->sortBy($this->seatOrder())
            ->map(fn (Seat $seat): array => [
                $this->layout->sectionLabel($seat->section),
                (string) $seat->row,
                (string) $seat->number,
                $seat->block_reason->label(),
                (string) $seat->block_note,
            ])
            ->values()
            ->all();

        return ['title' => 'Sperrliste', 'headers' => ['Bereich', 'Reihe', 'Platz', 'Grund', 'Notiz'], 'rows' => $rows];
    }

    private function seatOrder(): \Closure
    {
        $sections = array_flip(config('seating.section_preference'));

        return fn (Seat $seat): array => [$sections[$seat->section] ?? PHP_INT_MAX, $seat->row, $seat->number];
    }
}
