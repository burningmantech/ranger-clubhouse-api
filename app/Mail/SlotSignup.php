<?php

/*
 * Send a slot/shift sign up message
 */


namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SlotSignup extends Mailable
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
