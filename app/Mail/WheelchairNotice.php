<?php

namespace App\Mail;

use App\Enums\AttendeeStatus;
use App\Enums\MailType;
use App\Models\Attendee;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the organisers about a wheelchair request. Buyers never get this.
 */
class WheelchairNotice extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Order $order, public MailType $type) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->cancelled()
            ? "Rollstuhlplatz storniert: Bestellung {$this->order->short_id}"
            : "Rollstuhlplatz gewünscht: Bestellung {$this->order->short_id}");
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.wheelchair-notice',
            text: 'mail.wheelchair-notice-text',
            with: [
                'cancelled' => $this->cancelled(),
                'tickets' => $this->order->attendees()
                    ->where('status', AttendeeStatus::Active)
                    ->count(),
                'wheelchairAttendees' => $this->order->attendees()
                    ->where('needs_wheelchair', true)
                    ->get()
                    ->map(fn (Attendee $attendee): string => trim("{$attendee->first_name} {$attendee->last_name}")),
            ],
        );
    }

    private function cancelled(): bool
    {
        return $this->type === MailType::WheelchairCancelled;
    }
}
