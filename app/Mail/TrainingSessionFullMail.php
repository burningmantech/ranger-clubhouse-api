<?php

namespace App\Mail;

use App\Models\Slot;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrainingSessionFullMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public Slot $slot, public int $signedUp, public string $notifyEmail)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        $emails = $this->buildAddresses($this->notifyEmail);
        return new Envelope(
            from: $emails[0],
            to: $emails,
            subject: '[Clubhouse] A training session has become full',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.training-session-full');
    }
}
