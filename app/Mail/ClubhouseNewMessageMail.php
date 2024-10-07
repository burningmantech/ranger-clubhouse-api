<?php

namespace App\Mail;

use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClubhouseNewMessageMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public function __construct(public Person $person,
                                public string $clubhouseFrom,
                                public string $clubhouseSubject,
                                public string $clubhouseMessage)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(setting('DoNotReplyEmail'), 'The Black Rock Rangers'),
            to: [new Address($this->person->email)],
            subject: "[Clubhouse Message] {$this->clubhouseSubject}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.clubhouse-new-message',
        );
    }
}
