<?php

namespace App\Jobs;

use App\Lib\RBS;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RBSTransmitEmailJob implements ShouldQueue
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
        RBS::broadcastEmail($this->alert, $this->broadcastId, $this->userId, $this->people, $this->subject, $this->message, $this->expiresAt);
    }
}
