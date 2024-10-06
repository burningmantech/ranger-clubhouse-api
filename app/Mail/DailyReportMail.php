<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyReportMail extends ClubhouseMailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(
        public $failedBroadcasts,
        public $errorLogs,
        public $roleLogs,
        public $statusLogs,
        public $settings,
        public $settingLogs,
        public $emailIssues,
        public $dashboardPeriod,
        public $failedJobs,
        public $unknownPhones
    )
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        $envelope = $this->fromDoNotReply('[Clubhouse] Daily Report ' . date('Y-m-d'));
        $envelope->to($this->buildAddresses(setting('DailyReportEmail')));
        return $envelope;
    }

    public function content(): Content
    {
        return new Content(view: 'emails.daily-report');
    }
}
