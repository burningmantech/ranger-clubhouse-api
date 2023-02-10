<?php

namespace App\Mail;

use App\Models\Position;
use App\Models\Slot;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class AlertWhenSignUpsEmptyMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public function __construct(public Position $position, public Slot $slot, public $traineeSignups)
    {
        parent::__construct();
    }

    /**
     * Build the message.
     *
     * @return $this
     */

    public function build(): static
    {
        return $this->subject("{$this->position->title} shift has become empty")
            ->view('emails.alert-when-sign-ups-empty');
    }
}
