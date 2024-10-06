<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class PhotoPendingMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;


    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public $pendingPhotos)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        $count = count($this->pendingPhotos);
        $envelope = $this->fromVC('[Clubhouse] ' . $count . ' ' . Str::plural('photo', $count) . ' queued for review.');
        $envelope->to($this->buildAddresses(setting('PhotoPendingNotifyEmail')));
        return $envelope;
    }

    public function content(): Content
    {
        return new Content(view: 'emails.photo-pending');
    }
}
