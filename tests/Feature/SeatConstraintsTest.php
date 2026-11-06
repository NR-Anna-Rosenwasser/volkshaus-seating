<?php

use App\Enums\BlockReason;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Seat;
use App\Services\SeatImporter;
use Illuminate\Database\QueryException;

beforeEach(fn () => app(SeatImporter::class)->import(Event::factory()->create(), base_path('tests/Fixtures/seats.json')));

it('refuses to give one attendee two seats', function () {
    $attendee = Attendee::factory()->create();
    Seat::firstWhere('code', 'P-1-1')->update(['attendee_id' => $attendee->id]);

    Seat::firstWhere('code', 'P-1-2')->update(['attendee_id' => $attendee->id]);
})->throws(QueryException::class, 'seats_attendee_id_unique');

it('refuses to assign a blocked seat', function () {
    Seat::firstWhere('code', 'P-4-26')->update(['attendee_id' => Attendee::factory()->create()->id]);
})->throws(QueryException::class, 'seats_blocked_or_assigned_check');

it('refuses to block an assigned seat', function () {
    $seat = Seat::firstWhere('code', 'P-1-1');
    $seat->update(['attendee_id' => Attendee::factory()->create()->id]);

    $seat->update(['block_reason' => BlockReason::Press]);
})->throws(QueryException::class, 'seats_blocked_or_assigned_check');
