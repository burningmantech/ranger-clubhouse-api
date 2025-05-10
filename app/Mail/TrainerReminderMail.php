<?php

namespace App\Mail;

use App\Models\TrainingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrainerReminderMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public function __construct(public TrainingSession $slot, public array $trainerEmails)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        $contact = $this->slot->position->contact_email;
        $envelope = new Envelope(
            subject: '[Clubhouse] Record Training Results Reminder',
        );
        if (str_contains($contact, ',')) {
            // Can't handle multiple froms .
            $envelope->from(new Address(setting('DoNotReplyEmail')));
        } else {
            $envelope->from(new Address($contact));
        }
        $envelope->to($this->buildAddresses($contact));
        if (!empty($this->trainerEmails)) {
            $envelope->to(array_map(fn($email) => new Address($email), $this->trainerEmails));
        }
        return $envelope;
    }

    public function content(): Content
    {
        return new Content(view: 'emails.trainer-reminder');
    }
}
