<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models\Person;

class ClubhouseNewMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public $person;
    public $clubhouseFrom;
    public $clubhouseSubject;
    public $clubhouseMessage;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Person $person, $from, $subject, $message)
    {
        $this->person = $person;
        $this->clubhouseFrom = $from;
        $this->clubhouseSubject = $subject;
        $this->clubhouseMessage = $message;
    }

    /**
     * Build the message.
     *
     * @return $this
     */

    public function build()
    {
        return $this->from(setting('DoNotReplyEmail'))
                ->subject('[Rangers] A New Clubhouse Message')
                ->view('emails.clubhouse-new-message');
    }
}
