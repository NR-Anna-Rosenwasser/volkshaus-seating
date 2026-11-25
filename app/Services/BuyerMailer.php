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
    public function send(Order $order, MailType $type, int $delaySeconds = 0, ?bool $changed = null): void
    {
        DB::transaction(function () use ($order, $type, $delaySeconds, $changed): void {
            // Unless the caller knows better: seats confirmed once and confirmed again have changed.
            $changed ??= $type === MailType::Confirmation && $this->hadSeatMail($order);

            MailLog::create(['order_id' => $order->id, 'type' => $type, 'sent_at' => now()]);

            $mailable = new SeatMail($order, $type, $changed);
            $mail = Mail::to($order->email);
            $delaySeconds > 0
                ? $mail->later(now()->addSeconds($delaySeconds), $mailable)
                : $mail->queue($mailable);
        });
    }

    private function hadSeatMail(Order $order): bool
    {
        return MailLog::where('order_id', $order->id)
            ->whereIn('type', [MailType::Confirmation, MailType::AutoAssigned])
            ->exists();
    }

    /**
     * Seconds to hold back the mail at this position of a batch, to stay
     * under the provider's rate limit.
     */
    public function delayFor(int $position): int
    {
        return (int) floor($position / max(1, (int) config('seating.mail_per_second')));
    }

    /**
     * @param  iterable<Order>  $orders
     */
    public function sendBatch(iterable $orders, MailType $type, ?callable $afterEach = null): int
    {
        $count = 0;

        foreach ($orders as $order) {
            DB::transaction(function () use ($order, $type, $count, $afterEach): void {
                $this->send($order, $type, $this->delayFor($count));

                if ($afterEach !== null) {
                    $afterEach($order);
                }
            });

            $count++;
        }

        return $count;
    }
}
