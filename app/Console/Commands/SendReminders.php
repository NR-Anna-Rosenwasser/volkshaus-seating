<?php

namespace App\Console\Commands;

use App\Enums\MailType;
use App\Models\Order;
use App\Services\BuyerMailer;
use App\Support\SelectionWindow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seating:send-reminders')]
#[Description('Remind orders that have not chosen all their seats yet')]
class SendReminders extends Command
{
    private const HOURS_AFTER_LINK = 24;

    public function handle(BuyerMailer $mailer, SelectionWindow $window): int
    {
        if (! $window->isOpen()) {
            return self::SUCCESS;
        }

        $due = collect(config('seating.reminder_offsets_days'))
            ->filter(fn (int $days): bool => $window->deadline()->subDays($days)->isPast())
            ->count();

        // Someone who just got the link doesn't need a reminder on top.
        $orders = Order::choosingForThemselves()
            ->where('reminders_sent', '<', $due)
            ->whereHas('mailLogs', fn ($query) => $query
                ->where('type', MailType::Link)
                ->where('sent_at', '<=', now()->subHours(self::HOURS_AFTER_LINK)))
            ->orderBy('id')
            ->get()
            ->filter(fn (Order $order): bool => $order->seats()->count() < $order->seatsNeeded());

        $sent = $mailer->sendBatch($orders, MailType::Reminder, fn (Order $order) => $order->update([
            // Not one per missed offset: a late mail stands in for those before it.
            'reminders_sent' => $due,
            'last_reminder_at' => now(),
        ]));

        $this->info("{$sent} reminders queued.");

        return self::SUCCESS;
    }
}
