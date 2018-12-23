<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class TrainingSessionFullMail extends Mailable
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
