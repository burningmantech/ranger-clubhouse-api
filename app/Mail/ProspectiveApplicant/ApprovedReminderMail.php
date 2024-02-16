<?php

namespace App\Mail\ProspectiveApplicant;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ApprovedReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public Collection $applications)
    {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(setting('DoNotReplyEmail'), 'The Clubhouse Bot'),
            to: [new Address(setting('VCEmail'), 'The Volunteer Coordinators')],
            subject: '[Clubhouse] Approved Applications',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.prospective-application.approved-reminder',
        );
    }
}
