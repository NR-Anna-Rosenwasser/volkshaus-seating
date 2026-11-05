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
            MailType::Link => $this->order->say('Wähle deinen Platz', 'Wählt eure Plätze'),
            MailType::Confirmation => $this->order->say('Dein Platz', 'Eure Plätze'),
            MailType::Reminder => $this->order->say('Erinnerung: Wähle deinen Platz', 'Erinnerung: Wählt eure Plätze'),
            MailType::AutoAssigned => $this->order->say('Wir haben dir einen Platz zugeteilt', 'Wir haben euch Plätze zugeteilt'),
            MailType::Final => $this->order->say('Bis bald: dein Platz', 'Bis bald: eure Plätze'),
            default => throw new LogicException("{$this->type->value} is not a buyer mail."),
        };
    }

    /**
     * @return array{greeting: string, lines: list<string>, closing: list<string>, buttonLabel: string, buttonUrl: string, personalNote: string}
     */
    private function copy(): array
    {
        $window = app(SelectionWindow::class);
        $deadline = SelectionWindow::format($window->deadline());
        $selection = $this->order->selectionUrl();
        $pass = "{$selection}/ticket";
        $canChange = $window->isOpen() && ! $this->order->needs_wheelchair;
        $needed = $this->order->seatsNeeded();
        $entry = $this->order->say(
            'Einlass nur mit dem Ticket von Hi.Events. Dieses Mail zeigt nur deinen Sitzplatz.',
            'Einlass nur mit dem Ticket von Hi.Events. Dieses Mail zeigt nur euren Sitzplatz.',
        );
        $contact = config('seating.contact_email');
        $changes = $contact
            ? $this->order->say("Für Änderungen schreib uns an {$contact}.", "Für Änderungen schreibt uns an {$contact}.")
            : $this->order->say('Für Änderungen melde dich bei uns.', 'Für Änderungen meldet euch bei uns.');
        $common = [
            'greeting' => "Hallo {$this->order->first_name}",
            'personalNote' => $this->order->say(
                'Dieser Link ist persönlich. Bitte leite ihn nicht weiter.',
                'Dieser Link ist persönlich. Bitte leitet ihn nicht weiter.',
            ),
        ];

        return $common + match ($this->type) {
            MailType::Link => [
                'lines' => [
                    $this->order->say('Du kannst jetzt deinen Platz wählen.', "Ihr könnt jetzt eure Plätze wählen. Ihr braucht {$needed} Plätze."),
                    $this->order->say("Du kannst deine Wahl bis {$deadline} ändern.", "Ihr könnt eure Wahl bis {$deadline} ändern."),
                ],
                'closing' => [$this->order->say('Wie voll es schon ist, siehst du hier: ', 'Wie voll es schon ist, seht ihr hier: ').route('plan')],
                'buttonLabel' => $this->order->say('Platz wählen', 'Plätze wählen'),
                'buttonUrl' => $selection,
            ],
            MailType::Confirmation => [
                'lines' => [$this->order->say('Das ist dein aktueller Platz:', 'Das sind eure aktuellen Plätze:')],
                'closing' => [
                    $canChange
                        ? $this->order->say("Du kannst ihn bis {$deadline} ändern.", "Ihr könnt sie bis {$deadline} ändern.")
                        : $changes,
                    $entry,
                ],
                'buttonLabel' => $canChange ? $this->order->say('Platz ändern', 'Plätze ändern') : 'Sitzplatz-Pass ansehen',
                'buttonUrl' => $canChange ? $selection : $pass,
            ],
            MailType::Reminder => [
                'lines' => [
                    $this->order->say('Du hast deinen Platz noch nicht gewählt.', "Ihr habt noch nicht alle Plätze gewählt. Ihr braucht {$needed} Plätze."),
                    $this->order->say("Wähle ihn bis {$deadline}. Sonst teilen wir dir automatisch einen Platz zu.", "Wählt sie bis {$deadline}. Sonst teilen wir euch automatisch Plätze zu."),
                ],
                'closing' => [],
                'buttonLabel' => $this->order->say('Platz wählen', 'Plätze wählen'),
                'buttonUrl' => $selection,
            ],
            MailType::AutoAssigned => [
                'lines' => [$this->order->say('Wir haben dir diesen Platz zugeteilt:', 'Wir haben euch diese Plätze zugeteilt:')],
                'closing' => [$changes, $entry],
                'buttonLabel' => 'Sitzplatz-Pass ansehen',
                'buttonUrl' => $pass,
            ],
            MailType::Final => [
                'lines' => array_values(array_filter([
                    $this->eventLine('seating.event_date', $this->order->say('Wir freuen uns auf dich am %s.', 'Wir freuen uns auf euch am %s.')),
                    $this->eventLine('seating.doors_at', 'Türöffnung: %s.'),
                    $this->order->say('Das ist dein Platz:', 'Das sind eure Plätze:'),
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
            ->map(fn (Seat $seat): string => $layout->seatLabel($seat))
            ->all();
    }
}
