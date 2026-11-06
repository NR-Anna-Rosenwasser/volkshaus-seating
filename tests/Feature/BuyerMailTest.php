<?php

use App\Enums\AttendeeStatus;
use App\Enums\MailType;
use App\Enums\OrderStatus;
use App\Mail\AdminAlertMail;
use App\Mail\SeatMail;
use App\Models\Attendee;
use App\Models\MailLog;
use App\Models\Order;
use App\Services\AdminAlert;
use App\Services\BuyerMailer;
use App\Services\SeatSelection;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    seatedEvent();

    $this->travelTo('2027-01-01 12:00:00');

    setEvent([
        'name' => 'Hässig am Mittwoch',
        'starts_at' => '2027-02-01 20:00',
        'doors_at' => '2027-02-01 19:00',
        'selection_opens_at' => '2026-12-20 10:00',
        'selection_deadline' => '2027-01-25 23:59',
        'reminder_offsets_days' => [14, 7, 2],
        'contact_email' => 'hallo@example.ch',
    ]);
    config([
        'seating.final_mail_offset_days' => 2,
        'seating.mail_per_second' => 5,
        'seating.admin_emails' => ['admin@example.ch'],
        'seating.mail_failure_threshold' => 3,
    ]);
});

function buyer(int $tickets = 2, array $attributes = []): Order
{
    $order = Order::factory()->create(['first_name' => 'Rita', 'last_name' => 'Muster', ...$attributes]);
    Attendee::factory()->count($tickets)->for($order)->create();

    return $order;
}

function seated(Order $order, string ...$seatIds): void
{
    foreach ($order->attendees as $index => $attendee) {
        if (isset($seatIds[$index])) {
            seat($seatIds[$index])->update(['attendee_id' => $attendee->id]);
        }
    }
}

function linkSent(Order $order, string $when): void
{
    MailLog::create(['order_id' => $order->id, 'type' => MailType::Link, 'sent_at' => $when]);
}

function queuedOf(MailType $type): int
{
    return Mail::queued(SeatMail::class, fn (SeatMail $mail) => $mail->type === $type)->count();
}

it('sends each order its link once', function () {
    $order = buyer();

    $this->artisan('seating:send-links')->assertSuccessful();
    $this->artisan('seating:send-links')->assertSuccessful();

    Mail::assertQueued(SeatMail::class, fn (SeatMail $mail) => $mail->hasTo($order->email) && $mail->type === MailType::Link);
    expect(queuedOf(MailType::Link))->toBe(1)
        ->and(MailLog::where('type', MailType::Link)->count())->toBe(1);
});

it('holds links until selection opens', function () {
    buyer();
    $this->travelTo('2026-12-19 12:00:00');

    $this->artisan('seating:send-links')->assertSuccessful();

    Mail::assertNothingQueued();

    $this->travelTo('2026-12-20 10:01:00');
    $this->artisan('seating:send-links')->assertSuccessful();

    expect(queuedOf(MailType::Link))->toBe(1);
});

it('sends no links after the deadline', function () {
    buyer();
    $this->travelTo('2027-01-26 12:00:00');

    $this->artisan('seating:send-links')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('sends no link to orders that do not choose for themselves', function (array $attributes) {
    buyer(2, $attributes);

    $this->artisan('seating:send-links')->assertSuccessful();

    Mail::assertNothingQueued();
})->with([
    'wheelchair' => [['needs_wheelchair' => true]],
    'cancelled' => [['status' => OrderStatus::Cancelled]],
]);

it('sends no link to an order without seated tickets', function () {
    $order = buyer();
    $order->attendees()->update(['status' => AttendeeStatus::Cancelled]);

    $this->artisan('seating:send-links')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('spreads a batch out to respect the rate limit', function () {
    config(['seating.mail_per_second' => 2]);
    $orders = Order::factory()->count(5)->create();

    $mailer = new class extends BuyerMailer
    {
        public array $delays = [];

        public function send(Order $order, MailType $type, int $delaySeconds = 0): void
        {
            $this->delays[] = $delaySeconds;
        }
    };

    $mailer->sendBatch($orders, MailType::Link);

    expect($mailer->delays)->toBe([0, 0, 1, 1, 2]);
});

it('contains the link, seat count, deadline and plan', function () {
    $order = buyer();

    $mail = (new SeatMail($order, MailType::Link))->render();

    expect($mail)
        ->toContain($order->selectionUrl())
        ->toContain('Ihr braucht 2 Plätze')
        ->toContain('Montag, 25. Januar 2027, 23:59 Uhr')
        ->toContain(route('plan', seatedEvent()));
});

it('has an HTML and a text version of every mail', function (MailType $type) {
    $order = buyer();
    seated($order, 'P-12-7', 'P-12-8');

    $mail = new SeatMail($order, $type);

    expect($mail->render())->toContain('Hallo Rita')->not->toContain('ß');

    $mail->assertSeeInText('Hallo Rita');
})->with([MailType::Link, MailType::Confirmation, MailType::Reminder, MailType::AutoAssigned, MailType::Final]);

it('confirms current seats with a link to change them', function () {
    $order = buyer();
    seated($order, 'P-12-7', 'P-12-8');

    expect((new SeatMail($order, MailType::Confirmation))->render())
        ->toContain('Parterre · Reihe 12 · Platz 7')
        ->toContain('Plätze ändern')
        ->toContain('bis Montag, 25. Januar 2027');
});

it('points to the organisers once changes are closed', function () {
    $order = buyer();
    seated($order, 'P-12-7', 'P-12-8');
    $this->travelTo('2027-01-26 12:00:00');

    expect((new SeatMail($order, MailType::Confirmation))->render())
        ->toContain('hallo@example.ch')
        ->not->toContain('Plätze ändern');
});

it('addresses a single ticket as du in every mail', function (MailType $type, string $subject, string $body) {
    $order = buyer(tickets: 1);
    seated($order, 'P-12-7');

    $mail = new SeatMail($order, $type);
    $mail->assertHasSubject("{$subject}: Hässig am Mittwoch");

    expect($mail->render())
        ->toContain($body)
        ->toContain('Bitte leite ihn nicht weiter')
        ->not->toMatch('/\b(ihr|euch|euer|eure[nrs]?)\b/iu')
        ->not->toContain('Plätze');
})->with([
    [MailType::Link, 'Wähle deinen Platz', 'Du kannst jetzt deinen Platz wählen.'],
    [MailType::Confirmation, 'Dein Platz', 'Das ist dein aktueller Platz:'],
    [MailType::Reminder, 'Erinnerung: Wähle deinen Platz', 'Sonst teilen wir dir automatisch einen Platz zu.'],
    [MailType::AutoAssigned, 'Wir haben dir einen Platz zugeteilt', 'Wir haben dir diesen Platz zugeteilt:'],
    [MailType::Final, 'Bis bald: dein Platz', 'Wir freuen uns auf dich am'],
]);

it('stays plural when one seat of a group is still missing', function () {
    $order = buyer(tickets: 3);
    seated($order, 'P-12-7', 'P-12-8');

    $mail = new SeatMail($order, MailType::Reminder);
    $mail->assertHasSubject('Erinnerung: Wählt eure Plätze: Hässig am Mittwoch');

    expect($mail->render())->toContain('Ihr braucht 3 Plätze')->toContain('Bitte leitet ihn nicht weiter');
});

it('switches to du when a cancellation leaves one ticket', function () {
    $order = buyer(tickets: 2);
    $order->attendees()->first()->update(['status' => AttendeeStatus::Cancelled]);

    expect((new SeatMail($order, MailType::Link))->render())->toContain('Du kannst jetzt deinen Platz wählen.');
});

it('warns about automatic assignment in reminders', function () {
    expect((new SeatMail(buyer(), MailType::Reminder))->render())->toContain('Sonst teilen wir euch automatisch Plätze zu');
});

it('tells auto-assigned buyers that changes go through the organisers', function () {
    $order = buyer();
    seated($order, 'P-1-1', 'P-1-2');

    expect((new SeatMail($order, MailType::AutoAssigned))->render())
        ->toContain('Wir haben euch diese Plätze zugeteilt')
        ->toContain('hallo@example.ch');
});

it('gives the final mail its seats, doors time and pass', function () {
    $order = buyer();
    seated($order, 'P-1-1', 'P-1-2');

    expect((new SeatMail($order, MailType::Final))->render())
        ->toContain('Türöffnung: Montag, 1. Februar 2027, 19:00 Uhr')
        ->toContain($order->selectionUrl().'/ticket')
        ->toContain('Einlass nur mit dem Ticket von Hi.Events');
});

it('lets replies reach the organisers', function () {
    (new SeatMail(buyer(), MailType::Link))->assertHasReplyTo('hallo@example.ch');
});

it('confirms after every save', function () {
    $order = buyer();

    app(SeatSelection::class)->save($order, ['P-5-1', 'P-5-2']);
    app(SeatSelection::class)->save($order, ['P-5-2', 'P-5-3']);

    expect(queuedOf(MailType::Confirmation))->toBe(2);
});

it('sends reminders once per offset', function () {
    $order = buyer();
    linkSent($order, '2026-12-20 10:00:00');

    $this->artisan('seating:send-reminders')->assertSuccessful();
    expect(queuedOf(MailType::Reminder))->toBe(0);

    $this->travelTo('2027-01-12 12:00:00');
    $this->artisan('seating:send-reminders')->assertSuccessful();
    $this->artisan('seating:send-reminders')->assertSuccessful();
    expect(queuedOf(MailType::Reminder))->toBe(1);

    $this->travelTo('2027-01-19 12:00:00');
    $this->artisan('seating:send-reminders')->assertSuccessful();
    expect(queuedOf(MailType::Reminder))->toBe(2);

    $this->travelTo('2027-01-24 12:00:00');
    $this->artisan('seating:send-reminders')->assertSuccessful();
    expect(queuedOf(MailType::Reminder))->toBe(3)
        ->and($order->refresh()->reminders_sent)->toBe(3)
        ->and($order->last_reminder_at)->not->toBeNull();
});

it('only reminds incomplete orders', function () {
    $complete = buyer();
    $partial = buyer();
    linkSent($complete, '2026-12-20 10:00:00');
    linkSent($partial, '2026-12-20 10:00:00');
    seated($complete, 'P-1-1', 'P-1-2');
    seated($partial, 'P-2-1');

    $this->travelTo('2027-01-12 12:00:00');
    $this->artisan('seating:send-reminders')->assertSuccessful();

    Mail::assertQueued(SeatMail::class, fn (SeatMail $mail) => $mail->hasTo($partial->email));
    Mail::assertNotQueued(SeatMail::class, fn (SeatMail $mail) => $mail->hasTo($complete->email));
});

it('catches up with one reminder, not several', function () {
    $order = buyer();
    linkSent($order, '2026-12-20 10:00:00');

    $this->travelTo('2027-01-24 12:00:00');
    $this->artisan('seating:send-reminders')->assertSuccessful();

    expect(queuedOf(MailType::Reminder))->toBe(1)
        ->and($order->refresh()->reminders_sent)->toBe(3);
});

it('does not remind right after the link', function () {
    $order = buyer();
    $this->travelTo('2027-01-24 12:00:00');
    linkSent($order, '2027-01-24 09:00:00');

    $this->artisan('seating:send-reminders')->assertSuccessful();

    expect(queuedOf(MailType::Reminder))->toBe(0);
});

it('never reminds wheelchair orders or after the deadline', function () {
    $order = buyer(2, ['needs_wheelchair' => true]);
    linkSent($order, '2026-12-20 10:00:00');
    linkSent(buyer(), '2026-12-20 10:00:00');

    $this->travelTo('2027-01-12 12:00:00');
    $this->artisan('seating:send-reminders')->assertSuccessful();
    expect(queuedOf(MailType::Reminder))->toBe(1);

    $this->travelTo('2027-01-26 12:00:00');
    $this->artisan('seating:send-reminders')->assertSuccessful();
    expect(queuedOf(MailType::Reminder))->toBe(1);
});

it('sends the final mail once, on the configured day', function () {
    $order = buyer();
    seated($order, 'P-1-1', 'P-1-2');

    $this->travelTo('2027-01-29 12:00:00');
    $this->artisan('seating:send-final')->assertSuccessful();
    expect(queuedOf(MailType::Final))->toBe(0);

    $this->travelTo('2027-01-30 21:00:00');
    $this->artisan('seating:send-final')->assertSuccessful();
    $this->artisan('seating:send-final')->assertSuccessful();
    expect(queuedOf(MailType::Final))->toBe(1);
});

it('includes wheelchair orders in the final mail once they have seats', function () {
    $order = buyer(1, ['needs_wheelchair' => true]);
    seated($order, 'P-1-1');
    buyer(1, ['needs_wheelchair' => true]);

    $this->artisan('seating:send-final', ['--event' => 'test', '--now' => true])->assertSuccessful();

    expect(queuedOf(MailType::Final))->toBe(1);
});

it('skips cancelled orders in the final mail', function () {
    $order = buyer(1, ['status' => OrderStatus::Cancelled]);
    seated($order, 'P-1-1');

    $this->artisan('seating:send-final', ['--event' => 'test', '--now' => true])->assertSuccessful();

    Mail::assertNothingQueued();
});

it('sends no mail after the event', function () {
    $order = buyer();
    seated($order, 'P-1-1', 'P-1-2');
    $this->travelTo('2027-02-02 12:00:00');

    $this->artisan('seating:send-final', ['--event' => 'test', '--now' => true])->assertSuccessful();

    Mail::assertNothingQueued();
});

it('alerts every admin address', function () {
    config(['seating.admin_emails' => ['a@example.ch', 'b@example.ch']]);

    app(AdminAlert::class)->send('Titel', 'Text');

    Mail::assertSent(AdminAlertMail::class, fn (AdminAlertMail $mail) => $mail->hasTo('a@example.ch') && $mail->hasTo('b@example.ch'));
});

it('warns once when mails keep failing', function () {
    foreach (range(1, 5) as $failure) {
        app(AdminAlert::class)->mailFailed();
    }

    Mail::assertSentCount(1);
});

it('runs mail on a schedule', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('seating:send-links')
        ->expectsOutputToContain('seating:send-reminders')
        ->expectsOutputToContain('seating:send-final')
        ->assertSuccessful();
});
