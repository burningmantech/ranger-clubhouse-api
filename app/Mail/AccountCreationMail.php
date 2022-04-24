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

    public $status;
    public $details;
    public $person;
    public $intent;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public function __construct($status, $details, $person, $intent)
    {
        $this->status = $status;
        $this->details = $details;
        $this->person = $person;
        $this->intent = $intent;

        parent::__construct();
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            ->from(setting('DoNotReplyEmail'))
            ->subject('[clubhouse notification] account creation ' . $this->status)
            ->view('emails.account-creation');
    }
}
