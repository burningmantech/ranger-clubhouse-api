<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
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
    public function __construct(public $person, public $courseType, public $password)
    {
        parent::__construct();
        $this->otUrl = setting('OnlineCourseSiteUrl');
    }

    /**
     * Build the message.
     *
     * @return $this
     */

    public function build(): static
    {
        return $this->from(setting('DoNotReplyEmail'), 'The Ranger Training Academy')
        ->subject('Enrolled In The Ranger Online Course')
            ->view('emails.online-course-enrollment');
    }
}
