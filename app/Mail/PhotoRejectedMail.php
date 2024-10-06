<?php

namespace App\Mail;

use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhotoRejectedMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public Person $person, public $rejectReasons, public $rejectMessage)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        $envelope = $this->fromVC('Ranger Clubhouse photo submission REJECTED.');
        $envelope->to(new Address($this->person->email));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.photo-rejected');
    }
}
