<?php

/*
 * Send a welcome email to a new account
 */

namespace App\Mail;

use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public string $inviteUrl;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public function __construct(public Person $person, public string $inviteToken)
    {
        $host = request()->getSchemeAndHttpHost();
        $this->inviteUrl = "{$host}/login?token={$inviteToken}&welcome=1";
        parent::__construct();
    }

    public function envelope() : Envelope
    {
        $envelope = $this->fromVC('Welcome to the Black Rock Rangers Secret Clubhouse!');
        $envelope->to([new Address($this->person->email)]);
        return $envelope;
    }

    public function content() : Content
    {
        return new Content(view: 'emails.welcome');
    }
}
