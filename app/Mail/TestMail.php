<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return $this->fromDoNotReply('Test Email');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.test-mail');
    }
}
