<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

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

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this->subject('[Clubhouse] '.count($this->pendingPhotos).' photo(s) queued for review')
                ->view('emails.photo-pending');
    }
}
