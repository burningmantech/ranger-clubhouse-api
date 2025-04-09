<?php

namespace App\Jobs;

use App\Lib\AwardManagement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AwardsRebuildJob implements ShouldQueue
{
    use Queueable;

    /**
     * Go forth and rebuild
     */
    public function handle(): void
    {
        AwardManagement::rebuildAll();
    }
}
