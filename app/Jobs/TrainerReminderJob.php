<?php

namespace App\Jobs;

use App\Lib\TrainerReminder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TrainerReminderJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        TrainerReminder::execute();
    }
}
