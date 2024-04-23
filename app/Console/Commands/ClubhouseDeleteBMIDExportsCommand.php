<?php

namespace App\Console\Commands;

use App\Models\BmidExport;
use Illuminate\Console\Command;

class ClubhouseDeleteBMIDExportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:delete-bmid-exports
                            {--year= : the year to delete}
                            {--post-event : delete all exports}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete the BMID exports for a given year or all for post-event';

    /**
     * Execute the console command.
     */

    public function handle(): void
    {
        if ($this->option('post-event')) {
            BmidExport::deleteAllForPostEvent('automated post-event cleanup');
        } else {
            $year = $this->option('year') ?? current_year();
            BmidExport::deleteAllForYear($year);
        }
    }
}
