<?php

namespace App\Mail;

use App\Models\ProspectiveApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProspectiveApplicantMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public string $subjectLine;
    public string $viewResource;

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
        $envelope = $this->fromVC('Hey '.$this->application->first_name . ', '.$this->subjectLine);
        $envelope->to([new Address($this->application->email, $this->application->first_name . ' ' . $this->application->last_name)]);
        return $envelope;
    }

    public function content() : Content
    {
        return new Content(view: $this->viewResource);
    }
}