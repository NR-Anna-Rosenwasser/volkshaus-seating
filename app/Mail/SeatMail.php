<?php

namespace App\Mail;

use App\Enums\MailType;
use App\Models\Order;
use App\Models\Seat;
use App\Support\SelectionWindow;
use App\Support\VenueLayout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Everything buyers receive. Seats and dates are read when the mail is sent,
 * so a queued mail never shows outdated seats.
 */
class SeatMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public Order $order, public MailType $type) {}

    public function envelope(): Envelope
    {
        $event = config('seating.event_name');
        $contact = config('seating.contact_email');

        return new Envelope(
            subject: $event ? "{$this->subjectLine()}: {$event}" : $this->subjectLine(),
            replyTo: $contact ? [new Address($contact)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.seat-mail',
            text: 'mail.seat-mail-text',
            with: [...$this->copy(), 'seats' => $this->shownSeats()],
        );
    }

    private function subjectLine(): string
    {
        return match ($this->type) {
            MailType::Link => 'Wählt eure Plätze',
            MailType::Confirmation => 'Eure Plätze',
            MailType::Reminder => 'Erinnerung: Wählt eure Plätze',
            MailType::AutoAssigned => 'Wir haben euch Plätze zugeteilt',
            MailType::Final => 'Bis bald: eure Plätze',
            default => throw new LogicException("{$this->type->value} is not a buyer mail."),
        };
    }

    /**
     * @return array{greeting: string, lines: list<string>, closing: list<string>, buttonLabel: string, buttonUrl: string}
     */
    private function copy(): array
    {
        $greeting = "Hallo {$this->order->first_name}";
        $window = app(SelectionWindow::class);
        $deadline = SelectionWindow::format($window->deadline());
        $selection = $this->order->selectionUrl();
        $pass = "{$selection}/ticket";
        $needed = $this->order->seatsNeeded();
        $seats = $needed === 1 ? 'einen Platz' : "{$needed} Plätze";
        $entry = 'Einlass nur mit dem Ticket von Hi.Events. Dieses Mail zeigt nur euren Sitzplatz.';
        $contact = config('seating.contact_email');
        $changes = $contact ? "Für Änderungen schreibt uns an {$contact}." : 'Für Änderungen meldet euch bei uns.';

        return match ($this->type) {
            MailType::Link => [
                'greeting' => $greeting,
                'lines' => [
                    "Ihr könnt jetzt eure Plätze wählen. Ihr braucht {$seats}.",
                    "Ihr könnt eure Wahl bis {$deadline} ändern.",
                ],
                'closing' => ['Wie voll es schon ist, seht ihr hier: '.route('plan')],
                'buttonLabel' => 'Plätze wählen',
                'buttonUrl' => $selection,
            ],
            MailType::Confirmation => [
                'greeting' => $greeting,
                'lines' => ['Das sind eure aktuellen Plätze:'],
                'closing' => [$window->isOpen() ? "Ihr könnt sie bis {$deadline} ändern." : $changes, $entry],
                'buttonLabel' => $window->isOpen() ? 'Plätze ändern' : 'Sitzplatz-Pass ansehen',
                'buttonUrl' => $window->isOpen() ? $selection : $pass,
            ],
            MailType::Reminder => [
                'greeting' => $greeting,
                'lines' => [
                    "Ihr habt noch nicht alle Plätze gewählt. Ihr braucht {$seats}.",
                    "Wählt sie bis {$deadline}. Sonst teilen wir euch automatisch Plätze zu.",
                ],
                'closing' => [],
                'buttonLabel' => 'Plätze wählen',
                'buttonUrl' => $selection,
            ],
            MailType::AutoAssigned => [
                'greeting' => $greeting,
                'lines' => ['Wir haben euch diese Plätze zugeteilt:'],
                'closing' => [$changes, $entry],
                'buttonLabel' => 'Sitzplatz-Pass ansehen',
                'buttonUrl' => $pass,
            ],
            MailType::Final => [
                'greeting' => $greeting,
                'lines' => array_values(array_filter([
                    $this->eventLine('seating.event_date', 'Wir freuen uns auf euch am %s.'),
                    $this->eventLine('seating.doors_at', 'Türöffnung: %s.'),
                    'Das sind eure Plätze:',
                ])),
                'closing' => [$entry],
                'buttonLabel' => 'Sitzplatz-Pass ansehen',
                'buttonUrl' => $pass,
            ],
            default => throw new LogicException("{$this->type->value} is not a buyer mail."),
        };
    }

    private function eventLine(string $key, string $line): ?string
    {
        $date = config($key);

        return $date ? sprintf($line, SelectionWindow::format(Carbon::parse($date, config('app.timezone')))) : null;
    }

    /**
     * @return list<string>
     */
    private function shownSeats(): array
    {
        if (in_array($this->type, [MailType::Link, MailType::Reminder], true)) {
            return [];
        }

        $layout = app(VenueLayout::class);

        return $this->order->seats()
            ->orderBy('section')->orderBy('row')->orderBy('number')
            ->get()
            ->map(fn (Seat $seat): string => "{$layout->sectionLabel($seat->section)} · Reihe {$seat->row} · Platz {$seat->number}")
            ->all();
    }
}
