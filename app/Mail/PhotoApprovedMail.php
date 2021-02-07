<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PhotoApprovedMail extends Mailable
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
        return $this->from([ 'address' => setting('VCEmail'), 'name' => 'The Volunteer Coordinators'] )
                ->subject('Ranger Clubhouse photo submission APPROVED.')
                ->view('emails.photo-approved');
    }
}
