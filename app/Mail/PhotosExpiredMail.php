<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhotosExpiredMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */

    public function __construct(public $expiredPhotos)
    {
        parent::__construct();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $envelope = $this->fromVC('[Clubhouse] Photo expiration');
        $envelope->to($this->buildAddresses(setting('PhotosExpiredEmail')));
        return $envelope;
    }


    /**
     * Get the message content definition.
     */

    public function content(): Content
    {
        return new Content(
            view: 'emails.photos-expired',
        );
    }
}
