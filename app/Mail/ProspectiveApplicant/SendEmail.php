<?php

namespace App\Mail\ProspectiveApplicant;

use App\Mail\ClubhouseMailable;
use App\Models\ProspectiveApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendEmail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public ProspectiveApplication $application,
                                public                        $emailSubject,
                                public                        $emailMessage)
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
            subject: $this->emailSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.prospective-application.send-email'
        );
    }
}
