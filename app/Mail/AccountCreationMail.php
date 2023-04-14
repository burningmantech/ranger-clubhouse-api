<?php

/*
 * Administrative email - let someone know a new registration failed or succeeded.
 */

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class AccountCreationMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public function __construct(public $status, public $details, public $person, public $intent)
    {
        parent::__construct();
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this
            ->from(setting('DoNotReplyEmail'))
            ->subject('[clubhouse notification] account creation ' . $this->status)
            ->view('emails.account-creation');
    }
}
