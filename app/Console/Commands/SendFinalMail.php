<?php

namespace App\Console\Commands;

use App\Enums\MailType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\BuyerMailer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('seating:send-final {--now : Send now instead of waiting for the configured day}')]
#[Description('Send everyone their seats shortly before the event')]
class SendFinalMail extends Command
{
    public function handle(BuyerMailer $mailer): int
    {
        $date = config('seating.event_date');

        if (! $date) {
            return self::SUCCESS;
        }

        $event = Carbon::parse($date, config('app.timezone'));
        $sendAt = $event->copy()->subDays((int) config('seating.final_mail_offset_days'));

        if ($event->isPast() || ($sendAt->isFuture() && ! $this->option('now'))) {
            return self::SUCCESS;
        }

        $orders = Order::where('status', OrderStatus::Active)
            ->whereHas('seats')
            ->whereDoesntHave('mailLogs', fn ($query) => $query->where('type', MailType::Final))
            ->orderBy('id')
            ->get();

        $this->info($mailer->sendBatch($orders, MailType::Final).' final mails queued.');

        return self::SUCCESS;
    }
}
