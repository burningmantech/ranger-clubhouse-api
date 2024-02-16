<?php

namespace App\Mail\ProspectiveApplicant;

use App\Mail\ClubhouseMailable;
use App\Models\ProspectiveApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExperienceConfirmationMail extends ClubhouseMailable
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
            subject: 'Hey ' . $this->application->first_name . ', we have a question regarding your Ranger application.',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.prospective-applicant.experience-confirmation'
        );
    }
}
