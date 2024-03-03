<?php

namespace App\Console\Commands;

use App\Lib\ClubhouseCache;
use App\Lib\GroundHogDay;
use Illuminate\Console\Command;

class ClubhouseReloadTrainingDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:reload-training-db';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $ghdVar = GroundHogDay::ENVIRONMENT_VAR;
        $groundHogDay = env($ghdVar);
        if (empty($groundHogDay)) {
            $this->error("$ghdVar environment variable not set");
            return true;
        }

        $dumpFile = GroundHogDay::trainingDatabaseDumpName($groundHogDay);
        if (!file_exists($dumpFile)) {
            $this->error("Ground Hog Day database dump file [$dumpFile] does not exist");
            return true;
        }

        $user = config('database.connections.mysql.username');
        $pwd = config('database.connections.mysql.password');
        $db = config('database.connections.mysql.database');
        $host = config('database.connections.mysql.host');
        putenv("MYSQL_PWD=$pwd");

        $this->info("Reloading database");
        if (shell_exec("gunzip < $dumpFile | mysql -h $host -u $user $db")) {
            $this->error("Failed to reload database from $dumpFile.");
            return 1;
        }

        ClubhouseCache::flush();
        $this->info("Training database has been successfully reloaded. The cycle of time begins again.");
        return 0;
    }
}
