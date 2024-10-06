<?php

namespace App\Mail\ProspectiveApplicant;

use App\Mail\ClubhouseMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ApprovedReminderMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public Collection $applications)
    {
        parent::__construct();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $envelope =  $this->fromVC('[Clubhouse] Approved application(s) require attention');
        $envelope->to(new Address(setting('VCEmail')));
        return $envelope;
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
