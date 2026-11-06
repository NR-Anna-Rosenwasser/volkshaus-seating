<?php

use App\Models\Seat;

$missing = fn () => ! is_file(config('seating.seat_data_path'));
$reason = 'database/data/volkshaus-theatersaal-seats.json is not in the repo yet.';

it('seeds the whole venue', function () {
    seatedEvent();

    expect(Seat::count())->toBe(1139)
        ->and(Seat::distinct('id')->count('id'))->toBe(1139)
        ->and(Seat::sellable()->count())->toBe(1115)
        ->and(Seat::where('section', 'parterre')->count())->toBe(642);
})->skip($missing, $reason);

it('flags the seats the plan expects', function (string $flag, int $count) {
    seatedEvent();

    expect(Seat::whereJsonContains('flags', $flag)->count())->toBe($count);
})->with([
    ['obstructed', 81],
    ['wheelchair', 8],
    ['foh_var2', 16],
])->skip($missing, $reason);

it('seeds the same result twice', function () {
    seatedEvent();
    seatedEvent();

    expect(Seat::count())->toBe(1139)
        ->and(Seat::sellable()->count())->toBe(1115);
})->skip($missing, $reason);
