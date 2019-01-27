<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyVCEmailChangeMail extends Mailable
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
