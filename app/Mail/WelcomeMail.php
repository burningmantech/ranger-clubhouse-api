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
    public $inviteToken;
    public $inviteUrl;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public function __construct($person, $inviteToken)
    {
        $this->person = $person;
        $this->inviteToken = $inviteToken;
        $this->inviteUrl = "https://ranger-clubhouse.burningman.org/client/login?token=$inviteToken&welcome=1";
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from([ 'address' => setting('VCEmail'), 'name' => 'The Black Rock Rangers'] )
                ->subject('Welcome to the Black Rock Rangers Secret Clubhouse!')
                ->view('emails.welcome');
    }
}
