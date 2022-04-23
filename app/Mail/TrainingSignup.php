<?php

/*
 * Send a training session sign up message
 */


namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class TrainingSignup extends ClubhouseMailable
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
        return $this->from(setting('DoNotReplyEmail'))
            ->subject('Ranger Training Signup')
            ->view('emails.training-signup');
    }
}
