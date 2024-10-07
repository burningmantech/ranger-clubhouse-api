<?php

/*
 * Send a training session sign up message
 */


namespace App\Mail;

use App\Models\Person;
use App\Models\Slot;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrainingSignupMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param Slot $slot
     * @param Person $person
     */

    public function __construct(public Slot $slot, public Person $person)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        $envelope = $this->fromDoNotReply('[Clubhouse] Ranger Training Signup');
        $envelope->to([new Address($this->person->email)]);
        return $envelope;
    }

    public function content(): Content
    {
        return new Content(view: 'emails.training-signup');
    }
}
