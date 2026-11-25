<?php

use App\Console\Commands\Reconcile;
use App\Enums\AssignedBy;
use App\Enums\AttendeeStatus;
use App\Enums\BlockReason;
use App\Enums\MailType;
use App\Enums\OrderStatus;
use App\Mail\AdminAlertMail;
use App\Mail\SeatMail;
use App\Mail\WheelchairNotice;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\MailLog;
use App\Models\Order;
use App\Models\Seat;
use App\Services\Hievents\OrderSync;
use Database\Seeders\SeatSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    config([
        'seating.event_id' => '1',
        'seating.seated_product_ids' => ['10'],
        'seating.wheelchair_question_ids' => ['7'],
        'seating.wheelchair_notify_emails' => ['anna@example.ch', 'team@example.ch'],
        'seating.hievents.base_url' => 'https://hi.test',
        'seating.hievents.api_key' => 'key',
        'seating.admin_emails' => ['admin@example.ch'],
    ]);

    $this->seed(SeatSeeder::class);
});

function attendeePayload(int $id, array $overrides = []): array
{
    return [
        'id' => $id, 'order_id' => 100, 'event_id' => 1, 'product_id' => 10, 'product_price_id' => 1,
        'status' => 'ACTIVE', 'first_name' => 'Rita', 'last_name' => 'Muster', 'email' => 'rita@example.ch',
        'question_answers' => [], ...$overrides,
    ];
}

function orderPayload(array $overrides = []): array
{
    return [
        'id' => 100, 'event_id' => 1, 'short_id' => 'o_abc', 'public_id' => 'O-ABC', 'status' => 'COMPLETED',
        'payment_status' => 'NO_PAYMENT_REQUIRED', 'first_name' => 'Rita', 'last_name' => 'Muster',
        'email' => 'rita@example.ch', 'created_at' => '2026-11-05T13:18:01.000000Z',
        'attendees' => [attendeePayload(1), attendeePayload(2)], 'question_answers' => [], ...$overrides,
    ];
}

function wheelchairAnswer(): array
{
    return ['question_id' => 7, 'belongs_to' => 'ORDER', 'answer' => ['Ja'], 'text_answer' => 'Ja', 'order_id' => 100];
}

/**
 * Applies data the way a pull does: an order with its attendees, or a single attendee.
 */
function fromHievents(string $type, array $payload): void
{
    $sync = app(OrderSync::class);

    str_starts_with($type, 'attendee.') ? $sync->syncAttendee($payload) : $sync->syncOrder($payload);
}

function seatAttendee(string $hieventsAttendeeId, string $seatId): void
{
    Seat::findOrFail($seatId)->update([
        'block_reason' => null,
        'attendee_id' => Attendee::where('hievents_attendee_id', $hieventsAttendeeId)->value('id'),
        'assigned_at' => now(),
        'assigned_by' => AssignedBy::Buyer,
    ]);
}

it('creates the order', function () {
    fromHievents('order.created', orderPayload());

    $order = Order::where('hievents_order_id', '100')->sole();

    expect($order)
        ->status->toBe(OrderStatus::Active)
        ->short_id->toBe('o_abc')
        ->email->toBe('rita@example.ch')
        ->token_hash->toHaveLength(64)
        ->and($order->created_at->toIso8601String())->toBe('2026-11-05T14:18:01+01:00')
        ->and($order->attendees)->toHaveCount(2);
});

it('applies the same data only once', function () {
    $this->travelTo(now()->startOfSecond());

    fromHievents('order.created', orderPayload(['attendees' => [attendeePayload(1)], 'question_answers' => [wheelchairAnswer()]]));
    fromHievents('order.created', orderPayload(['attendees' => [attendeePayload(1)], 'question_answers' => [wheelchairAnswer()]]));

    expect(Order::count())->toBe(1);

    Mail::assertQueuedCount(1);
});

it('ignores other events', function () {
    fromHievents('order.created', orderPayload(['event_id' => 2]));

    expect(Order::count())->toBe(0);
});

it('keeps unpaid orders out until they are paid', function () {
    $unpaid = [attendeePayload(1, ['status' => 'AWAITING_PAYMENT'])];

    fromHievents('attendee.created', $unpaid[0]);
    fromHievents('order.created', orderPayload(['status' => 'AWAITING_OFFLINE_PAYMENT', 'attendees' => $unpaid]));

    expect(Order::count())->toBe(0)
        ->and(Attendee::count())->toBe(0);

    fromHievents('order.marked_as_paid', orderPayload(['payment_status' => 'PAYMENT_RECEIVED']));

    expect(Order::sole()->attendees()->where('status', AttendeeStatus::Active)->count())->toBe(2);
});

it('gives attendees of other products no seat', function () {
    fromHievents('order.created', orderPayload(['attendees' => [attendeePayload(1), attendeePayload(2, ['product_id' => 99])]]));

    expect(Attendee::where('hievents_attendee_id', '1')->value('needs_seat'))->toBeTrue()
        ->and(Attendee::where('hievents_attendee_id', '2')->value('needs_seat'))->toBeFalse();
});

it('frees the seats of a cancelled order', function () {
    fromHievents('order.created', orderPayload());
    seatAttendee('1', 'P-1-1');
    seatAttendee('2', 'P-1-2');

    $cancelled = array_map(fn ($a) => [...$a, 'status' => 'CANCELLED'], orderPayload()['attendees']);
    fromHievents('order.cancelled', orderPayload(['status' => 'CANCELLED', 'attendees' => $cancelled]));

    expect(Order::sole()->status)->toBe(OrderStatus::Cancelled)
        ->and(Seat::whereNotNull('attendee_id')->count())->toBe(0)
        ->and(Attendee::where('status', AttendeeStatus::Active)->count())->toBe(0)
        ->and(AuditLog::where('action', 'seat_released')->count())->toBe(2);
});

it('frees one seat when one attendee is cancelled and confirms the rest', function () {
    fromHievents('order.created', orderPayload());
    seatAttendee('1', 'P-1-1');
    seatAttendee('2', 'P-1-2');

    fromHievents('attendee.cancelled', attendeePayload(1, ['status' => 'CANCELLED']));

    expect(Seat::find('P-1-1')->attendee_id)->toBeNull()
        ->and(Seat::find('P-1-2')->attendee_id)->not->toBeNull()
        ->and(Order::sole()->status)->toBe(OrderStatus::Active);

    Mail::assertQueued(SeatMail::class, fn (SeatMail $mail) => $mail->type === MailType::Confirmation && $mail->hasTo('rita@example.ch'));
});

it('sends no confirmation when no seats remain', function () {
    fromHievents('order.created', orderPayload(['attendees' => [attendeePayload(1)]]));
    seatAttendee('1', 'P-1-1');

    fromHievents('attendee.cancelled', attendeePayload(1, ['status' => 'CANCELLED']));

    Mail::assertNotQueued(SeatMail::class);
});

it('sends wheelchair orders no confirmation', function () {
    fromHievents('order.created', orderPayload(['question_answers' => [wheelchairAnswer()]]));
    seatAttendee('1', 'P-4-26');
    seatAttendee('2', 'P-4-27');

    fromHievents('attendee.cancelled', attendeePayload(1, ['status' => 'CANCELLED']));

    Mail::assertNotQueued(SeatMail::class);
});

it('tells every configured address about a wheelchair request, once', function () {
    $payload = orderPayload(['question_answers' => [wheelchairAnswer()]]);

    fromHievents('order.created', $payload);
    fromHievents('order.updated', $payload);

    Mail::assertQueuedCount(1);
    Mail::assertQueued(WheelchairNotice::class, fn (WheelchairNotice $mail) => $mail->hasTo('anna@example.ch')
        && $mail->hasTo('team@example.ch')
        && $mail->type === MailType::WheelchairRequested);

    expect(Order::sole()->needs_wheelchair)->toBeTrue()
        ->and(MailLog::sole()->type)->toBe(MailType::WheelchairRequested);
});

it('registers every ticket of a wheelchair order as needing a seat', function () {
    fromHievents('order.created', orderPayload(['question_answers' => [wheelchairAnswer()]]));

    expect(Attendee::where('needs_seat', true)->where('status', AttendeeStatus::Active)->count())->toBe(2);
});

it('never mails the buyer about a wheelchair request', function () {
    fromHievents('order.created', orderPayload(['question_answers' => [wheelchairAnswer()]]));

    Mail::assertNotQueued(WheelchairNotice::class, fn (WheelchairNotice $mail) => $mail->hasTo('rita@example.ch'));
});

it('renders the wheelchair notice', function () {
    fromHievents('order.created', orderPayload(['question_answers' => [wheelchairAnswer()]]));

    $mail = new WheelchairNotice(Order::sole(), MailType::WheelchairRequested);

    expect($mail->render())->toContain('rita@example.ch')->toContain('Rita Muster')->toContain('o_abc');
    $mail->assertHasSubject('Rollstuhlplatz gewünscht: Bestellung o_abc');
});

it('ignores other questions and unanswered boxes', function () {
    fromHievents('order.created', orderPayload(['question_answers' => [
        [...wheelchairAnswer(), 'question_id' => 8],
        [...wheelchairAnswer(), 'answer' => []],
    ]]));

    Mail::assertNothingQueued();
});

it('reports a cancelled wheelchair order and blocks its seats again', function () {
    fromHievents('order.created', orderPayload(['question_answers' => [wheelchairAnswer()]]));
    seatAttendee('1', 'P-4-26');

    fromHievents('order.cancelled', orderPayload(['status' => 'CANCELLED', 'question_answers' => [wheelchairAnswer()]]));

    Mail::assertQueued(WheelchairNotice::class, fn (WheelchairNotice $mail) => $mail->type === MailType::WheelchairCancelled);

    expect(Seat::find('P-4-26'))
        ->attendee_id->toBeNull()
        ->block_reason->toBe(BlockReason::Wheelchair);
});

it('only reports a wheelchair cancellation once no ticket is left', function () {
    fromHievents('order.created', orderPayload(['question_answers' => [wheelchairAnswer()]]));

    fromHievents('attendee.cancelled', attendeePayload(1, ['status' => 'CANCELLED']));
    Mail::assertQueuedCount(1);

    fromHievents('attendee.cancelled', attendeePayload(2, ['status' => 'CANCELLED']));
    Mail::assertQueued(WheelchairNotice::class, fn (WheelchairNotice $mail) => $mail->type === MailType::WheelchairCancelled);
});

it('warns instead of losing a request when no recipient is configured', function () {
    config(['seating.wheelchair_notify_emails' => []]);

    fromHievents('order.created', orderPayload(['question_answers' => [wheelchairAnswer()]]));

    Mail::assertNothingQueued();
    Mail::assertSent(AdminAlertMail::class);
});

it('refuses to sync without a product whitelist', function () {
    config(['seating.seated_product_ids' => []]);

    app(OrderSync::class)->syncOrder(orderPayload());
})->throws(RuntimeException::class, 'SEATED_PRODUCT_IDS');

it('imports missed orders with their wheelchair answers', function () {
    $list = orderPayload();
    unset($list['question_answers']);

    Http::fake([
        'hi.test/api/events/1/orders/100' => Http::response(['data' => orderPayload(['question_answers' => [wheelchairAnswer()]])]),
        'hi.test/api/events/1/orders*' => Http::response(['data' => [$list], 'meta' => ['last_page' => 1]]),
    ]);

    $this->artisan('seating:reconcile')->expectsOutputToContain('created 1')->assertSuccessful();

    expect(Order::sole()->needs_wheelchair)->toBeTrue();
    Mail::assertQueuedCount(1);
    Mail::assertNotSent(AdminAlertMail::class);
});

it('fixes attendees from the order list', function () {
    fromHievents('order.created', orderPayload(['attendees' => [attendeePayload(1), attendeePayload(2, ['status' => 'CANCELLED'])]]));

    $list = orderPayload();
    unset($list['question_answers']);

    Http::fake([
        'hi.test/*' => Http::response(['data' => [$list], 'meta' => ['last_page' => 1]]),
    ]);

    $this->artisan('seating:reconcile')->expectsOutputToContain('updated 1')->assertSuccessful();

    expect(Attendee::where('hievents_attendee_id', '2')->value('status'))->toBe(AttendeeStatus::Active);
});

it('keeps wheelchair flags when reconciling a list without answers', function () {
    fromHievents('order.created', orderPayload(['question_answers' => [wheelchairAnswer()]]));

    $list = orderPayload();
    unset($list['question_answers']);

    Http::fake(['hi.test/*' => Http::response(['data' => [$list], 'meta' => ['last_page' => 1]])]);

    $this->artisan('seating:reconcile')->expectsOutputToContain('unchanged 1')->assertSuccessful();

    expect(Order::sole()->needs_wheelchair)->toBeTrue();
    Mail::assertQueuedCount(1);
});

it('reads every page', function () {
    Http::fake([
        'hi.test/*page=2*' => Http::response(['data' => [orderPayload(['id' => 101, 'attendees' => [], 'status' => 'CANCELLED'])], 'meta' => ['last_page' => 2]]),
        'hi.test/*' => Http::response(['data' => [orderPayload(['status' => 'RESERVED'])], 'meta' => ['last_page' => 2]]),
    ]);

    $this->artisan('seating:reconcile')->assertSuccessful();

    Http::assertSentCount(2);
});

it('alerts once after three failed runs and again when it recovers', function () {
    $down = true;
    Http::fake(function () use (&$down) {
        return $down ? Http::response('nope', 500) : Http::response(['data' => [], 'meta' => ['last_page' => 1]]);
    });

    $this->artisan('seating:reconcile')->assertFailed();
    $this->artisan('seating:reconcile')->assertFailed();
    Mail::assertNotSent(AdminAlertMail::class);

    $this->artisan('seating:reconcile')->assertFailed();
    $this->artisan('seating:reconcile')->assertFailed();
    Mail::assertSent(AdminAlertMail::class, 1);
    Mail::assertSent(AdminAlertMail::class, fn (AdminAlertMail $mail) => $mail->title === 'Abgleich fehlgeschlagen');

    $down = false;

    $this->artisan('seating:reconcile')->assertSuccessful();
    $this->artisan('seating:reconcile')->assertSuccessful();
    Mail::assertSent(AdminAlertMail::class, fn (AdminAlertMail $mail) => $mail->title === 'Abgleich läuft wieder');
    Mail::assertSent(AdminAlertMail::class, 2);
});

it('skips a broken order, syncs the rest and reports it once', function () {
    // Missing its status, as if Hi.Events changed its format.
    $broken = ['id' => 101, 'event_id' => 1];
    $list = [orderPayload(['id' => 101]), orderPayload(['id' => 102])];

    Http::fake([
        'hi.test/api/events/1/orders/101' => Http::response(['data' => $broken]),
        'hi.test/api/events/1/orders/102' => Http::response(['data' => orderPayload(['id' => 102])]),
        'hi.test/api/events/1/orders*' => Http::response(['data' => $list, 'meta' => ['last_page' => 1]]),
    ]);

    $this->artisan('seating:reconcile')->expectsOutputToContain('created 1')->assertSuccessful();
    $this->artisan('seating:reconcile')->assertSuccessful();

    expect(Order::pluck('hievents_order_id')->all())->toBe(['102']);
    Mail::assertSent(AdminAlertMail::class, 1);
    Mail::assertSent(AdminAlertMail::class, fn (AdminAlertMail $mail) => $mail->title === 'Bestellung nicht übernommen' && str_contains($mail->alert, '101'));
});

it('fetches at most a capped number of new orders per run', function () {
    $list = collect(range(1, Reconcile::MAX_DETAILS_PER_RUN + 5))
        ->map(fn (int $i) => orderPayload(['id' => 1000 + $i, 'attendees' => [attendeePayload(5000 + $i, ['order_id' => 1000 + $i])]]))
        ->all();

    Http::fake(function ($request) use ($list) {
        if (preg_match('#/orders/(\d+)$#', $request->url(), $match)) {
            return Http::response(['data' => collect($list)->firstWhere('id', (int) $match[1])]);
        }

        return Http::response(['data' => $list, 'meta' => ['last_page' => 1]]);
    });

    $this->artisan('seating:reconcile')->expectsOutputToContain('deferred 5')->assertSuccessful();
    expect(Order::count())->toBe(Reconcile::MAX_DETAILS_PER_RUN);

    $this->artisan('seating:reconcile')->expectsOutputToContain('created 5')->assertSuccessful();
    expect(Order::count())->toBe(Reconcile::MAX_DETAILS_PER_RUN + 5);
});

it('fails the run instead of every order without a product whitelist', function () {
    config(['seating.seated_product_ids' => []]);
    Http::fake(['hi.test/*' => Http::response(['data' => [orderPayload()], 'meta' => ['last_page' => 1]])]);

    $this->artisan('seating:reconcile')->assertFailed();
});

it('sends no alert when nothing changed', function () {
    fromHievents('order.created', orderPayload());

    Http::fake(['hi.test/*' => Http::response(['data' => [orderPayload()], 'meta' => ['last_page' => 1]])]);

    $this->artisan('seating:reconcile')->assertSuccessful();

    Mail::assertNotSent(AdminAlertMail::class);
});

it('pulls every two minutes', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'seating:reconcile'));

    expect($event->expression)->toBe('*/2 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
