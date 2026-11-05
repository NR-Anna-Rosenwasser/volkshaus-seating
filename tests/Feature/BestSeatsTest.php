<?php

use App\Enums\BlockReason;
use App\Models\Attendee;
use App\Models\Seat;
use App\Services\BestSeats;
use Database\Seeders\SeatSeeder;

beforeEach(function () {
    $this->seed(SeatSeeder::class);
});

function bestSeats(int $count, array $own = [], array $nearSeatIds = []): array
{
    return app(BestSeats::class)->find($count, $own, $nearSeatIds);
}

function fillSeats(iterable $ids): void
{
    Seat::whereIn('id', $ids)->update(['block_reason' => BlockReason::Other]);
}

/**
 * Leaves only the given seats free.
 */
function onlyFree(array $ids): void
{
    Seat::whereNotIn('id', $ids)->whereNull('block_reason')->update(['block_reason' => BlockReason::Other]);
}

it('starts in the centre of the front row', function () {
    expect(bestSeats(2))->toEqualCanonicalizing(['P-1-13', 'P-1-14']);
});

it('keeps a group together in one row and segment', function () {
    $seats = Seat::findMany(bestSeats(6));

    expect($seats->pluck('row')->unique())->toHaveCount(1)
        ->and($seats->pluck('segment')->unique())->toHaveCount(1);

    $numbers = $seats->pluck('number')->sort()->values();

    expect($numbers->last() - $numbers->first())->toBe(5);
});

it('moves back when the front row is full', function () {
    fillSeats(Seat::where('section', 'parterre')->where('row', 1)->pluck('id'));

    expect(Seat::findMany(bestSeats(2))->pluck('row')->unique()->all())->toBe([2]);
});

it('does not treat seats across a pillar as neighbours', function () {
    // Seats 3 and 4 of row 17 have a pillar between them.
    onlyFree(['P-17-3', 'P-17-4', 'P-16-20', 'P-16-21']);

    expect(bestSeats(2))->toEqualCanonicalizing(['P-16-20', 'P-16-21']);
});

it('skips taken and blocked seats', function () {
    fillSeats(['P-1-13']);

    expect(bestSeats(2))->not->toContain('P-1-13');
});

it('fills obstructed seats last', function () {
    onlyFree(['P-18-1', 'P-18-2', 'GL-1-20', 'GL-1-21']);

    expect(bestSeats(2))->toEqualCanonicalizing(['GL-1-20', 'GL-1-21']);
});

it('counts a group\'s own seats as free', function () {
    $attendee = Attendee::factory()->create();
    Seat::find('P-1-13')->update(['attendee_id' => $attendee->id]);

    expect(bestSeats(1))->not->toBe(['P-1-13'])
        ->and(bestSeats(1, ['P-1-13']))->toBe(['P-1-13']);
});

it('is deterministic', function () {
    expect(bestSeats(5))->toBe(bestSeats(5));
});

it('splits into 2 + 2 rather than leaving someone alone', function () {
    onlyFree(['P-5-1', 'P-5-2', 'P-5-3', 'P-5-10', 'P-6-1', 'P-6-2']);

    $rows = Seat::findMany(bestSeats(4))->groupBy('row')->map->count()->all();

    expect($rows)->toEqualCanonicalizing([5 => 2, 6 => 2]);
});

it('prefers parts in neighbouring rows', function () {
    onlyFree(['P-5-1', 'P-5-2', 'P-6-1', 'P-6-2', 'P-20-13', 'P-20-14']);

    expect(Seat::findMany(bestSeats(4))->pluck('row')->unique()->sort()->values()->all())->toBe([5, 6]);
});

it('falls back to single seats', function () {
    onlyFree(['P-1-1', 'P-3-5', 'P-7-9', 'GM-1-1', 'GL-1-20']);

    expect(bestSeats(5))->toHaveCount(5);
});

it('returns nothing when too few seats are free', function () {
    onlyFree(['P-1-1']);

    expect(bestSeats(2))->toBe([]);
});

it('does not strand a single seat when the group can sit at the end of a stretch', function () {
    onlyFree(['P-5-5', 'P-5-6', 'P-5-7', 'P-5-8', 'P-5-9']);

    $numbers = Seat::findMany(bestSeats(3))->pluck('number')->sort()->values()->all();

    expect($numbers)->not->toBe([6, 7, 8]);
});

it('moves a row back rather than strand a seat', function () {
    onlyFree(['P-5-5', 'P-5-6', 'P-5-7', 'P-6-5', 'P-6-6', 'P-6-7', 'P-6-8']);

    // Three fills row 5 exactly. Two would strand its third seat, one row 6 does not.
    expect(Seat::findMany(bestSeats(3))->pluck('row')->unique()->all())->toBe([5])
        ->and(Seat::findMany(bestSeats(2))->pluck('row')->unique()->all())->toBe([6]);
});

it('prefers a clear seat over avoiding a stranded seat', function () {
    $clear = Seat::where('section', 'parterre')->whereJsonDoesntContain('flags', 'obstructed')->where('row', 8)->orderBy('number')->limit(2)->pluck('id');
    $obstructed = Seat::whereJsonContains('flags', 'obstructed')->limit(6)->pluck('id');
    onlyFree([...$clear, ...$obstructed]);

    expect($clear)->toContain(bestSeats(1)[0]);
});

it('stays close to seats the group already has', function () {
    fillSeats(['P-12-7', 'P-12-8']);

    $seats = bestSeats(1, nearSeatIds: ['P-12-7', 'P-12-8']);

    expect(Seat::find($seats[0])->row)->toBe(12)
        ->and($seats[0])->toBeIn(['P-12-6', 'P-12-9']);
});
