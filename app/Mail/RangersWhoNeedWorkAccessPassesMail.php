<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RangersWhoNeedWorkAccessPassesMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public $people)
    {
        //
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('[Clubhouse] Rangers Who Need WAPs '.date('Y-m-d'))->view('emails.rangers-who-need-waps');
    }
}
