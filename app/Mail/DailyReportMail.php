<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class DailyReportMail extends Mailable
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
        public $dashboardPeriod)
    {
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('[Clubhouse] Daily Report '.date('Y-m-d'))->view('emails.daily-report');
    }
}
