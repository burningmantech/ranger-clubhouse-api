<?php

/*
 * Send a training session sign up message
 */


namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class TrainingSignup extends Mailable
{
    use Queueable, SerializesModels;

    public $slot;
    public $vcEmail;

     /**
      * Create a new message instance.
      *
      * @param
      * @return void
      */
     public function __construct($slot, $vcEmail)
     {
         $this->slot = $slot;
         $this->vcEmail = $vcEmail;
     }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Ranger Training Signup')->view('emails.training-signup');
    }
}
