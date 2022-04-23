<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class PhotoApprovedMail extends ClubhouseMailable
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
            ->subject('Ranger Clubhouse photo submission APPROVED.')
            ->view('emails.photo-approved');
    }
}
