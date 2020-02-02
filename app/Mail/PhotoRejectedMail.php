<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PhotoRejectedMail extends Mailable
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
     }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from([ 'address' => 'ranger-vc-list@burningman.org', 'name' => 'The Voluneer Coordinators'] )
                ->subject('Ranger Clubhouse photo submission REJECTED.')
                ->view('emails.photo-rejected');
    }
}
