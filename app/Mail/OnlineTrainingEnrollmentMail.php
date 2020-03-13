<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

use Carbon\Carbon;

class OnlineTrainingEnrollmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public $person;
    public $courseType;
    public $password;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($person, $courseType, $password)
    {
        $this->person = $person;
        $this->courseType = $courseType;
        $this->password = $password;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from([
            'address' => 'ranger-trainingacademy-list@burningman.org',
            'name' => 'The Ranger Training Academy'
        ])->subject('Enrolled in Part 1 of Ranger Training (online)')
            ->view('emails.online-training-enrollment');
    }
}
