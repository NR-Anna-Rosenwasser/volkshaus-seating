<?php

namespace App\Services\Hievents;

use App\Enums\AttendeeStatus;
use App\Enums\AuditActor;
use App\Enums\MailType;
use App\Enums\OrderStatus;
use App\Enums\SyncResult;
use App\Mail\WheelchairNotice;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\MailLog;
use App\Models\Order;
use App\Models\Seat;
use App\Services\AdminAlert;
use App\Services\AutoAssign;
use App\Services\BuyerMailer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Brings local orders in line with Hi.Events. Webhooks and reconciliation
 * both go through here, and applying the same data twice changes nothing.
 */
class OrderSync
{
    /** @var array<string, array{order: Order, type: MailType}> */
    private array $notices = [];

    public function __construct(
        private readonly AdminAlert $alert,
        private readonly BuyerMailer $mailer,
        private readonly AutoAssign $autoAssign,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  An OrderResource.
     */
    public function syncOrder(array $payload): SyncResult
    {
        if (! $this->isOurEvent($payload)) {
            return SyncResult::Ignored;
        }

        return $this->run((string) $payload['id'], fn (): SyncResult => $this->applyOrder($payload));
    }

    /**
     * @param  array<string, mixed>  $payload  An AttendeeResource.
     */
    public function syncAttendee(array $payload): SyncResult
    {
        if (! $this->isOurEvent($payload)) {
            return SyncResult::Ignored;
        }

        return $this->run((string) $payload['order_id'], function () use ($payload): SyncResult {
            $order = Order::where('hievents_order_id', (string) $payload['order_id'])->first();

            // Unknown means unpaid or not there yet. The order's own event will bring it in.
            if ($order === null) {
                return SyncResult::Ignored;
            }

            $changed = $this->applyAttendee($order, $payload);
            $changed = $this->syncWheelchairRequest($order) || $changed;

            return $changed ? SyncResult::Updated : SyncResult::Unchanged;
        });
    }

    /**
     * @param  callable(): SyncResult  $work
     */
    private function run(string $hieventsOrderId, callable $work): SyncResult
    {
        if (config('seating.seated_product_ids') === []) {
            throw new RuntimeException('SEATED_PRODUCT_IDS is empty, so no attendee would get a seat.');
        }

        $this->notices = [];

        $result = DB::transaction(function () use ($hieventsOrderId, $work): SyncResult {
            Order::lockByHieventsId($hieventsOrderId);

            return $work();
        });

        foreach ($this->notices as $notice) {
            $this->sendNotice($notice['order'], $notice['type']);
        }

        $order = Order::where('hievents_order_id', $hieventsOrderId)->first();

        if ($order !== null) {
            $this->autoAssign->seatIfLate($order);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyOrder(array $payload): SyncResult
    {
        $order = Order::where('hievents_order_id', (string) $payload['id'])->first();
        $cancelled = $payload['status'] === 'CANCELLED';

        if ($order === null && $payload['status'] !== 'COMPLETED') {
            return SyncResult::Ignored;
        }

        // Unpaid and abandoned states never follow COMPLETED, so a known order only moves to cancelled.
        if ($order !== null && ! $cancelled && $payload['status'] !== 'COMPLETED') {
            return SyncResult::Unchanged;
        }

        $created = $order === null;
        $order ??= new Order([
            'hievents_order_id' => (string) $payload['id'],
            // Never used as a link: the link email issues a fresh token.
            'token_hash' => hash('sha256', Str::random(64)),
            // Hi.Events sends UTC. Laravel stores timestamps as written, in the app's timezone.
            'created_at' => Carbon::parse($payload['created_at'])->setTimezone(config('app.timezone')),
        ]);

        $wasCancelled = $order->status === OrderStatus::Cancelled;
        $order->fill([
            'hievents_public_id' => $payload['public_id'] ?? null,
            'short_id' => $payload['short_id'] ?? null,
            'email' => $payload['email'] ?? null,
            'first_name' => $payload['first_name'] ?? null,
            'last_name' => $payload['last_name'] ?? null,
            'status' => $cancelled ? OrderStatus::Cancelled : OrderStatus::Active,
        ]);

        // The order lists leave answers out, so absence says nothing.
        if (is_array($payload['question_answers'] ?? null)) {
            $order->needs_wheelchair = $this->askedForWheelchair($payload['question_answers']);
        }

        $changed = $order->isDirty();
        $order->save();

        foreach ($payload['attendees'] ?? [] as $attendee) {
            $changed = $this->applyAttendee($order, $attendee) || $changed;
        }

        if ($cancelled) {
            $changed = $this->cancelAttendees($order->attendees()->where('status', AttendeeStatus::Active)->get()) || $changed;
        }

        $changed = $this->syncWheelchairRequest($order) || $changed;

        return match (true) {
            $created => SyncResult::Created,
            $cancelled && ! $wasCancelled => SyncResult::Cancelled,
            $changed => SyncResult::Updated,
            default => SyncResult::Unchanged,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyAttendee(Order $order, array $payload): bool
    {
        $attendee = Attendee::firstOrNew(['hievents_attendee_id' => (string) $payload['id']]);
        $wasActive = $attendee->exists && $attendee->status === AttendeeStatus::Active;

        $attendee->fill([
            'order_id' => $order->id,
            'hievents_product_id' => (string) $payload['product_id'],
            'first_name' => $payload['first_name'] ?? null,
            'last_name' => $payload['last_name'] ?? null,
            'needs_seat' => in_array((string) $payload['product_id'], config('seating.seated_product_ids'), true),
            'status' => $payload['status'] === 'CANCELLED' ? AttendeeStatus::Cancelled : AttendeeStatus::Active,
        ]);

        $changed = $attendee->isDirty();
        $attendee->save();

        if ($wasActive && $attendee->status === AttendeeStatus::Cancelled) {
            $this->releaseSeats(collect([$attendee->id]));
            $this->notices[MailType::Confirmation->value] = ['order' => $order, 'type' => MailType::Confirmation];
        }

        return $changed;
    }

    /**
     * @param  Collection<int, Attendee>  $attendees
     */
    private function cancelAttendees(Collection $attendees): bool
    {
        if ($attendees->isEmpty()) {
            return false;
        }

        Attendee::whereKey($attendees->modelKeys())->update(['status' => AttendeeStatus::Cancelled]);
        $this->releaseSeats($attendees->modelKeys());

        return true;
    }

    /**
     * @param  Collection<int, int>|list<int>  $attendeeIds
     */
    private function releaseSeats(Collection|array $attendeeIds): void
    {
        $seats = Seat::whereIn('attendee_id', $attendeeIds)->orderBy('id')->lockForUpdate()->get();

        foreach ($seats as $seat) {
            AuditLog::create([
                'actor' => AuditActor::System,
                'action' => 'seat_released',
                'seat_id' => $seat->id,
                'attendee_id' => $seat->attendee_id,
                'before' => ['assigned_by' => $seat->assigned_by?->value],
            ]);

            $seat->update($seat->vacantAttributes());
        }
    }

    /**
     * Notifies once per request, and again if a cancelled request comes back.
     */
    private function syncWheelchairRequest(Order $order): bool
    {
        $wanted = $order->needs_wheelchair
            && $order->status === OrderStatus::Active
            && $order->attendees()->where('status', AttendeeStatus::Active)->exists();

        if ($wanted === ($order->wheelchair_notified_at !== null)) {
            return false;
        }

        $order->update(['wheelchair_notified_at' => $wanted ? now() : null]);
        $type = $wanted ? MailType::WheelchairRequested : MailType::WheelchairCancelled;
        $this->notices[$type->value] = ['order' => $order, 'type' => $type];

        return true;
    }

    private function sendNotice(Order $order, MailType $type): void
    {
        if ($type === MailType::Confirmation) {
            // Only if seats remain. Wheelchair orders hear from the organisers instead.
            if ($order->status === OrderStatus::Active && ! $order->needs_wheelchair && $order->seats()->exists()) {
                $this->mailer->send($order, $type);
            }

            return;
        }

        $recipients = config('seating.wheelchair_notify_emails');

        if ($recipients === []) {
            $this->alert->send(
                'Rollstuhlplatz: keine Empfänger',
                "Bestellung {$order->short_id}: WHEELCHAIR_NOTIFY_EMAILS ist leer.",
            );

            return;
        }

        Mail::to($recipients)->queue(new WheelchairNotice($order, $type));
        MailLog::create(['order_id' => $order->id, 'type' => $type, 'sent_at' => now()]);
    }

    /**
     * @param  list<array<string, mixed>>  $answers
     */
    private function askedForWheelchair(array $answers): bool
    {
        return collect($answers)->contains(
            fn (array $answer): bool => in_array((string) $answer['question_id'], config('seating.wheelchair_question_ids'), true)
                && filled($answer['answer'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isOurEvent(array $payload): bool
    {
        return (string) ($payload['event_id'] ?? '') === (string) config('seating.event_id');
    }
}
