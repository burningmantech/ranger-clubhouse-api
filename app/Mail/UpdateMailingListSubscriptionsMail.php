<?php

namespace App\Mail;

use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UpdateMailingListSubscriptionsMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public Person $person,
                                public Person $user,
                                public string $oldEmail,
                                public string $additionalLists,
                                public        $teams)
    {
        parent::__construct();

    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('rangers@burningman.org'),
            to: $this->buildAddresses(setting('MailingListUpdateRequestEmail')),
            subject: "[Clubhouse] Update mailing list subscriptions for {$this->person->callsign}"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.update-mailing-list-subscriptions');
    }
}
