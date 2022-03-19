<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OnlineTrainingCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $person;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public function __construct($person)
    {
        $this->person = $person;
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
        ])->subject('Ranger Online Training Course Completed')
            ->view('emails.online-training-completed');
    }
}
