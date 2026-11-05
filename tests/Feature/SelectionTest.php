<?php

use App\Enums\AssignedBy;
use App\Enums\AttendeeStatus;
use App\Enums\BlockReason;
use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Seat;
use Database\Seeders\SeatSeeder;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(SeatSeeder::class);

    config([
        'seating.selection_opens_at' => now()->subDay()->toDateTimeString(),
        'seating.selection_deadline' => now()->addWeek()->toDateTimeString(),
        'seating.contact_email' => 'hallo@example.ch',
    ]);

    $this->order = Order::factory()->create(['first_name' => 'Rita', 'last_name' => 'Muster']);
    Attendee::factory()->count(2)->for($this->order)->create();
    $this->token = $this->order->issueToken();
});

function saveSeats(array $seats, ?string $token = null)
{
    return test()->postJson('/s/'.($token ?? test()->token).'/seats', ['seats' => $seats]);
}

function otherOrderOn(string ...$seatIds): Order
{
    $order = Order::factory()->create();

    foreach ($seatIds as $id) {
        Seat::find($id)->update(['attendee_id' => Attendee::factory()->for($order)->create()->id]);
    }

    return $order;
}

it('stores only a hash of the token', function () {
    expect($this->order->refresh()->token_hash)->toBe(hash('sha256', $this->token))
        ->and(strlen($this->token))->toBe(43);
});

it('puts the same link into every mail', function () {
    expect($this->order->selectionUrl())
        ->toBe($this->order->refresh()->selectionUrl())
        ->toEndWith("/s/{$this->token}");
});

it('addresses a single ticket as du on the seat pages', function () {
    $order = Order::factory()->create();
    Attendee::factory()->for($order)->create();
    $token = $order->issueToken();

    $this->get("/s/{$token}")
        ->assertOk()
        ->assertSeeText('Dein Platz')
        ->assertSeeText('Wähle einen Platz. Du kannst deine Wahl bis')
        ->assertSeeText('Besten Platz für mich finden')
        ->assertSeeText('Platz speichern')
        ->assertSeeText('Deine Wahl')
        ->assertDontSeeText('Eure')
        ->assertDontSeeText('eure')
        ->assertDontSeeText('Ihr ');

    $this->get("/s/{$token}/ticket")
        ->assertSeeText('Du hast noch keinen Platz.')
        ->assertSeeText('Diese Seite zeigt nur deinen Sitzplatz.');
});

it('keeps groups on ihr on the seat pages', function () {
    $this->get("/s/{$this->token}")
        ->assertSeeText('Eure Plätze')
        ->assertSeeText('Wählt 2 Plätze. Ihr könnt eure Wahl bis')
        ->assertSeeText('Eure Wahl');
});

it('shows a friendly page for an unknown link', function () {
    $this->get('/s/nonsense')->assertNotFound()->assertSeeText('Diese Seite gibt es nicht');
});

it('invalidates the old link when a new one is issued', function () {
    $new = $this->order->issueToken();

    $this->get("/s/{$this->token}")->assertNotFound();
    $this->get("/s/{$new}")->assertOk();
});

it('prints a new link and replaces the old one', function () {
    $this->artisan('seating:link', ['order' => $this->order->short_id])->assertSuccessful();

    $this->get("/s/{$this->token}")->assertNotFound();
});

it('limits requests by IP', function () {
    foreach (range(1, 120) as $request) {
        $this->get('/s/nonsense');
    }

    $this->get('/s/nonsense')->assertTooManyRequests();
});

it('shows what the buyer needs', function () {
    $this->get("/s/{$this->token}")
        ->assertOk()
        ->assertSeeText('Rita Muster')
        ->assertSeeText('Wählt 2 Plätze')
        ->assertSeeText('Beste Plätze für uns finden');
});

it('saves seats', function () {
    saveSeats(['P-5-1', 'P-5-2'])->assertOk();

    expect($this->order->seats()->pluck('seats.id')->sort()->values()->all())->toBe(['P-5-1', 'P-5-2'])
        ->and(Seat::find('P-5-1')->assigned_by)->toBe(AssignedBy::Buyer)
        ->and($this->order->refresh()->seats_confirmed_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'seat_assigned')->count())->toBe(2);
});

it('changes seats and frees the old ones', function () {
    saveSeats(['P-5-1', 'P-5-2'])->assertOk();
    saveSeats(['P-5-2', 'P-5-3'])->assertOk();

    expect(Seat::find('P-5-1')->attendee_id)->toBeNull()
        ->and($this->order->seats()->pluck('seats.id')->sort()->values()->all())->toBe(['P-5-2', 'P-5-3'])
        ->and(AuditLog::where('action', 'seat_released')->count())->toBe(1);
});

it('swaps seats within the same selection', function () {
    saveSeats(['P-5-1', 'P-5-2'])->assertOk();
    saveSeats(['P-5-2', 'P-5-1'])->assertOk();

    expect($this->order->seats()->count())->toBe(2);
});

it('counts the buyer\'s own seats as free', function () {
    saveSeats(['P-5-1', 'P-5-2']);

    expect($this->getJson("/s/{$this->token}/availability")->json('seats.P-5-1'))->toBe('free');
});

it('shows other people\'s seats as taken right away', function () {
    otherOrderOn('P-5-1');

    expect($this->getJson("/s/{$this->token}/availability")->json('seats.P-5-1'))->toBe('taken');
});

it('returns a conflict and changes nothing when a seat is gone', function () {
    saveSeats(['P-5-1', 'P-5-2'])->assertOk();
    otherOrderOn('P-6-1');

    saveSeats(['P-6-1', 'P-6-2'])->assertConflict()->assertJson(['conflicts' => ['P-6-1']]);

    expect($this->order->seats()->pluck('seats.id')->sort()->values()->all())->toBe(['P-5-1', 'P-5-2'])
        ->and(Seat::find('P-6-2')->attendee_id)->toBeNull();
});

it('rejects the wrong number of seats', function (array $seats) {
    saveSeats($seats)->assertUnprocessable();

    expect($this->order->seats()->count())->toBe(0);
})->with([
    'too few' => [['P-5-1']],
    'too many' => [['P-5-1', 'P-5-2', 'P-5-3']],
    'none' => [[]],
]);

it('rejects the same seat twice', function () {
    saveSeats(['P-5-1', 'P-5-1'])->assertUnprocessable();
});

it('rejects seats that do not exist', function () {
    saveSeats(['P-5-1', 'P-99-1'])->assertUnprocessable();
});

it('rejects a blocked seat', function () {
    Seat::find('P-5-1')->update(['block_reason' => BlockReason::Press]);

    saveSeats(['P-5-1', 'P-5-2'])->assertConflict();
});

it('rejects wheelchair seats', function () {
    saveSeats(['P-4-26', 'P-4-27'])->assertConflict();
});

it('rejects one free seat between a group\'s own seats', function () {
    saveSeats(['P-5-1', 'P-5-3'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['seats' => 'Bitte rückt zusammen']);

    expect($this->order->seats()->count())->toBe(0);
});

it('counts the group\'s own old seat as a free gap', function () {
    saveSeats(['P-5-1', 'P-5-2'])->assertOk();

    saveSeats(['P-5-1', 'P-5-3'])->assertUnprocessable();

    expect($this->order->seats()->pluck('seats.id')->sort()->values()->all())->toBe(['P-5-1', 'P-5-2']);
});

it('allows a gap that someone else already took', function () {
    otherOrderOn('P-5-2');

    saveSeats(['P-5-1', 'P-5-3'])->assertOk();
});

it('allows a gap that is blocked', function () {
    Seat::find('P-5-2')->update(['block_reason' => BlockReason::Press]);

    saveSeats(['P-5-1', 'P-5-3'])->assertOk();
});

it('allows bigger gaps and separate rows', function (array $seats) {
    saveSeats($seats)->assertOk();
})->with([
    'two seats apart' => [['P-5-1', 'P-5-4']],
    'other rows' => [['P-5-1', 'P-6-3']],
]);

it('does not count seats across a pillar as a gap', function () {
    expect(Seat::find('P-17-3')->segment)->not->toBe(Seat::find('P-17-5')->segment);

    saveSeats(['P-17-3', 'P-17-5'])->assertOk();
});

it('rejects changes after the deadline but shows the seats', function () {
    saveSeats(['P-5-1', 'P-5-2'])->assertOk();

    $this->travel(8)->days();

    saveSeats(['P-5-3', 'P-5-4'])->assertForbidden();

    $this->get("/s/{$this->token}")
        ->assertOk()
        ->assertSeeText('Die Platzwahl ist beendet')
        ->assertSeeText('hallo@example.ch')
        ->assertSeeText('Reihe 5')
        ->assertDontSeeText('Plätze speichern');
});

it('stays closed before opening and without dates', function (array $dates) {
    config($dates);

    saveSeats(['P-5-1', 'P-5-2'])->assertForbidden();
})->with([
    'not open yet' => [['seating.selection_opens_at' => '2099-01-01 10:00']],
    'no deadline' => [['seating.selection_deadline' => null]],
    'no opening' => [['seating.selection_opens_at' => null]],
]);

it('keeps wheelchair orders from choosing', function () {
    $this->order->update(['needs_wheelchair' => true]);

    saveSeats(['P-5-1', 'P-5-2'])->assertForbidden();
    $this->getJson("/s/{$this->token}/suggestion")->assertForbidden();

    $this->get("/s/{$this->token}")
        ->assertSeeText('Wir melden uns bei euch')
        ->assertDontSeeText('Plätze speichern');
});

it('keeps cancelled orders from choosing', function () {
    $this->order->update(['status' => OrderStatus::Cancelled]);

    saveSeats(['P-5-1', 'P-5-2'])->assertForbidden();
    $this->get("/s/{$this->token}")->assertSeeText('storniert');
});

it('only needs seats for active seated attendees', function () {
    $this->order->attendees()->first()->update(['status' => AttendeeStatus::Cancelled]);
    Attendee::factory()->for($this->order)->create(['needs_seat' => false]);

    saveSeats(['P-5-1'])->assertOk();
    $this->get("/s/{$this->token}")->assertSeeText('Wähle einen Platz');
});

it('suggests seats next to each other without saving them', function () {
    $this->getJson("/s/{$this->token}/suggestion")
        ->assertOk()
        ->assertJsonCount(2, 'seats');

    expect($this->order->seats()->count())->toBe(0);
});

it('shows the seat pass', function () {
    config(['seating.event_name' => 'Hässig am Mittwoch', 'seating.event_date' => '2027-02-01 20:00', 'seating.doors_at' => '2027-02-01 19:00']);
    saveSeats(['P-12-7', 'P-12-8']);

    $response = $this->get("/s/{$this->token}/ticket")
        ->assertOk()
        ->assertSeeText('Hässig am Mittwoch')
        ->assertSeeText('Parterre · Reihe 12 · Platz 7')
        ->assertSeeText('Montag, 1. Februar 2027, 20:00 Uhr')
        ->assertSeeText('Einlass nur mit dem Ticket von Hi.Events. Diese Seite zeigt nur euren Sitzplatz.');

    expect(substr_count($response->getContent(), 'is-selected'))->toBe(2);
});

it('keeps personal links out of search engines and referrers', function () {
    $this->get("/s/{$this->token}")
        ->assertSee('name="referrer" content="no-referrer"', false)
        ->assertSee('name="robots" content="noindex"', false);
});
