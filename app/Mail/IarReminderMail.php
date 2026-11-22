<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Preview-safe mail template. Constructing/rendering this class does not send mail. */
final class IarReminderMail extends Mailable
{
    public function __construct(public readonly string $reminderSubject, public readonly string $reminderBody) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->reminderSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.iar-reminder', with: ['bodyText' => $this->reminderBody]);
    }
}
