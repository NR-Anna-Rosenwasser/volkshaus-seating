<?php

use App\Enums\AssignedBy;
use App\Enums\AttendeeStatus;
use App\Enums\AuditActor;
use App\Enums\BlockReason;
use App\Enums\MailType;
use App\Exceptions\SeatConflict;
use App\Mail\SeatMail;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\MailLog;
use App\Models\Order;
use App\Models\Seat;
use App\Services\AdminSeats;
use App\Services\SeatStats;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Mail::fake();
    seatedEvent();
    $this->seats = app(AdminSeats::class);
});

function groupOf(int $tickets, array $attributes = []): Order
{
    $order = Order::factory()->create($attributes);
    Attendee::factory()->count($tickets)->for($order)->create();

    return $order;
}

function adminSeatIds(Order $order): array
{
    return $order->seats()->pluck('seats.code')->sort()->values()->all();
}

function wheelchairSeat(): Seat
{
    return Seat::whereJsonContains('flags', 'wheelchair')->orderBy('id')->first();
}

it('blocks free seats with a reason and note', function () {
    $result = $this->seats->block(seatedEvent(), ['P-1-1', 'P-1-2'], BlockReason::Press, 'Radio');

    expect($result)->toBe(['changed' => 2, 'skipped' => []])
        ->and(seat('P-1-1')->block_reason)->toBe(BlockReason::Press)
        ->and(seat('P-1-2')->block_note)->toBe('Radio')
        ->and(AuditLog::where(['action' => 'seat_blocked', 'actor' => AuditActor::Admin])->count())->toBe(2);
});

it('leaves assigned seats out of a block', function () {
    $order = groupOf(1);
    $this->seats->assign($order, ['P-1-1']);

    $result = $this->seats->block(seatedEvent(), ['P-1-1', 'P-1-2'], BlockReason::Vip, null);

    expect($result)->toBe(['changed' => 1, 'skipped' => ['P-1-1']])
        ->and(seat('P-1-1')->attendee_id)->not->toBeNull()
        ->and(seat('P-1-1')->block_reason)->toBeNull();
});

it('releases blocked seats and clears the note', function () {
    $this->seats->block(seatedEvent(), ['P-1-1'], BlockReason::Vip, 'Gast');

    expect($this->seats->unblock(seatedEvent(), ['P-1-1', 'P-1-2']))->toBe(1)
        ->and(seat('P-1-1')->block_reason)->toBeNull()
        ->and(seat('P-1-1')->block_note)->toBeNull();
});

it('moves one person and leaves the group alone', function () {
    $order = groupOf(2);
    $this->seats->assign($order, ['P-1-1', 'P-1-2']);
    $person = seat('P-1-1')->attendee_id;

    $this->seats->move(seatedEvent(), 'P-1-1', 'P-9-9');

    expect(seat('P-9-9')->attendee_id)->toBe($person)
        ->and(seat('P-9-9')->assigned_by)->toBe(AssignedBy::Admin)
        ->and(seat('P-1-1')->attendee_id)->toBeNull()
        ->and(seat('P-1-2')->attendee_id)->not->toBeNull()
        ->and(AuditLog::where('action', 'seat_moved')->first()->before)->toBe(['seat_id' => 'P-1-1']);
});

it('refuses to move someone onto a taken or blocked seat', function (string $target) {
    $this->seats->assign(groupOf(1), ['P-1-1']);
    $this->seats->assign(groupOf(1), ['P-1-2']);
    $this->seats->block(seatedEvent(), ['P-1-3'], BlockReason::Vip, null);

    expect(fn () => $this->seats->move(seatedEvent(), 'P-1-1', $target))->toThrow(SeatConflict::class)
        ->and(seat('P-1-1')->attendee_id)->not->toBeNull();
})->with(['P-1-2', 'P-1-3']);

it('assigns an order partly, then completely, and can take seats away', function () {
    $order = groupOf(3);

    $this->seats->assign($order, ['P-1-1']);
    expect(adminSeatIds($order))->toBe(['P-1-1']);

    $this->seats->assign($order, ['P-1-1', 'P-1-2', 'P-1-3']);
    expect(adminSeatIds($order))->toBe(['P-1-1', 'P-1-2', 'P-1-3']);

    $this->seats->assign($order, ['P-1-2']);
    expect(adminSeatIds($order))->toBe(['P-1-2'])
        ->and(seat('P-1-1')->assigned_by)->toBeNull();
});

it('keeps people on seats that stay in the selection', function () {
    $order = groupOf(2);
    $this->seats->assign($order, ['P-1-1', 'P-1-2']);
    $person = seat('P-1-2')->attendee_id;

    $this->seats->assign($order, ['P-1-2', 'P-1-3']);

    expect(seat('P-1-2')->attendee_id)->toBe($person);
});

it('refuses more seats than tickets, unknown seats, and other people\'s seats', function () {
    $other = groupOf(1);
    $this->seats->assign($other, ['P-1-9']);
    $order = groupOf(1);

    expect(fn () => $this->seats->assign($order, ['P-1-1', 'P-1-2']))->toThrow(ValidationException::class)
        ->and(fn () => $this->seats->assign($order, ['X-1-1']))->toThrow(ValidationException::class)
        ->and(fn () => $this->seats->assign($order, ['P-1-9']))->toThrow(SeatConflict::class);
});

it('refuses reserved seats until the block is lifted', function () {
    $this->seats->block(seatedEvent(), ['P-1-1'], BlockReason::Vip, null);
    $order = groupOf(1);

    expect(fn () => $this->seats->assign($order, ['P-1-1']))->toThrow(SeatConflict::class);

    $this->seats->unblock(seatedEvent(), ['P-1-1']);
    $this->seats->assign($order, ['P-1-1']);

    expect(adminSeatIds($order))->toBe(['P-1-1']);
});

it('seats a wheelchair order on a wheelchair seat, and restores the block when moved away', function () {
    $seat = wheelchairSeat();
    expect($seat->block_reason)->toBe(BlockReason::Wheelchair);

    $order = groupOf(1, ['needs_wheelchair' => true]);
    $this->seats->assign($order, [$seat->code]);

    expect($seat->refresh()->block_reason)->toBeNull()
        ->and($seat->attendee_id)->not->toBeNull();

    $this->seats->move(seatedEvent(), $seat->code, 'P-9-9');

    expect($seat->refresh()->block_reason)->toBe(BlockReason::Wheelchair)
        ->and($seat->attendee_id)->toBeNull();
});

it('works after the deadline', function () {
    setEvent(['selection_deadline' => now()->subDay()->toDateTimeString()]);
    $order = groupOf(1);

    $this->seats->assign($order, ['P-1-1']);

    expect(adminSeatIds($order))->toBe(['P-1-1']);
});

it('confirms changes to the buyer', function () {
    $order = groupOf(2);

    $this->seats->assign($order, ['P-1-1', 'P-1-2']);
    $this->seats->move(seatedEvent(), 'P-1-1', 'P-2-1');

    Mail::assertQueued(SeatMail::class, fn (SeatMail $mail) => $mail->type === MailType::Confirmation && $mail->hasTo($order->email));
    expect(MailLog::where(['order_id' => $order->id, 'type' => MailType::Confirmation])->count())->toBe(2);
});

it('sends wheelchair orders no automatic mail', function () {
    $this->seats->assign(groupOf(1, ['needs_wheelchair' => true]), [wheelchairSeat()->code]);

    Mail::assertNothingQueued();
});

it('offers no change link to wheelchair orders in a manual confirmation', function () {
    setEvent([
        'selection_opens_at' => now()->subDay()->toDateTimeString(),
        'selection_deadline' => now()->addWeek()->toDateTimeString(),
    ]);
    $order = groupOf(1, ['needs_wheelchair' => true]);
    $this->seats->assign($order, [wheelchairSeat()->code]);

    expect((new SeatMail($order, MailType::Confirmation))->render())
        ->toContain('Sitzplatz-Pass ansehen')
        ->not->toContain('Platz ändern');
});

it('counts sold, assigned, free and blocked seats', function () {
    $before = app(SeatStats::class)->snapshot(seatedEvent());
    $order = groupOf(3);
    $cancelled = groupOf(1);
    $cancelled->attendees()->update(['status' => AttendeeStatus::Cancelled]);
    $this->seats->assign($order, ['P-1-1', 'P-1-2']);
    $this->seats->block(seatedEvent(), ['P-2-1'], BlockReason::Press, null);

    $stats = app(SeatStats::class)->snapshot(seatedEvent());

    expect($stats['sold'])->toBe(3)
        ->and($stats['assigned'])->toBe(2)
        ->and($stats['unassigned'])->toBe(1)
        ->and($stats['sellable'])->toBe($before['sellable'] - 1)
        ->and($stats['free'])->toBe($before['free'] - 3)
        ->and($stats['blocked']['press'])->toBe(1)
        ->and($stats['ordersWithoutSeats'])->toBe(1)
        ->and($stats['overbooked'])->toBe(0);
});

it('warns when more tickets are sold than seats exist, but not for wheelchair orders', function () {
    Seat::whereNull('block_reason')->where('code', '!=', 'P-1-1')->update(['block_reason' => BlockReason::Other]);

    groupOf(1);
    groupOf(2, ['needs_wheelchair' => true]);
    expect(app(SeatStats::class)->snapshot(seatedEvent())['overbooked'])->toBe(0);

    groupOf(2);
    expect(app(SeatStats::class)->snapshot(seatedEvent())['overbooked'])->toBe(2);
});
