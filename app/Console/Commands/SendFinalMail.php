<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsPerEvent;
use App\Enums\MailType;
use App\Enums\OrderStatus;
use App\Models\Event;
use App\Services\BuyerMailer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seating:send-final {--event= : Only this event (slug)} {--now : Send now instead of waiting for the configured day. Needs --event}')]
#[Description('Send everyone their seats shortly before the event')]
class SendFinalMail extends Command
{
    use RunsPerEvent;

    public function handle(BuyerMailer $mailer): int
    {
        // Skipping the schedule is a decision about one event, never about all of them.
        if ($this->option('now') && ! $this->option('event')) {
            $this->error('--now needs --event.');

            return self::FAILURE;
        }

        return $this->forEachEvent(function (Event $event) use ($mailer): int {
            if ($event->starts_at === null) {
                return self::SUCCESS;
            }

            $sendAt = $event->starts_at->copy()->subDays($event->final_mail_offset_days);

            if ($event->starts_at->isPast() || ($sendAt->isFuture() && ! $this->option('now'))) {
                return self::SUCCESS;
            }

            $orders = $event->orders()
                ->where('status', OrderStatus::Active)
                ->whereHas('seats')
                ->whereDoesntHave('mailLogs', fn ($query) => $query->where('type', MailType::Final))
                ->orderBy('id')
                ->get();

            $this->info($mailer->sendBatch($orders, MailType::Final).' final mails queued.');

            return self::SUCCESS;
        });
    }
}
