<?php

namespace App\Mail;

use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MailingListChangesMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param Person $person
     * @param string $reason
     * @param array $results list of ['action', 'group', 'email', 'success', 'status']
     */

    public function __construct(public Person $person,
                                public string $reason,
                                public array  $results)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        $hasFailures = collect($this->results)->contains(fn($r) => !$r['success']);
        return new Envelope(
            from: new Address('rangers@burningman.org'),
            to: $this->buildAddresses(setting('MailingListUpdateRequestEmail')),
            subject: ($hasFailures ? '[Clubhouse] FAILED ' : '[Clubhouse] ')
                . "Mailing list changes for {$this->person->callsign}"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.mailing-list-changes');
    }
}
