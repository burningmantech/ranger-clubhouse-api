<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OnlineTrainingEnrollmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otUrl;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public $person, public $courseType, public $password)
    {
        $this->otUrl = setting('OnlineTrainingUrl');
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from([
            'address' => setting('TrainingAcademyEmail'),
            'name' => 'The Ranger Training Academy'
        ])->subject('Enrolled In The Ranger Online Course')
            ->view('emails.online-training-enrollment');
    }
}
