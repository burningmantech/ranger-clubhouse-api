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

    public function handle(): int
    {
        $import = new ProspectiveApplicationImport();
        if (!$import->auth()) {
            $this->error("Salesforce authentication failure");
            return self::FAILURE;
        }

        $hadError = false;
        for ($year = 2015; $year < 2024; $year++) {
            $this->info("Importing year $year");
            $import->importForYear($year, true);
            if (!empty($import->errorMessage)) {
                $this->error("Import for year $year failed: {$import->errorMessage}");
                $hadError = true;
            }
        }

        if (!empty($import->queryFailures)) {
            $this->error(count($import->queryFailures) . ' application(s) failed to import due to query failures:');
            foreach ($import->queryFailures as $failure) {
                $this->error("- {$failure->salesforce_name}: {$failure->api_error}");
            }
            $hadError = true;
        }

        if (!empty($import->creationFailures)) {
            $this->error(count($import->creationFailures) . ' application(s) failed to be created:');
            foreach ($import->creationFailures as $failure) {
                $this->error("- {$failure->salesforce_name}: " . ($failure->api_error_message ?? $failure->api_error));
            }
            $hadError = true;
        }

        return $hadError ? self::FAILURE : self::SUCCESS;
    }
}
