<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class TrainingSessionFullMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public $slot;
    public $signedUp;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($slot, $signedUp)
    {
        $this->slot = $slot;
        $this->signedUp = $signedUp;
        parent::__construct();
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            ->subject('Training Session Full')
            ->view('emails.training-session-full');
    }
}
