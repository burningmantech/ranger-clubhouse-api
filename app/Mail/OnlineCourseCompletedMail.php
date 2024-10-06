<?php

namespace App\Mail;

use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnlineCourseCompletedMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public function __construct(public Person $person)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        $envelope = $this->fromTrainingAcademy('[Clubhouse] Ranger Online Course Completed');
        $envelope->to(new Address($this->person->email));
        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.online-course-completed',
        );
    }
}
