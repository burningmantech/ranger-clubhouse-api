<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models\Person;

class ContactMail extends Mailable
{
    use Queueable, SerializesModels;

    public $senderPerson;
    public $recipientPerson;
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
        $this->recipientPerson = $recipient;
        $this->messageSubject = $subject;
        $this->contactMessage = $message;
    }

    /**
     * Build the message.
     *
     * @return $this
     */

    public function build()
    {
        return $this->from('do-not-reply@burningman.org')
                ->subject($this->messageSubject)
                ->view('emails.contact');
    }
}
