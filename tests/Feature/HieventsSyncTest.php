<?php

use App\Enums\AssignedBy;
use App\Enums\AttendeeStatus;
use App\Enums\BlockReason;
use App\Enums\MailType;
use App\Enums\OrderStatus;
use App\Jobs\ProcessWebhookEvent;
use App\Mail\WheelchairNotice;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\MailLog;
use App\Models\Order;
use App\Models\Seat;
use App\Models\WebhookEvent;
use App\Services\Hievents\OrderSync;
use Database\Seeders\SeatSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

const WEBHOOK_SECRET = 'test-secret';

beforeEach(function () {
    Mail::fake();
    Http::fake(['ntfy.test/*' => Http::response()]);

    config([
        'seating.event_id' => '1',
        'seating.seated_product_ids' => ['10'],
        'seating.wheelchair_question_ids' => ['7'],
        'seating.wheelchair_notify_emails' => ['anna@example.ch', 'team@example.ch'],
        'seating.hievents.webhook_secret' => WEBHOOK_SECRET,
        'seating.hievents.base_url' => 'https://hi.test',
        'seating.hievents.api_key' => 'key',
        'seating.ntfy' => ['url' => 'https://ntfy.test', 'topic' => 'seats'],
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

function wheelchairAnswer(int $attendeeId): array
{
    return ['question_id' => 7, 'answer' => ['Ja'], 'text_answer' => 'Ja', 'attendee_id' => $attendeeId, 'order_id' => 100];
}

function sendWebhook(string $type, array $payload, ?string $secret = WEBHOOK_SECRET)
{
    $body = json_encode(['event_type' => $type, 'event_sent_at' => now()->toIso8601String(), 'payload' => $payload]);
    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

    if ($secret !== null) {
        $server['HTTP_SIGNATURE'] = hash_hmac('sha256', $body, $secret);
    }

    return test()->call('POST', '/webhooks/hievents', [], [], [], $server, $body);
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

it('accepts a signed webhook and creates the order', function () {
    sendWebhook('order.created', orderPayload())->assertOk();

    $order = Order::where('hievents_order_id', '100')->sole();

    expect($order)
        ->status->toBe(OrderStatus::Active)
        ->short_id->toBe('o_abc')
        ->email->toBe('rita@example.ch')
        ->token_hash->toHaveLength(64)
        ->and($order->created_at->toIso8601String())->toBe('2026-11-05T14:18:01+01:00')
        ->and($order->attendees)->toHaveCount(2);

    expect(WebhookEvent::sole())
        ->event_type->toBe('order.created')
        ->processed_at->not->toBeNull();
});

it('rejects a wrong or missing signature', function (?string $secret) {
    sendWebhook('order.created', orderPayload(), $secret)->assertUnauthorized();

    expect(WebhookEvent::count())->toBe(0)
        ->and(Order::count())->toBe(0);
})->with(['wrong secret' => 'nope', 'no signature' => null]);

it('rejects everything when no secret is configured', function () {
    config(['seating.hievents.webhook_secret' => '']);

    sendWebhook('order.created', orderPayload(), '')->assertUnauthorized();
});

it('handles a duplicate delivery only once', function () {
    $this->travelTo(now()->startOfSecond());

    sendWebhook('order.created', orderPayload(['attendees' => [attendeePayload(1)], 'question_answers' => [wheelchairAnswer(1)]]))->assertOk();
    sendWebhook('order.created', orderPayload(['attendees' => [attendeePayload(1)], 'question_answers' => [wheelchairAnswer(1)]]))->assertOk();

    expect(WebhookEvent::count())->toBe(1)
        ->and(Order::count())->toBe(1);

    Mail::assertQueuedCount(1);
});

it('ignores other events', function () {
    sendWebhook('order.created', orderPayload(['event_id' => 2]))->assertOk();

    expect(Order::count())->toBe(0);
});

it('ignores event types it does not handle', function () {
    sendWebhook('product.updated', ['id' => 1])->assertOk();

    expect(WebhookEvent::sole()->processed_at)->not->toBeNull();
});

it('keeps unpaid orders out until they are paid', function () {
    $unpaid = [attendeePayload(1, ['status' => 'AWAITING_PAYMENT'])];

    sendWebhook('attendee.created', $unpaid[0])->assertOk();
    sendWebhook('order.created', orderPayload(['status' => 'AWAITING_OFFLINE_PAYMENT', 'attendees' => $unpaid]))->assertOk();

    expect(Order::count())->toBe(0)
        ->and(Attendee::count())->toBe(0);

    sendWebhook('order.marked_as_paid', orderPayload(['payment_status' => 'PAYMENT_RECEIVED']))->assertOk();

    expect(Order::sole()->attendees()->where('status', AttendeeStatus::Active)->count())->toBe(2);
});

it('gives attendees of other products no seat', function () {
    sendWebhook('order.created', orderPayload(['attendees' => [attendeePayload(1), attendeePayload(2, ['product_id' => 99])]]));

    expect(Attendee::where('hievents_attendee_id', '1')->value('needs_seat'))->toBeTrue()
        ->and(Attendee::where('hievents_attendee_id', '2')->value('needs_seat'))->toBeFalse();
});

it('frees the seats of a cancelled order', function () {
    sendWebhook('order.created', orderPayload());
    seatAttendee('1', 'P-1-1');
    seatAttendee('2', 'P-1-2');

    $cancelled = array_map(fn ($a) => [...$a, 'status' => 'CANCELLED'], orderPayload()['attendees']);
    sendWebhook('order.cancelled', orderPayload(['status' => 'CANCELLED', 'attendees' => $cancelled]));

    expect(Order::sole()->status)->toBe(OrderStatus::Cancelled)
        ->and(Seat::whereNotNull('attendee_id')->count())->toBe(0)
        ->and(Attendee::where('status', AttendeeStatus::Active)->count())->toBe(0)
        ->and(AuditLog::where('action', 'seat_released')->count())->toBe(2);
});

it('frees one seat when one attendee is cancelled', function () {
    sendWebhook('order.created', orderPayload());
    seatAttendee('1', 'P-1-1');
    seatAttendee('2', 'P-1-2');

    sendWebhook('attendee.cancelled', attendeePayload(1, ['status' => 'CANCELLED']));

    expect(Seat::find('P-1-1')->attendee_id)->toBeNull()
        ->and(Seat::find('P-1-2')->attendee_id)->not->toBeNull()
        ->and(Order::sole()->status)->toBe(OrderStatus::Active);
});

it('tells every configured address about a wheelchair request, once', function () {
    $payload = orderPayload(['question_answers' => [wheelchairAnswer(1)]]);

    sendWebhook('order.created', $payload);
    sendWebhook('order.updated', $payload);

    Mail::assertQueuedCount(1);
    Mail::assertQueued(WheelchairNotice::class, fn (WheelchairNotice $mail) => $mail->hasTo('anna@example.ch')
        && $mail->hasTo('team@example.ch')
        && $mail->type === MailType::WheelchairRequested);

    expect(Attendee::where('hievents_attendee_id', '1')->value('needs_wheelchair'))->toBeTrue()
        ->and(Attendee::where('hievents_attendee_id', '2')->value('needs_wheelchair'))->toBeFalse()
        ->and(MailLog::sole()->type)->toBe(MailType::WheelchairRequested);
});

it('never mails the buyer about a wheelchair request', function () {
    sendWebhook('order.created', orderPayload(['question_answers' => [wheelchairAnswer(1)]]));

    Mail::assertNotQueued(WheelchairNotice::class, fn (WheelchairNotice $mail) => $mail->hasTo('rita@example.ch'));
});

it('renders the wheelchair notice', function () {
    sendWebhook('order.created', orderPayload(['question_answers' => [wheelchairAnswer(1)]]));

    $mail = new WheelchairNotice(Order::sole(), MailType::WheelchairRequested);

    expect($mail->render())->toContain('rita@example.ch')->toContain('Rita Muster')->toContain('o_abc');
    $mail->assertHasSubject('Rollstuhlplatz gewünscht: Bestellung o_abc');
});

it('ignores other questions and unanswered boxes', function () {
    sendWebhook('order.created', orderPayload(['question_answers' => [
        [...wheelchairAnswer(1), 'question_id' => 8],
        [...wheelchairAnswer(2), 'answer' => []],
    ]]));

    Mail::assertNothingQueued();
});

it('reports a cancelled wheelchair ticket and blocks its seat again', function () {
    sendWebhook('order.created', orderPayload(['question_answers' => [wheelchairAnswer(1)]]));
    seatAttendee('1', 'P-4-26');

    sendWebhook('attendee.cancelled', attendeePayload(1, ['status' => 'CANCELLED']));

    Mail::assertQueued(WheelchairNotice::class, fn (WheelchairNotice $mail) => $mail->type === MailType::WheelchairCancelled);

    expect(Seat::find('P-4-26'))
        ->attendee_id->toBeNull()
        ->block_reason->toBe(BlockReason::Wheelchair);
});

it('warns instead of losing a request when no recipient is configured', function () {
    config(['seating.wheelchair_notify_emails' => []]);

    sendWebhook('order.created', orderPayload(['question_answers' => [wheelchairAnswer(1)]]));

    Mail::assertNothingQueued();
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'ntfy.test/seats'));
});

it('refuses to sync without a product whitelist', function () {
    config(['seating.seated_product_ids' => []]);

    app(OrderSync::class)->syncOrder(orderPayload());
})->throws(RuntimeException::class, 'SEATED_PRODUCT_IDS');

it('records a failed delivery and alerts', function () {
    $event = WebhookEvent::create([
        'event_type' => 'order.created',
        'payload_hash' => str_repeat('a', 64),
        'payload' => [],
        'received_at' => now(),
    ]);

    (new ProcessWebhookEvent($event->id))->failed(new RuntimeException('Kaputt'));

    expect($event->refresh()->error)->toBe('Kaputt');
    Http::assertSent(fn (Request $request) => $request->header('Title') === ['Webhook fehlgeschlagen']);
});

it('imports missed orders with their wheelchair answers', function () {
    $list = orderPayload();
    unset($list['question_answers']);

    Http::fake([
        'hi.test/api/events/1/orders/100' => Http::response(['data' => orderPayload(['question_answers' => [wheelchairAnswer(2)]])]),
        'hi.test/api/events/1/orders*' => Http::response(['data' => [$list], 'meta' => ['last_page' => 1]]),
        'ntfy.test/*' => Http::response(),
    ]);

    $this->artisan('seating:reconcile')->expectsOutputToContain('created 1')->assertSuccessful();

    expect(Attendee::where('hievents_attendee_id', '2')->value('needs_wheelchair'))->toBeTrue();
    Mail::assertQueuedCount(1);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'ntfy.test/seats'));
});

it('fixes attendees whose changes sent no webhook', function () {
    sendWebhook('order.created', orderPayload(['attendees' => [attendeePayload(1), attendeePayload(2, ['status' => 'CANCELLED'])]]));

    $list = orderPayload();
    unset($list['question_answers']);

    Http::fake([
        'hi.test/*' => Http::response(['data' => [$list], 'meta' => ['last_page' => 1]]),
        'ntfy.test/*' => Http::response(),
    ]);

    $this->artisan('seating:reconcile')->expectsOutputToContain('updated 1')->assertSuccessful();

    expect(Attendee::where('hievents_attendee_id', '2')->value('status'))->toBe(AttendeeStatus::Active);
});

it('keeps wheelchair flags when reconciling a list without answers', function () {
    sendWebhook('order.created', orderPayload(['question_answers' => [wheelchairAnswer(1)]]));

    $list = orderPayload();
    unset($list['question_answers']);

    Http::fake(['hi.test/*' => Http::response(['data' => [$list], 'meta' => ['last_page' => 1]])]);

    $this->artisan('seating:reconcile')->expectsOutputToContain('unchanged 1')->assertSuccessful();

    expect(Attendee::where('hievents_attendee_id', '1')->value('needs_wheelchair'))->toBeTrue();
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

it('alerts and fails when the API is down', function () {
    Http::fake([
        'hi.test/*' => Http::response('nope', 500),
        'ntfy.test/*' => Http::response(),
    ]);

    $this->artisan('seating:reconcile')->assertFailed();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'ntfy.test/seats')
        && $request->header('Title') === ['Abgleich fehlgeschlagen']);
});

it('sends no alert when nothing changed', function () {
    sendWebhook('order.created', orderPayload());

    Http::fake(['hi.test/*' => Http::response(['data' => [orderPayload()], 'meta' => ['last_page' => 1]])]);

    $this->artisan('seating:reconcile')->assertSuccessful();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'ntfy.test'));
});

it('runs reconciliation every 30 minutes', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('seating:reconcile')->assertSuccessful();
});
