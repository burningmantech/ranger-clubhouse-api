<?php

namespace App\Mail;

use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class UpdateMailingListSubscriptionsMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public Person $person, public Person $user, public string $oldEmail, public string $additionalLists)
    {
        parent::__construct();

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(setting('DoNotReplyEmail'))
            ->subject("Update mailing list subscriptions for {$this->person->callsign}")
            ->view('emails.update-mailing-list-subscriptions');
    }
}
