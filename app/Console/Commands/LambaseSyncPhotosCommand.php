<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Person;
use App\Models\PersonPhoto;
use App\Models\Photo;

class LambaseSyncPhotosCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lambase:syncphotos';

    /**
     * The console command description.
     *
     * @var string
     */

    protected $description = 'Rebuild the photo status cache, and download available mugshots. Used for on playa operations.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     *
     *
     * @return mixed
     */
    public function handle()
    {
        if (!setting('PhotoStoreLocally')) {
            $this->error("PhotoStoreLocally setting is not enabled. Enable the setting and then rerun this command.");
            return;
        }

        // Blow away the cached statuses
        PersonPhoto::truncate();

        $people = Person::whereIn('status', Person::LIVE_STATUSES)
                  ->orWhere('status', Person::NON_RANGER)
                  ->get();

        $this->info("Syncing ".$people->count()." photos");
        foreach ($people as $person) {
            $this->info("Syncing #{$person->id} {$person->callsign}");
            Photo::retrieveInfo($person, true);
        }

        $this->info("finished.");
    }
}
