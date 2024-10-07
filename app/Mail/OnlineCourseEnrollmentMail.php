<?php

namespace App\Mail;

use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnlineCourseEnrollmentMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public ?string $otUrl;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public Person $person, public $courseType, public $password)
    {
        parent::__construct();
        $this->otUrl = setting('OnlineCourseSiteUrl');
    }

    public function envelope(): Envelope
    {
        $envelope = $this->fromTrainingAcademy('[Clubhouse] Enrolled in The Ranger Online Course');
        $envelope->to([new Address($this->person->email)]);
        return $envelope;
    }

    public function content(): Content
    {
        return new Content(view: 'emails.online-course-enrollment');
    }
}
