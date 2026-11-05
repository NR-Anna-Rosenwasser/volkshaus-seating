<?php

use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\MailLog;
use App\Models\Order;
use App\Models\Seat;
use Database\Seeders\SeatSeeder;
use Illuminate\Support\Facades\Process;

// Row locks only mean something between real connections on committed data,
// so this runs without the transaction the Feature tests are wrapped in.
beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed(SeatSeeder::class);
});

afterEach(function () {
    Seat::query()->update(['attendee_id' => null]);
    AuditLog::query()->delete();
    MailLog::query()->delete();
    Attendee::query()->delete();
    Order::query()->delete();
    Seat::query()->delete();
});

function saveInParallel(array $requests): array
{
    $startAt = microtime(true) + 2;

    $processes = array_map(fn (array $request) => Process::path(base_path())
        ->env(['APP_ENV' => 'testing', 'DB_DATABASE' => 'testing', 'CACHE_STORE' => 'array', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync'])
        ->start(['php', 'tests/Concurrency/save-seats.php', (string) $request['order'], (string) $startAt, implode(',', $request['seats'])]),
        $requests);

    return array_map(function ($process): string {
        $result = $process->wait();

        expect($result->errorOutput())->toBe('');

        return trim($result->output());
    }, $processes);
}

it('lets exactly one of two overlapping saves win', function (int $round) {
    $first = Order::factory()->create();
    $second = Order::factory()->create();
    Attendee::factory()->count(2)->for($first)->create();
    Attendee::factory()->count(2)->for($second)->create();

    // Opposite order on purpose: this deadlocks without the fixed lock order.
    $results = saveInParallel([
        ['order' => $first->id, 'seats' => ['P-5-1', 'P-5-2']],
        ['order' => $second->id, 'seats' => ['P-5-3', 'P-5-2']],
    ]);

    expect($results)->toEqualCanonicalizing(['saved', 'conflict'])
        ->and(Seat::whereNotNull('attendee_id')->count())->toBe(2);

    $winner = $results[0] === 'saved' ? $first : $second;

    expect($winner->seats()->count())->toBe(2);
})->with([1, 2, 3]);
