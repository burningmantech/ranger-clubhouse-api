<?php

/*
 * Send a welcome email to a new account
 */

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $person;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public function __construct($person)
    {
        $this->person = $person;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from([ 'address' => 'ranger-vc-list@burningman.org', 'name' => 'The Black Rock Rangers'] )
                ->subject('Welcome to the Black Rock Rangers Secret Clubhouse!')
                ->view('emails.welcome');
    }
}
