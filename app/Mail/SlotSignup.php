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

    /**
     * Create a new message instance.
     *
     * @param
     * @return void
     */
    public function __construct(public $slot)
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
        return $this
            ->from(setting('DoNotReplyEmail'))
            ->subject('Shift Signup')
            ->view('emails.slot-signup');
    }
}
