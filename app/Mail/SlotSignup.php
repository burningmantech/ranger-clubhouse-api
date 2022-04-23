<?php

/*
 * Send a slot/shift sign up message
 */


namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class SlotSignup extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public $slot;
    public $fromEmail;

    /**
     * Create a new message instance.
     *
     * @param
     * @return void
     */
    public function __construct($slot, $fromEmail)
    {
        $this->slot = $slot;
        $this->fromEmail = $fromEmail;
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
            ->from($this->fromEmail)
            ->subject('Shift Signup')
            ->view('emails.slot-signup');
    }
}
