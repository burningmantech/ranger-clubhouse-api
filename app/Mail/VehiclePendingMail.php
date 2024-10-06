<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class VehiclePendingMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public $pending)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        $count = count($this->pending);
        $envelope =  $this->fromDoNotReply('[Clubhouse] ' . $count . ' ' . Str::plural('vehicle', $count) . ' pending review');
        $envelope->to($this->buildAddresses(setting('VehiclePendingEmail')));
        return $envelope;
    }

    public function content(): Content
    {
        return new Content(view: 'emails.vehicle-pending');
    }
}
