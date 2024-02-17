<?php

namespace App\Mail\ProspectiveApplicant;

use App\Mail\ClubhouseMailable;
use App\Models\ProspectiveApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReturningRangerMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public ProspectiveApplication $application,
                                public string                 $status,
                                public ?string                $messageToUser)
    {
        parent::__construct();
    }

    /**
     * Get the message envelope.
     */

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(setting('VCEmail'), 'The Ranger Volunteer Coordinators'),
            to: [new Address($this->application->email, $this->application->first_name . ' ' . $this->application->last_name)],
            subject: 'Hey ' . $this->application->first_name . ', concerning your Ranger Application.',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.prospective-application.returning-ranger',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
