<?php

namespace App\Services;

use App\Enums\MailType;
use App\Mail\SeatMail;
use App\Models\MailLog;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class BuyerMailer
{
    /**
     * Logging and queueing share one transaction, so a crash can neither
     * lose a mail nor send it twice.
     */
    public function send(Order $order, MailType $type, int $delaySeconds = 0): void
    {
        DB::transaction(function () use ($order, $type, $delaySeconds): void {
            MailLog::create(['order_id' => $order->id, 'type' => $type, 'sent_at' => now()]);

            $mail = Mail::to($order->email);
            $delaySeconds > 0
                ? $mail->later(now()->addSeconds($delaySeconds), new SeatMail($order, $type))
                : $mail->queue(new SeatMail($order, $type));
        });
    }

    /**
     * Spreads mails out to stay under the provider's rate limit.
     *
     * @param  iterable<Order>  $orders
     */
    public function sendBatch(iterable $orders, MailType $type, ?callable $afterEach = null): int
    {
        $perSecond = max(1, (int) config('seating.mail_per_second'));
        $count = 0;

        foreach ($orders as $order) {
            DB::transaction(function () use ($order, $type, $perSecond, $count, $afterEach): void {
                $this->send($order, $type, (int) floor($count / $perSecond));

                if ($afterEach !== null) {
                    $afterEach($order);
                }
            });

            $count++;
        }

        return $count;
    }
}
