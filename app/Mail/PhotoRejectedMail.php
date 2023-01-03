<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class PhotoRejectedMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public $person;
    public $rejectMessage;
    public $rejectReasons;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($person, $rejectReasons, $rejectMessage)
    {
        $this->person = $person;
        $this->rejectMessage = $rejectMessage;
        $this->rejectReasons = $rejectReasons;
        parent::__construct();
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(setting('VCEmail'), 'The Volunteer Coordinators')
            ->subject('Ranger Clubhouse photo submission REJECTED.')
            ->view('emails.photo-rejected');
    }
}
