<?php

namespace App\Mail;

use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public $adminEmail;
    public $greeting;
    public $resetURL;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public Person $person, $token)
    {
        $this->adminEmail = setting('AdminEmail');

        switch ($person->status) {
            case Person::AUDITOR:
                $this->greeting = "Auditor {$person->first_name}";
                break;
            case Person::PROSPECTIVE:
            case Person::ALPHA:
                $this->greeting = "Ranger Applicant {$person->callsign}";
                break;
            case Person::NON_RANGER:
                $this->greeting = "Ranger Volunteer {$person->callsign}";
                break;
            default:
                $this->greeting = "Ranger {$person->callsign}";
                break;
        }

        $host = request()->getSchemeAndHttpHost();
        $this->resetURL = "{$host}/login?token={$token}";
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        $envelope = $this->fromDoNotReply('Ranger Clubhouse password reset');
        $envelope->to([new Address($this->person->email)]);
        return $envelope;
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reset-password');
    }
}
