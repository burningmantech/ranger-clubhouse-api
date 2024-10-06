<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
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

    public function envelope(): Envelope
    {
        return $this->fromDoNotReply($this->subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.rbs-mail');
    }
}
