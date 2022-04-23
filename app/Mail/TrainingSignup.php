<?php

/*
 * Send a training session sign up message
 */


namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class TrainingSignup extends ClubhouseMailable
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
            ->subject('Ranger Training Signup')
            ->view('emails.training-signup');
    }
}
