<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PhotoPendingMail extends Mailable
{
    use Queueable, SerializesModels;

    public $pendingPhotos;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($pendingPhotos)
    {
        $this->pendingPhotos = $pendingPhotos;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('[Clubhouse] '.count($this->pendingPhotos).' photo(s) queued for review')
                ->view('emails.photo-pending');
    }
}
