<?php

use App\Enums\BlockReason;
use App\Models\Attendee;
use App\Models\Seat;
use App\Services\SeatImporter;

function importFixture(): int
{
    return app(SeatImporter::class)->import(base_path('tests/Fixtures/seats.json'));
}

/**
 * @param  list<array<string, mixed>>  $seats
 */
function seatFile(array $seats): string
{
    $path = tempnam(sys_get_temp_dir(), 'seats');
    file_put_contents($path, json_encode(['seats' => $seats]));

    return $path;
}

it('imports every seat with its geometry and flags', function () {
    expect(importFixture())->toBe(6)
        ->and(Seat::count())->toBe(6);

    $seat = Seat::findOrFail('GL-1-1');

    expect($seat)
        ->section->toBe('galerie-links')
        ->row->toBe(1)
        ->number->toBe(1)
        ->segment->toBe(2)
        ->x->toBe(40.0)
        ->flags->toBe(['obstructed'])
        ->and($seat->hasFlag('obstructed'))->toBeTrue();

    expect(Seat::findOrFail('P-1-2')->flags)->toBe([]);
});

it('applies the default blocks', function () {
    importFixture();

    expect(Seat::findOrFail('P-4-26')->block_reason)->toBe(BlockReason::Wheelchair)
        ->and(Seat::findOrFail('P-23-10')->block_reason)->toBe(BlockReason::Tech);
});

it('leaves foh_var1 and obstructed seats sellable', function () {
    importFixture();

    expect(Seat::findOrFail('P-23-9')->block_reason)->toBeNull()
        ->and(Seat::findOrFail('GL-1-1')->block_reason)->toBeNull()
        ->and(Seat::sellable()->count())->toBe(4);
});

it('keeps admin changes and assignments when imported again', function () {
    importFixture();

    $attendee = Attendee::factory()->create();
    Seat::findOrFail('P-1-1')->update(['attendee_id' => $attendee->id]);
    Seat::findOrFail('P-1-2')->update(['block_reason' => BlockReason::Vip, 'block_note' => 'Gemeinderat']);
    Seat::findOrFail('P-4-26')->update(['block_reason' => null]);

    importFixture();

    expect(Seat::count())->toBe(6)
        ->and(Seat::findOrFail('P-1-1')->attendee_id)->toBe($attendee->id)
        ->and(Seat::findOrFail('P-1-2')->block_reason)->toBe(BlockReason::Vip)
        ->and(Seat::findOrFail('P-1-2')->block_note)->toBe('Gemeinderat')
        ->and(Seat::findOrFail('P-4-26')->block_reason)->toBeNull();
});

it('updates geometry when imported again', function () {
    $seat = ['id' => 'P-1-1', 'section' => 'parterre', 'row' => 1, 'number' => 1, 'segment' => 1, 'x' => 1, 'y' => 2];

    app(SeatImporter::class)->import(seatFile([$seat]));
    app(SeatImporter::class)->import(seatFile([['x' => 9] + $seat]));

    expect(Seat::findOrFail('P-1-1')->x)->toBe(9.0);
});

it('rejects a missing file, duplicate ids and incomplete seats', function (callable $path, string $message) {
    expect(fn () => app(SeatImporter::class)->import($path()))
        ->toThrow(InvalidArgumentException::class, $message);

    expect(Seat::count())->toBe(0);
})->with([
    'missing file' => [fn () => '/nonexistent/seats.json', 'not found'],
    'no seats' => [fn () => seatFile([]), 'has no seats'],
    'duplicate id' => [fn () => seatFile([
        ['id' => 'P-1-1', 'section' => 'parterre', 'row' => 1, 'number' => 1, 'segment' => 1, 'x' => 1, 'y' => 2],
        ['id' => 'P-1-1', 'section' => 'parterre', 'row' => 1, 'number' => 1, 'segment' => 1, 'x' => 1, 'y' => 2],
    ]), 'Duplicate seat ids: P-1-1'],
    'missing field' => [fn () => seatFile([['id' => 'P-1-1']]), 'missing "section"'],
]);
