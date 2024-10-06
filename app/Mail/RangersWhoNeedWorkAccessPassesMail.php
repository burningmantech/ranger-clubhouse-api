<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RangersWhoNeedWorkAccessPassesMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    public function __construct(public $people, public $startYear)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        $envelope = $this->fromVC('[Clubhouse] Ranger SAP Candidates ' . date('Y-m-d'));
        $envelope->to($this->buildAddresses(setting('TAS_SAP_Report_Email')));
        return $envelope;
    }

    public function content(): Content
    {
        return new Content(view: 'emails.rangers-who-need-waps');
    }
}
