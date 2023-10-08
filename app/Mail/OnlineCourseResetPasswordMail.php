<?php

namespace App\Mail;

use App\Models\Person;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OnlineCourseResetPasswordMail extends ClubhouseMailable
{
    public ?string $ocUrl;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public function __construct(public Person $person, public string $password)
    {
        $this->otUrl = setting('OnlineCourseSiteUrl');
        parent::__construct();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(setting('DoNotReplyEmail'), 'The Ranger Training Academy'),
            to: $this->person->email,
            subject: 'Ranger Online Course Password Reset',
        );
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

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
