<?php

namespace App\Mail;

use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UpdateMailingListSubscriptionsMail extends Mailable
{
    use Queueable, SerializesModels;

    public Person $person;
    public Person $user;
    public string $oldEmail;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Person $person, Person $user, string $oldEmail)
    {
        $this->person = $person;
        $this->user = $user;
        $this->oldEmail = $oldEmail;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('do-not-reply@burningman.org')
            ->subject("Update mailing list subscriptions for {$this->person->callsign}")
            ->view('emails.update-mailing-list-subscriptions');
    }
}
