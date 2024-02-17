<?php

namespace App\Mail\ProspectiveApplicant;

use App\Mail\ClubhouseMailable;
use App\Models\ProspectiveApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RRNCheckMail extends ClubhouseMailable
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
            to: [new Address(setting('RRNEmail'), 'Regional Ranger Network Team')],
            subject: 'A-' . ($this->application->id) . ' : Request for Regional Ranger Experience Validation',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.prospective-application.rrn-check'
        );
    }
}
