<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models\Person;

class ContactMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public $senderPerson;
    public $person;
    public $messageSubject;
    public $contactMessage;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Person $sender, Person $recipient, $subject, $message)
    {
        $this->senderPerson = $sender;
        $this->person = $recipient;
        $this->messageSubject = $subject;
        $this->contactMessage = $message;

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
                ->subject($this->messageSubject)
                ->view('emails.contact');
    }
}
