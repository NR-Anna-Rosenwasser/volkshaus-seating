<?php

namespace App\Services;

use App\Enums\AssignedBy;
use App\Enums\AuditActor;
use App\Enums\MailType;
use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Order;
use App\Models\Seat;
use App\Support\AutoAssignment;
use App\Support\StatusLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Seats everyone left after the deadline. Deterministic, and it only ever
 * adds seats: nobody who already has one is moved.
 */
class AutoAssign
{
    /** Another late order may take the suggested seats while we wait for the lock. */
    private const ATTEMPTS = 3;

    private const LATE_ALERT_SECONDS = 86400;

    public function __construct(
        private readonly BestSeats $bestSeats,
        private readonly BuyerMailer $mailer,
        private readonly AdminAlert $alert,
    ) {}

    public static function lastRunKey(Event $event): string
    {
        return "seating.last-auto-assign.{$event->id}";
    }

    /**
     * @return Collection<int, AutoAssignment>
     */
    public function run(Event $event): Collection
    {
        StatusLog::put(self::lastRunKey($event), now()->toIso8601String());

        $mailed = 0;
        $results = $this->waitingOrders($event)->map(function (Order $order) use (&$mailed): AutoAssignment {
            $result = $this->assign($order, $mailed);
            $mailed += $result->isSeated() ? 1 : 0;

            return $result;
        });

        $unseated = $results->reject(fn (AutoAssignment $result): bool => $result->isSeated());

        if ($unseated->isNotEmpty()) {
            $this->alert->send(
                'Auto-Zuteilung: Bestellungen ohne Platz',
                "{$unseated->count()} Bestellungen haben keinen Platz bekommen: "
                    .$unseated->map(fn (AutoAssignment $result): string => "{$result->order->short_id} ({$result->missing})")->implode(', ').'.',
                $event,
            );
        }

        return $results;
    }

    /**
     * The same work as a real run, rolled back, so the preview can't differ
     * from the result. No mails or alerts.
     *
     * @return Collection<int, AutoAssignment>
     */
    public function preview(Event $event): Collection
    {
        DB::beginTransaction();

        try {
            return $this->waitingOrders($event)->map(fn (Order $order): AutoAssignment => $this->assign($order));
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Seats orders that had no chance to choose, and tickets added to a group
     * that has seats already. Everyone else waits for the batch, which seats
     * the biggest groups first.
     */
    public function seatIfLate(Order $order): void
    {
        if (! $order->event->window()->hasEnded()
            || $order->status !== OrderStatus::Active
            || $order->needs_wheelchair
            || ! $order->attendees()->withoutSeat()->exists()) {
            return;
        }

        $neverChose = ! $order->mailLogs()->where('type', MailType::Link)->exists();

        if (! $neverChose && ! $order->seats()->exists()) {
            return;
        }

        $result = $this->assign($order, 0);

        // Retried at every reconcile, so only the first failure of a day alerts.
        if (! $result->isSeated() && Cache::add("auto-assign-late-alert-{$order->id}", true, self::LATE_ALERT_SECONDS)) {
            $this->alert->send(
                'Auto-Zuteilung: Bestellung ohne Platz',
                "Bestellung {$order->short_id} braucht {$result->missing} Plätze, aber es gibt nicht genug freie Plätze.",
                $order->event,
            );
        }
    }

    /**
     * Biggest groups first: they need the longest free stretches.
     *
     * @return Collection<int, Order>
     */
    public function waitingOrders(Event $event): Collection
    {
        return $event->orders()
            ->choosingForThemselves()
            ->missingSeats()
            ->withTicketCount()
            ->orderByDesc('tickets')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->toBase();
    }

    /**
     * @param  int|null  $mailPosition  Place in the mail batch, null for no mail.
     */
    private function assign(Order $order, ?int $mailPosition = null): AutoAssignment
    {
        return DB::transaction(function () use ($order, $mailPosition): AutoAssignment {
            $order->lock();

            // A sync may have changed the order since the list was read.
            $order->refresh();
            $event = $order->event;
            $waiting = $order->attendees()->withoutSeat()->orderBy('id')->get();
            $own = $order->seats()->pluck('seats.code')->all();

            if ($order->status !== OrderStatus::Active || $waiting->isEmpty()) {
                return new AutoAssignment($order, 0, [], false);
            }

            for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
                $codes = $this->bestSeats->find($event, $waiting->count(), nearCodes: $own);

                if ($codes === []) {
                    break;
                }

                $seats = $event->seats()->whereIn('code', $codes)->orderBy('id')->lockForUpdate()->get();

                if ($seats->contains(fn (Seat $seat): bool => $seat->block_reason !== null || $seat->attendee_id !== null)) {
                    continue;
                }

                $seats->sortBy(['row', 'number'])->values()->each(function (Seat $seat, int $index) use ($waiting): void {
                    $attendee = $waiting[$index];

                    $seat->update(['attendee_id' => $attendee->id, 'assigned_at' => now(), 'assigned_by' => AssignedBy::Auto]);
                    AuditLog::create([
                        'actor' => AuditActor::System,
                        'action' => 'seat_auto_assigned',
                        'seat_id' => $seat->id,
                        'attendee_id' => $attendee->id,
                    ]);
                });

                if ($mailPosition !== null) {
                    $this->mailer->send($order, MailType::AutoAssigned, $this->mailer->delayFor($mailPosition));
                }

                return new AutoAssignment($order, $waiting->count(), $codes, $this->isSplit($order));
            }

            return new AutoAssignment($order, $waiting->count(), [], false);
        });
    }

    private function isSplit(Order $order): bool
    {
        $seats = $order->seats()->get();
        $blocks = $seats->map(fn (Seat $seat): string => "{$seat->section}|{$seat->row}|{$seat->segment}")->unique();

        return $blocks->count() > 1 || $seats->max('number') - $seats->min('number') + 1 !== $seats->count();
    }
}
