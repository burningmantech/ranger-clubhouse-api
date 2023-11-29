<?php

namespace App\Mail;

use App\Models\Person;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AutomaticActiveConversionMail extends Mailable
{
    use Queueable, SerializesModels;

    public Carbon $timeOfConversion;

    /**
     * Create a new message instance.
     */
    public function __construct(public Person $person, public string $oldStatus, public string $positionTitle, public string $workerCallsign)
    {
        $this->timeOfConversion = now();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->person->callsign}: Automatic Conversion to Active");
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.automatic-active-conversion',
        );
    }
}
