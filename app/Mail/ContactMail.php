<?php

namespace App\Mail;

use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public Person $senderPerson, public Person $person, public $messageSubject, public $contactMessage)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(setting('DoNotReplyEmail'), 'The Black Rock Rangers'),
            to: [new Address($this->person->email, 'Ranger ' . $this->person->callsign)],
            subject: $this->messageSubject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact');
    }
}
