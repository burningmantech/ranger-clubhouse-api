<?php

namespace App\Mail;

use App\Models\Person;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AutomaticActiveConversionMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public Carbon $timeOfConversion;

    /**
     * Create a new message instance.
     */
    public function __construct(public Person $person, public string $oldStatus, public string $positionTitle, public string $workerCallsign)
    {
        $this->timeOfConversion = now();
        parent::__construct();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $envelope = $this->fromVC("[Clubhouse] {$this->person->callsign} automatic conversion to active");
        $envelope->to($this->buildAddresses(setting('VCEmail')));
        return $envelope;
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
