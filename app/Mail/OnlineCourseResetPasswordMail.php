<?php

namespace App\Mail;

use App\Models\Person;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OnlineCourseResetPasswordMail extends ClubhouseMailable
{
    public ?string $otUrl;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public function __construct(public Person $person, public string $password)
    {
        parent::__construct();
        $this->otUrl = setting('OnlineCourseSiteUrl');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $envelope = $this->fromTrainingAcademy('[Clubhouse] Ranger Online Course Password Reset');
        $envelope->to(new Address($this->person->email));
        return $envelope;
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.online-course-password-reset',
        );
    }
}
