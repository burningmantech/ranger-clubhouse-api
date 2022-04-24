<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class NotifyVCEmailChangeMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public $person;
    public $oldEmail;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($person, $oldEmail)
    {
        $this->person = $person;
        $this->oldEmail = $oldEmail;
        parent::__construct();
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('[clubhouse notification] Prospective email change')->view('emails.notify-vc-email-change');
    }
}
