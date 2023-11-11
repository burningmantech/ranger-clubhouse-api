<?php

namespace App\Jobs;

use App\Lib\RBS;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RBSTransmitClubhouseMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(public $alert,
                                public $broadcastId,
                                public $userId,
                                public $people,
                                public $from,
                                public $subject,
                                public $message,
                                public $expiresAt)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        RBS::broadcastClubhouse($this->alert,
            $this->broadcastId,
            $this->userId,
            $this->people,
            $this->from,
            $this->subject,
            $this->message,
            $this->expiresAt
        );
    }
}
