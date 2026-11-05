<?php

namespace App\Console\Commands;

use App\Enums\MailType;
use App\Models\Order;
use App\Services\BuyerMailer;
use App\Support\SelectionWindow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seating:send-links')]
#[Description('Send the personal link to orders that have not had it yet')]
class SendSelectionLinks extends Command
{
    public function handle(BuyerMailer $mailer, SelectionWindow $window): int
    {
        // After the deadline, late orders are assigned seats instead.
        if (! $window->isOpen()) {
            return self::SUCCESS;
        }

        $orders = Order::choosingForThemselves()
            ->whereDoesntHave('mailLogs', fn ($query) => $query->where('type', MailType::Link))
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(max(1, (int) config('seating.mail_per_second')) * 60)
            ->get()
            ->filter(fn (Order $order): bool => $order->seatsNeeded() > 0);

        $this->info($mailer->sendBatch($orders, MailType::Link).' links queued.');

        return self::SUCCESS;
    }
}
