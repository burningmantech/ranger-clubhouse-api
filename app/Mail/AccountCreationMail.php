<?php

/*
 * Administrative email - let someone know a new registration failed or succeeded.
 */

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class AccountCreationMail extends Mailable
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
        $this->person  = $person;
        $this->intent = $intent;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('[clubhouse notification] account creation '.$this->status)->view('emails.account-creation');
    }
}
