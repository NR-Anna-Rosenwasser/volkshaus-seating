<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsPerEvent;
use App\Enums\MailType;
use App\Models\Event;
use App\Models\Order;
use App\Services\BuyerMailer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seating:send-reminders {--event= : Only this event (slug)} {--now : Remind everyone missing seats, without waiting for the configured days. Needs --event}')]
#[Description('Remind orders that have not chosen all their seats yet')]
class SendReminders extends Command
{
    use RunsPerEvent;

    private const HOURS_AFTER_LINK = 24;

    /** Keeps a double click from reminding everyone twice. */
    private const HOURS_BETWEEN_MANUAL = 24;

    public function handle(BuyerMailer $mailer): int
    {
        // Skipping the schedule is a decision about one event, never about all of them.
        if ($this->option('now') && ! $this->option('event')) {
            $this->error('--now needs --event.');

            return self::FAILURE;
        }

        return $this->forEachEvent(fn (Event $event): int => $this->remind($event, $mailer));
    }

    private function remind(Event $event, BuyerMailer $mailer): int
    {
        $window = $event->window();

        if (! $window->isOpen()) {
            return self::SUCCESS;
        }

        $due = collect($event->reminder_offsets_days)
            ->filter(fn (int $days): bool => $window->deadline()->subDays($days)->isPast())
            ->count();

        // Someone who just got the link doesn't need a reminder on top.
        $orders = $event->orders()
            ->choosingForThemselves()
            ->when(
                $this->option('now'),
                fn ($query) => $query->where(fn ($quiet) => $quiet
                    ->whereNull('last_reminder_at')
                    ->orWhere('last_reminder_at', '<=', now()->subHours(self::HOURS_BETWEEN_MANUAL))),
                fn ($query) => $query->where('reminders_sent', '<', $due),
            )
            ->whereHas('mailLogs', fn ($query) => $query
                ->where('type', MailType::Link)
                ->where('sent_at', '<=', now()->subHours(self::HOURS_AFTER_LINK)))
            ->orderBy('id')
            ->get()
            ->filter(fn (Order $order): bool => $order->seats()->count() < $order->seatsNeeded());

        $sent = $mailer->sendBatch($orders, MailType::Reminder, fn (Order $order) => $order->update([
            // Not one per missed offset: a late mail stands in for those before it.
            // Extra reminders leave the schedule as it is.
            'reminders_sent' => max($order->reminders_sent, $this->option('now') ? 0 : $due),
            'last_reminder_at' => now(),
        ]));

        $this->info("{$sent} reminders queued.");

        return self::SUCCESS;
    }
}
