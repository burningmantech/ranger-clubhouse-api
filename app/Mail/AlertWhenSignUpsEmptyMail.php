<?php

namespace App\Mail;

use App\Models\Position;
use App\Models\Slot;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlertWhenSignUpsEmptyMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public function __construct(public Position $position, public Slot $slot, public $traineeSignups)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        $envelope= $this->fromDoNotReply("[Clubhouse] A {$this->position->title} shift has become empty");
        $envelope->to($this->buildAddresses($this->position->contact_email));
        return $envelope;
    }

    public function content(): Content
    {
        return new Content(view: 'emails.alert-when-sign-ups-empty');
    }
}
