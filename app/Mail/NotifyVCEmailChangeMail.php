<?php

namespace App\Mail;

use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotifyVCEmailChangeMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public Person $person, public string $oldEmail)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        $envelope = $this->fromVC('[Clubhouse] Applicant email address update');
        $envelope->to(setting('VCEmail'));
        return $envelope;
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notify-vc-email-change');
    }
}
