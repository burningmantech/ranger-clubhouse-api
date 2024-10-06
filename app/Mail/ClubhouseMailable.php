<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Auth;

class ClubhouseMailable extends Mailable
{
    public ?int $senderId;
    public ?int $personId;

    public function __construct()
    {
        $this->senderId = Auth::id();
        $this->personId = isset($this->person) ? $this->person->id : null;
    }

    public function fromVC(string $subject): Envelope
    {
        return new Envelope(
            from: new Address(setting('VCEmail'), 'The Ranger Volunteer Coordinators'),
            subject: $subject,
        );
    }

    public function fromDoNotReply(string $subject, string $name = 'The Ranger Clubhouse') : Envelope
    {
        return new Envelope(
            from: new Address(setting('DoNotReplyEmail'), $name),
            subject: $subject,
        );
    }

    public function fromTrainingAcademy(string $subject): Envelope
    {
        return new Envelope(
            from: new Address(setting('TrainingAcademyEmail'), 'The Ranger Training Academy'),
            subject: $subject,
        );
    }

    public function buildAddresses($addresses) : array
    {
        return array_map(fn($email) => new Address($email), explode(',', $addresses));
    }
}
