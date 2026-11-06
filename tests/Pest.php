<?php

use App\Models\Event;
use App\Models\Seat;
use App\Services\SeatImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Load');

pest()->extend(TestCase::class)->in('Concurrency');

/**
 * The event of a test, with the real hall. Its slug is fixed so paths can be
 * written out.
 */
function seatedEvent(): Event
{
    return Event::query()->where('slug', 'test')->first() ?? hallFor(Event::factory()->create(['slug' => 'test']));
}

/**
 * A second event, in the same hall, sharing every seat code with the first.
 *
 * @param  array<string, mixed>  $attributes
 */
function otherEvent(array $attributes = []): Event
{
    return hallFor(Event::factory()->create($attributes));
}

function hallFor(Event $event): Event
{
    app(SeatImporter::class)->import($event, config('seating.seat_data_path'));

    return $event->refresh();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function setEvent(array $attributes): Event
{
    return tap(seatedEvent())->update($attributes);
}

function seat(string $code): Seat
{
    return seatedEvent()->seats()->where('code', $code)->firstOrFail();
}
