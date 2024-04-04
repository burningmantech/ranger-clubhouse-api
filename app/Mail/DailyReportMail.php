<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
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

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this->subject('Clubhouse Daily Report ' . date('Y-m-d'))->view('emails.daily-report');
    }
}
