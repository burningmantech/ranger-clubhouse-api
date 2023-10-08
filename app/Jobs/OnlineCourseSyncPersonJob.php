<?php

namespace App\Jobs;

use App\Exceptions\MoodleDownForMaintenanceException;
use App\Lib\Moodle;
use App\Models\ActionLog;
use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OnlineCourseSyncPersonJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Person $person)
    {
    }

    /**
     * Sync the user's info with the Moodle account.
     *
     * @throws MoodleDownForMaintenanceException
     */

    public function handle(): void
    {
        $lms = new Moodle();
        $lms->syncPersonInfo($this->person);
        ActionLog::record($this->person, 'lms-sync-user', 'person update');
    }

    /**
     * Only one job instance should be queued up.
     *
     * @return string
     */

    public function uniqueId(): string
    {
        return $this->person->id;
    }
}
