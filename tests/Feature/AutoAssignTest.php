<?php

use App\Enums\AssignedBy;
use App\Enums\AttendeeStatus;
use App\Enums\BlockReason;
use App\Enums\MailType;
use App\Enums\OrderStatus;
use App\Mail\AdminAlertMail;
use App\Mail\SeatMail;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\MailLog;
use App\Models\Order;
use App\Models\Seat;
use App\Services\AutoAssign;
use App\Services\BestSeats;
use App\Services\BuyerMailer;
use App\Services\Hievents\OrderSync;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    Cache::flush();
    seatedEvent();

    $this->travelTo('2027-01-26 12:00:00');

    setEvent([
        'hievents_event_id' => '1',
        'seated_product_ids' => ['10'],
        'selection_opens_at' => '2026-12-20 10:00',
        'selection_deadline' => '2027-01-25 23:59',
    ]);
    config([
        'seating.admin_emails' => ['admin@example.ch'],
        'seating.mail_per_second' => 2,
    ]);
});

function waitingOrder(int $tickets, array $attributes = []): Order
{
    $order = Order::factory()->create($attributes);
    Attendee::factory()->count($tickets)->for($order)->create();
    MailLog::create(['order_id' => $order->id, 'type' => MailType::Link, 'sent_at' => '2027-01-01 12:00:00']);

    return $order;
}

function seatIdsOf(Order $order): array
{
    return $order->seats()->pluck('seats.code')->sort()->values()->all();
}

function leaveFree(array $ids): void
{
    Seat::whereNotIn('code', $ids)->whereNull('block_reason')->update(['block_reason' => BlockReason::Other]);
}

function isContiguous(Order $order): bool
{
    $seats = $order->seats()->get();

    return $seats->pluck('section')->unique()->count() === 1
        && $seats->pluck('row')->unique()->count() === 1
        && $seats->pluck('segment')->unique()->count() === 1
        && $seats->max('number') - $seats->min('number') === $seats->count() - 1;
}

function lateWebhookOrder(array $overrides = []): array
{
    $attendee = fn (int $id): array => [
        'id' => $id, 'order_id' => 900, 'event_id' => 1, 'product_id' => 10, 'status' => 'ACTIVE',
        'first_name' => 'Late', 'last_name' => 'Buyer', 'email' => 'late@example.ch', 'question_answers' => [],
    ];

    return [
        'id' => 900, 'event_id' => 1, 'short_id' => 'o_late', 'public_id' => 'O-LATE', 'status' => 'COMPLETED',
        'first_name' => 'Late', 'last_name' => 'Buyer', 'email' => 'late@example.ch',
        'created_at' => '2027-01-26T10:00:00.000000Z', 'attendees' => [$attendee(901), $attendee(902)],
        'question_answers' => [], ...$overrides,
    ];
}

it('seats every waiting order, marked as automatic', function () {
    $orders = [waitingOrder(4), waitingOrder(2), waitingOrder(1)];

    app(AutoAssign::class)->run(seatedEvent());

    foreach ($orders as $order) {
        expect($order->seats()->count())->toBe($order->seatsNeeded())
            ->and(isContiguous($order))->toBeTrue();
    }

    expect(Seat::whereNotNull('attendee_id')->pluck('assigned_by')->unique()->all())->toBe([AssignedBy::Auto])
        ->and(AuditLog::where('action', 'seat_auto_assigned')->count())->toBe(7);
});

it('seats the biggest groups first, then the oldest orders', function () {
    $newerPair = waitingOrder(2, ['created_at' => '2026-12-22 10:00:00']);
    $olderPair = waitingOrder(2, ['created_at' => '2026-12-21 10:00:00']);
    $group = waitingOrder(5, ['created_at' => '2026-12-23 10:00:00']);

    $results = app(AutoAssign::class)->run(seatedEvent());

    expect($results->pluck('order.id')->all())->toBe([$group->id, $olderPair->id, $newerPair->id]);
});

it('never treats seats on both sides of a pillar as together', function () {
    $ids = collect(range(11, 16))->map(fn (int $number): string => "GL-1-{$number}")->all();
    expect(Seat::whereIn('code', $ids)->get()->pluck('section')->unique()->all())->toBe(['galerie-links']);
    leaveFree($ids);

    $whole = waitingOrder(6);
    $result = app(AutoAssign::class)->run(seatedEvent())->first();

    expect($result->split)->toBeTrue()
        ->and($whole->seats()->pluck('segment')->sort()->values()->all())->toBe([1, 1, 1, 2, 2, 2]);
});

it('fills seats with a restricted view last', function () {
    $clear = Seat::whereJsonDoesntContain('flags', 'obstructed')->where(['section' => 'parterre', 'row' => 10])->orderBy('number')->limit(4)->pluck('code')->all();
    $obstructed = Seat::whereJsonContains('flags', 'obstructed')->orderBy('code')->limit(10)->pluck('code')->all();
    leaveFree([...$clear, ...$obstructed]);

    $order = waitingOrder(2);
    app(AutoAssign::class)->run(seatedEvent());

    expect(array_intersect(seatIdsOf($order), $obstructed))->toBe([]);
});

it('splits 2 and 2 rather than leaving someone alone', function () {
    leaveFree(['P-5-1', 'P-5-2', 'P-5-3', 'P-5-5', 'P-5-20', 'P-6-1', 'P-6-2']);
    Seat::where('code', 'P-5-4')->update(['block_reason' => BlockReason::Other]);

    $order = waitingOrder(4);
    $result = app(AutoAssign::class)->run(seatedEvent())->first();

    $perRun = $order->seats()->get()->groupBy('row')->map->count();

    expect($result->split)->toBeTrue()
        ->and($perRun->min())->toBeGreaterThanOrEqual(2);
});

it('gives the same result every time', function () {
    $orders = collect([5, 3, 2, 2, 1])->map(fn (int $tickets): Order => waitingOrder($tickets));

    $first = app(AutoAssign::class)->preview(seatedEvent())->map->seatIds->all();
    $second = app(AutoAssign::class)->preview(seatedEvent())->map->seatIds->all();
    $real = app(AutoAssign::class)->run(seatedEvent())->map->seatIds->all();

    expect($second)->toBe($first)
        ->and($real)->toBe($first);
});

it('leaves out wheelchair, cancelled and fully seated orders', function () {
    $wheelchair = waitingOrder(2, ['needs_wheelchair' => true]);
    $cancelled = waitingOrder(2, ['status' => OrderStatus::Cancelled]);
    $seated = waitingOrder(2);
    $seated->attendees->each(fn (Attendee $attendee, int $index) => seat('P-9-'.($index + 1))->update(['attendee_id' => $attendee->id]));
    $free = waitingOrder(1);

    $results = app(AutoAssign::class)->run(seatedEvent());

    expect($results->pluck('order.id')->all())->toBe([$free->id])
        ->and($wheelchair->seats()->count())->toBe(0)
        ->and($cancelled->seats()->count())->toBe(0);
});

it('ignores cancelled tickets and tickets without a seat', function () {
    $order = waitingOrder(3);
    $order->attendees()->first()->update(['status' => AttendeeStatus::Cancelled]);
    Attendee::factory()->for($order)->create(['needs_seat' => false]);

    app(AutoAssign::class)->run(seatedEvent());

    expect($order->seats()->count())->toBe(2);
});

it('keeps existing seats and places a new ticket beside the group', function () {
    $order = waitingOrder(3);
    $order->attendees->take(2)->values()->each(fn (Attendee $attendee, int $index) => seat('P-12-'.($index + 7))->update(['attendee_id' => $attendee->id, 'assigned_by' => AssignedBy::Buyer]));

    app(AutoAssign::class)->run(seatedEvent());

    expect(Seat::whereIn('code', ['P-12-7', 'P-12-8'])->pluck('assigned_by')->unique()->all())->toBe([AssignedBy::Buyer])
        ->and(seatIdsOf($order))->toContain('P-12-7', 'P-12-8')
        ->and(isContiguous($order))->toBeTrue();
});

it('sends one mail per order, spread out', function () {
    $orders = collect([2, 2, 2])->map(fn (int $tickets): Order => waitingOrder($tickets));

    $mailer = new class extends BuyerMailer
    {
        public array $delays = [];

        public function send(Order $order, MailType $type, int $delaySeconds = 0): void
        {
            $this->delays[] = [$type, $delaySeconds];
        }
    };
    $this->app->instance(BuyerMailer::class, $mailer);

    app(AutoAssign::class)->run(seatedEvent());

    expect($mailer->delays)->toBe([[MailType::AutoAssigned, 0], [MailType::AutoAssigned, 0], [MailType::AutoAssigned, 1]]);
});

it('logs and queues the auto-assigned mail', function () {
    $order = waitingOrder(2);

    app(AutoAssign::class)->run(seatedEvent());

    Mail::assertQueued(SeatMail::class, fn (SeatMail $mail) => $mail->type === MailType::AutoAssigned && $mail->hasTo($order->email));
    expect(MailLog::where(['order_id' => $order->id, 'type' => MailType::AutoAssigned])->count())->toBe(1);
});

it('does nothing on a second run', function () {
    waitingOrder(2);

    app(AutoAssign::class)->run(seatedEvent());
    $second = app(AutoAssign::class)->run(seatedEvent());

    expect($second)->toBeEmpty();
    Mail::assertQueuedCount(1);
});

it('reports orders that do not fit and seats none of their tickets', function () {
    leaveFree(['P-5-1', 'P-5-2', 'P-5-3']);
    $tooBig = waitingOrder(4, ['short_id' => 'o_big', 'email' => 'secret@example.ch', 'last_name' => 'Geheim']);
    $fits = waitingOrder(3);

    $results = app(AutoAssign::class)->run(seatedEvent());

    expect($tooBig->seats()->count())->toBe(0)
        ->and($fits->seats()->count())->toBe(3)
        ->and($results->first()->isSeated())->toBeFalse();

    Mail::assertSent(AdminAlertMail::class, function (AdminAlertMail $mail) {
        $body = $mail->render();

        return str_contains($body, 'o_big') && ! str_contains($body, 'secret@example.ch') && ! str_contains($body, 'Geheim');
    });
    Mail::assertNotQueued(SeatMail::class, fn (SeatMail $mail) => $mail->hasTo('secret@example.ch'));
});

it('tries again when the suggested seats are gone', function () {
    $order = waitingOrder(1);
    Seat::where('code', 'P-1-1')->update(['block_reason' => BlockReason::Other]);

    $bestSeats = Mockery::mock(BestSeats::class);
    $bestSeats->shouldReceive('find')->twice()->andReturn(['P-1-1'], ['P-1-2']);
    $this->app->instance(BestSeats::class, $bestSeats);

    app(AutoAssign::class)->run(seatedEvent());

    expect(seatIdsOf($order))->toBe(['P-1-2']);
});

it('previews without changing anything', function () {
    leaveFree(['P-5-1', 'P-5-2']);
    $order = waitingOrder(2);
    waitingOrder(2, ['short_id' => 'o_none']);

    $this->artisan('seating:auto-assign', ['event' => 'test', '--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->expectsOutputToContain('Assigned: 1 orders, 2 tickets.')
        ->expectsOutputToContain('Unassignable: 1 orders, 2 tickets: o_none.')
        ->assertSuccessful();

    expect($order->seats()->count())->toBe(0)
        ->and(AuditLog::count())->toBe(0)
        ->and(MailLog::where('type', MailType::AutoAssigned)->count())->toBe(0);

    Mail::assertNothingQueued();
    Mail::assertNotSent(AdminAlertMail::class);
});

it('allows a preview before the deadline but no real run', function () {
    $this->travelTo('2027-01-10 12:00:00');
    $order = waitingOrder(2);

    $this->artisan('seating:auto-assign', ['event' => 'test', '--dry-run' => true])->assertSuccessful();
    $this->artisan('seating:auto-assign', ['event' => 'test'])->expectsOutputToContain('deadline has not passed')->assertFailed();

    expect($order->seats()->count())->toBe(0);
});

it('prints a summary after the real run', function () {
    waitingOrder(3);

    $this->artisan('seating:auto-assign', ['event' => 'test'])
        ->expectsOutputToContain('Assigned: 1 orders, 3 tickets.')
        ->expectsOutputToContain('Split: 0 orders.')
        ->assertSuccessful();
});

describe('overdue alert', function () {
    beforeEach(function () {
        setEvent(['starts_at' => '2027-02-01 20:00']);
    });

    it('alerts three hours after the deadline if nobody ran it', function () {
        waitingOrder(2, ['last_name' => 'Geheim']);

        $this->travelTo('2027-01-26 02:58:00');
        $this->artisan('seating:check-auto-assign')->assertSuccessful();
        Mail::assertNotSent(AdminAlertMail::class);

        $this->travelTo('2027-01-26 03:00:00');
        $this->artisan('seating:check-auto-assign')->assertSuccessful();
        Mail::assertSent(AdminAlertMail::class, fn (AdminAlertMail $mail) => $mail->hasTo('admin@example.ch')
            && str_contains($mail->render(), '1 Bestellungen warten')
            && ! str_contains($mail->render(), 'Geheim'));
    });

    it('repeats once a day until it has been run', function () {
        waitingOrder(2);
        $this->travelTo('2027-01-26 12:00:00');

        $this->artisan('seating:check-auto-assign');
        $this->artisan('seating:check-auto-assign');
        Mail::assertSent(AdminAlertMail::class, 1);

        $this->travelTo('2027-01-27 12:01:00');
        $this->artisan('seating:check-auto-assign');
        Mail::assertSent(AdminAlertMail::class, 2);
    });

    it('stays quiet once it has been run, even for orders that did not fit', function () {
        leaveFree([]);
        waitingOrder(2);

        app(AutoAssign::class)->run(seatedEvent());
        Mail::assertSent(AdminAlertMail::class, 1);

        $this->artisan('seating:check-auto-assign');
        Mail::assertSent(AdminAlertMail::class, 1);
    });

    it('does not count a preview as a run', function () {
        waitingOrder(2);

        app(AutoAssign::class)->preview(seatedEvent());
        $this->artisan('seating:check-auto-assign');

        Mail::assertSent(AdminAlertMail::class, 1);
    });

    it('stays quiet when nobody is waiting, without dates, and after the event', function () {
        $this->artisan('seating:check-auto-assign');

        waitingOrder(2);
        setEvent(['selection_deadline' => null]);
        $this->artisan('seating:check-auto-assign');

        setEvent(['selection_deadline' => '2027-01-25 23:59']);
        $this->travelTo('2027-02-02 12:00:00');
        $this->artisan('seating:check-auto-assign');

        Mail::assertNotSent(AdminAlertMail::class);
    });

    it('leaves out wheelchair orders', function () {
        waitingOrder(2, ['needs_wheelchair' => true]);

        $this->artisan('seating:check-auto-assign');

        Mail::assertNotSent(AdminAlertMail::class);
    });
});

describe('late orders', function () {
    it('seats an order arriving after the deadline right away', function () {
        app(OrderSync::class)->syncOrder(seatedEvent(), lateWebhookOrder());

        $order = Order::where('short_id', 'o_late')->first();

        expect($order->seats()->count())->toBe(2)
            ->and(Seat::whereNotNull('attendee_id')->pluck('assigned_by')->unique()->all())->toBe([AssignedBy::Auto]);

        Mail::assertQueued(SeatMail::class, fn (SeatMail $mail) => $mail->type === MailType::AutoAssigned && $mail->hasTo('late@example.ch'));
    });

    it('does not seat anyone before the deadline', function () {
        $this->travelTo('2027-01-10 12:00:00');

        app(OrderSync::class)->syncOrder(seatedEvent(), lateWebhookOrder());

        expect(Order::where('short_id', 'o_late')->first()->seats()->count())->toBe(0);
    });

    it('leaves orders that could choose to the batch, so big groups still go first', function () {
        $order = waitingOrder(2, ['hievents_order_id' => '900']);

        app(OrderSync::class)->syncOrder(seatedEvent(), lateWebhookOrder(['attendees' => []]));

        expect($order->seats()->count())->toBe(0);
    });

    it('seats a ticket added to a group that already has seats', function () {
        $order = waitingOrder(1, ['hievents_order_id' => '900']);
        seat('P-12-7')->update(['attendee_id' => $order->attendees->first()->id, 'assigned_by' => AssignedBy::Buyer]);

        app(OrderSync::class)->syncAttendee(seatedEvent(), [
            'id' => 955, 'order_id' => 900, 'event_id' => 1, 'product_id' => 10, 'status' => 'ACTIVE',
            'first_name' => 'Neu', 'last_name' => 'Dazu', 'email' => 'neu@example.ch', 'question_answers' => [],
        ]);

        expect($order->seats()->count())->toBe(2)
            ->and(seatIdsOf($order))->toContain('P-12-7')
            ->and(isContiguous($order))->toBeTrue();
    });

    it('never seats a late wheelchair order', function () {
        setEvent([
            'wheelchair_question_ids' => ['7'],
            'wheelchair_notify_emails' => ['anna@example.ch'],
        ]);

        app(OrderSync::class)->syncOrder(seatedEvent(), lateWebhookOrder([
            'question_answers' => [['question_id' => 7, 'belongs_to' => 'ORDER', 'answer' => ['Ja'], 'text_answer' => 'Ja', 'order_id' => 900]],
        ]));

        expect(Order::where('short_id', 'o_late')->first()->seats()->count())->toBe(0);
    });

    it('applies the same data twice without a second mail', function () {
        app(OrderSync::class)->syncOrder(seatedEvent(), lateWebhookOrder());
        app(OrderSync::class)->syncOrder(seatedEvent(), lateWebhookOrder());

        expect(MailLog::where('type', MailType::AutoAssigned)->count())->toBe(1);
    });

    it('alerts once a day when a late order does not fit, and seats it once there is room', function () {
        Seat::whereNull('block_reason')->update(['block_reason' => BlockReason::Other]);

        app(OrderSync::class)->syncOrder(seatedEvent(), lateWebhookOrder());
        app(OrderSync::class)->syncOrder(seatedEvent(), lateWebhookOrder());

        Mail::assertSent(AdminAlertMail::class, 1);

        Seat::whereIn('code', ['P-5-1', 'P-5-2'])->update(['block_reason' => null]);
        app(OrderSync::class)->syncOrder(seatedEvent(), lateWebhookOrder());

        expect(Order::where('short_id', 'o_late')->first()->seats()->count())->toBe(2);
    });
});
