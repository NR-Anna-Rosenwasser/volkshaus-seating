<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AdminAlertMail extends Mailable
{
    public function __construct(public string $title, public string $alert) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Sitzplatzwahl: {$this->title}");
    }

    public function content(): Content
    {
        return new Content(text: 'mail.admin-alert-text');
    }
}
