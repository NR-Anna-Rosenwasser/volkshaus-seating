<?php

use App\Enums\AttendeeStatus;
use App\Enums\BlockReason;
use App\Enums\MailType;
use App\Enums\OrderStatus;
use App\Mail\SeatMail;
use App\Models\Attendee;
use App\Models\MailLog;
use App\Models\Order;
use App\Models\Seat;
use App\Models\User;
use App\Services\AutoAssign;
use App\Services\BestSeats;
use App\Services\SeatStats;
use Database\Seeders\SeatSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * One whole event in fast-forward, at real size: about 1,100 tickets in about
 * 500 orders, from selection through auto-assign and exports to the purge.
 */
it('gets a full house through the whole event', function () {
    Mail::fake();
    $this->withoutVite();
    $this->seed(SeatSeeder::class);
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->actingAs(User::factory()->create());

    config([
        'seating.selection_opens_at' => '2026-12-20 10:00',
        'seating.selection_deadline' => '2027-01-25 23:59',
        'seating.event_date' => '2027-02-01 20:00',
        'seating.mail_per_second' => 5,
        'seating.admin_emails' => ['admin@example.ch'],
    ]);
    $this->travelTo('2027-01-02 12:00:00');
    mt_srand(2027);

    // Four press seats on top of the seeded blocks, so that blocks show up as a live change too.
    Seat::whereKey(['P-3-1', 'P-3-2', 'P-3-3', 'P-3-4'])->update(['block_reason' => BlockReason::Press]);

    $sizes = [];
    $bag = [...array_fill(0, 34, 1), ...array_fill(0, 40, 2), ...array_fill(0, 10, 3), ...array_fill(0, 11, 4), ...array_fill(0, 3, 5), ...array_fill(0, 2, 6)];

    while (array_sum($sizes) < 1100 && count($sizes) < 560) {
        $sizes[] = $bag[mt_rand(0, count($bag) - 1)];
    }

    $orders = collect($sizes)->values()->map(function (int $size, int $index): Order {
        $order = Order::factory()->create(['created_at' => now()->subMinutes(5000 - $index), 'needs_wheelchair' => $index % 150 === 149]);
        Attendee::factory()->count($size)->for($order)->create();

        return $order;
    });

    $tickets = Attendee::count();
    expect($tickets)->toBeGreaterThan(1050)->toBeLessThanOrEqual(1107)
        ->and($orders->count())->toBeGreaterThan(400);

    // Two thirds choose through the real endpoint, each in their own way.
    $choosing = $orders->reject->needs_wheelchair->filter(fn (): bool => mt_rand(0, 99) < 66)->values();
    $conflicts = 0;

    foreach ($choosing as $index => $order) {
        $count = $order->seatsNeeded();
        $seats = app(BestSeats::class)->find($count);

        // Every fifth buyer ignores the suggestion and picks a free stretch somewhere.
        if ($index % 5 === 0) {
            $start = Seat::sellable()->whereNull('attendee_id')->inRandomOrder()->first();
            $stretch = Seat::sellable()->whereNull('attendee_id')
                ->where(['section' => $start->section, 'row' => $start->row, 'segment' => $start->segment])
                ->where('number', '>=', $start->number)->orderBy('number')->limit($count)->pluck('id')->all();
            $seats = count($stretch) === $count ? $stretch : $seats;
        }

        $response = $this->postJson('/s/'.$order->issueToken().'/seats', ['seats' => $seats]);
        $conflicts += $response->status() === 409 ? 1 : 0;

        expect($response->status())->toBeIn([200, 409, 422]);
    }

    // A few people change their mind, and some cancel.
    $orders->reject->needs_wheelchair->take(12)->each(function (Order $order): void {
        $order->refresh();
        DB::table('seats')->whereIn('attendee_id', $order->attendees()->select('id'))->update(['attendee_id' => null, 'assigned_at' => null, 'assigned_by' => null]);
        $order->attendees()->update(['status' => AttendeeStatus::Cancelled]);
        $order->update(['status' => OrderStatus::Cancelled]);
    });

    $this->travelTo('2027-01-26 03:00:00');

    $preview = app(AutoAssign::class)->preview()->map->seatIds->all();

    $started = microtime(true);
    $results = app(AutoAssign::class)->run();
    $seconds = microtime(true) - $started;

    expect($results->map->seatIds->all())->toBe($preview)
        ->and($seconds)->toBeLessThan(30);

    // Nobody without a seat except the wheelchair orders, which wait for a person.
    $stats = app(SeatStats::class)->snapshot();
    $wheelchairTickets = Attendee::whereHas('order', fn ($query) => $query->where('needs_wheelchair', true)->where('status', OrderStatus::Active))
        ->where('status', AttendeeStatus::Active)->count();

    expect($results->reject->isSeated())->toBeEmpty()
        ->and($stats['unassigned'])->toBe($wheelchairTickets)
        ->and($stats['ordersWithoutSeats'])->toBe(0)
        ->and($stats['overbooked'])->toBe(0);

    // The database agrees: one seat per person, only sellable seats, only live tickets.
    expect(Seat::whereNotNull('attendee_id')->whereNotNull('block_reason')->count())->toBe(0)
        ->and(DB::table('seats')->whereNotNull('attendee_id')->distinct()->count('attendee_id'))->toBe(Seat::whereNotNull('attendee_id')->count())
        ->and(Seat::whereNotNull('attendee_id')->whereHas('attendee', fn ($query) => $query->where('status', AttendeeStatus::Cancelled))->count())->toBe(0);

    // Almost everyone sits together.
    $split = $results->filter->split->count();
    expect($split)->toBeLessThan($results->count() / 4);

    // The lists for the night have every line.
    $lines = fn (string $list): int => substr_count(trim($this->get("/admin/exporte/{$list}.csv")->assertOk()->streamedContent()), "\n");

    expect($lines('platzliste'))->toBe(Seat::whereNotNull('attendee_id')->count())
        ->and($lines('namensliste'))->toBe($stats['sold'])
        ->and($lines('sperrliste'))->toBe(array_sum($stats['blocked']));

    $this->get('/admin/exporte/namensliste.html')->assertOk();

    // One mail per order for everything that happened.
    expect(MailLog::where('type', MailType::AutoAssigned)->count())->toBe($results->count());
    Mail::assertQueued(SeatMail::class, fn (SeatMail $mail) => $mail->type === MailType::AutoAssigned);

    $this->travelTo('2027-01-31 12:00:00');
    $this->artisan('seating:send-final')->assertSuccessful();

    expect(MailLog::where('type', MailType::Final)->count())->toBe(Order::where('status', OrderStatus::Active)->whereHas('seats')->count());

    // A month later, nobody is left in the database.
    $seated = Seat::whereNotNull('attendee_id')->count();
    $this->travelTo('2027-03-04 12:00:00');
    $this->artisan('seating:purge')->assertSuccessful();

    expect(Order::whereNotNull('email')->count())->toBe(0)
        ->and(Attendee::whereNotNull('last_name')->count())->toBe(0)
        ->and(Seat::whereNotNull('attendee_id')->count())->toBe($seated)
        ->and(Attendee::count())->toBe($tickets);

    fwrite(STDERR, sprintf(
        "\n  Full event: %d orders, %d tickets, %d chose (%d conflicts), auto-assign %d orders in %.1fs, %d split\n",
        $orders->count(), $tickets, $choosing->count(), $conflicts, $results->count(), $seconds, $split,
    ));
});
