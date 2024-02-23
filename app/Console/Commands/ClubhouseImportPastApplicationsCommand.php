<?php

namespace App\Console\Commands;

use App\Lib\ProspectiveApplicationImport;
use Illuminate\Console\Command;

class ClubhouseImportPastApplicationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:import-past-applications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import past Salesforce Ranger Applications (used to seed the database)';

    /**
     * Execute the console command.
     */

    public function handle(): void
    {
        $import = new ProspectiveApplicationImport();
        if (!$import->auth()) {
            $this->error("Salesforce authentication failure");
            exit(-1);
        }

        for ($year = 2015; $year < 2024; $year++) {
            $this->info("Importing year $year");
            $import->importForYear($year, true);
        }
    }
}
