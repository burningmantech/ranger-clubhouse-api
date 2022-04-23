<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class RangersWhoNeedWorkAccessPassesMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public function __construct(public $people)
    {
        parent::__construct();
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('[Clubhouse] Rangers Who Need WAPs ' . date('Y-m-d'))->view('emails.rangers-who-need-waps');
    }
}
