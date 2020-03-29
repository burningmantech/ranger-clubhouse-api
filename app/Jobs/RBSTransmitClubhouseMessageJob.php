<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Lib\RBS;

class RBSTransmitClubhouseMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $alert;
    public $broadcastId;
    public $userId;
    public $people;
    public $from;
    public $subject;
    public $message;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($alert, $broadcastId, $userId, $people, $from, $subject, $message)
    {
        $this->alert = $alert;
        $this->broadcastId = $broadcastId;
        $this->userId = $userId;
        $this->people = $people;
        $this->from = $from;
        $this->subject = $subject;
        $this->message = $message;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        RBS::broadcastClubhouse($this->alert, $this->broadcastId,
            $this->userId, $this->people, $this->from, $this->subject, $this->message);
    }
}
