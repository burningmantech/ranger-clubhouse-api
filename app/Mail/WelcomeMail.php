<?php

/*
 * Send a welcome email to a new account
 */

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public $inviteUrl;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public function __construct(public $person, public $inviteToken)
    {
        $host = request()->getSchemeAndHttpHost();
        $this->inviteUrl = "{$host}/login?token={$inviteToken}&welcome=1";
        parent::__construct();
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(setting('DoNotReplyEmail'), 'The Black Rock Rangers')
            ->subject('Welcome to the Black Rock Rangers Secret Clubhouse!')
            ->view('emails.welcome');
    }
}
