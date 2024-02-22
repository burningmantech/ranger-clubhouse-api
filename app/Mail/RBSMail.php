<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class RBSMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public ?Carbon $expiresAt;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public $subject, public $rbsMessage, public $alert, $expiresAt)
    {
        parent::__construct();
        $this->expiresAt = $expiresAt ? (new Carbon($expiresAt)) : null;
    }

    /**
     * Build the message.
     *
     * @return $this
     */

    public function build(): static
    {
        return $this->subject($this->subject)->view('emails.rbs-mail');
    }
}
