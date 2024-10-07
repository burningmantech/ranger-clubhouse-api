<?php

/*
 * Administrative email - let someone know a new registration failed or succeeded.
 */

namespace App\Mail;

use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountCreationMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public function __construct(public string $status,
                                public        $details,
                                public Person $person,
                                public        $intent)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(setting('DoNotReplyEmail')),
            to: [new Address(setting('AccountCreationEmail'))],
            subject: '[Clubhouse] Account creation ' . $this->person->callsign,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.account-creation');
    }
}
