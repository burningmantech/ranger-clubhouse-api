<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class VehiclePendingMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public $pending;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($pending)
    {
        $this->pending = $pending;
        parent::__construct();
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $count = count($this->pending);
        return $this->subject('[Clubhouse] ' . $count . ' ' . Str::plural('vehicle', $count) . ' pending review')
            ->view('emails.vehicle-pending');
    }
}
