<?php

namespace App\Console\Commands;

use App\Lib\GroundHogDay;
use App\Lib\RedactDatabase;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ClubhouseCreateTrainingDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:create-training-db';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a training db from production (config.mysql_clone_from). Left in place, and dumped to file.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() : int
    {
        $ghdVar = GroundHogDay::ENVIRONMENT_VAR;
        $groundHogDay = env($ghdVar);
        if (empty($groundHogDay)) {
            $this->error("$ghdVar environment variable not set");
            return true;
        }

        $dumpFile = GroundHogDay::trainingDatabaseDumpName($groundHogDay);
        $groundHogDay = Carbon::parse($groundHogDay);
        $year = $groundHogDay->year;

        $cloneUser = config('database.connections.mysql_clone_from.username');
        $clonePwd = config('database.connections.mysql_clone_from.password');
        $cloneDb = config('database.connections.mysql_clone_from.database');
        $cloneHost = config('database.connections.mysql_clone_from.host');
        putenv("MYSQL_PWD=$clonePwd");
        $cloneSql = "clone.sql.gz";

        $this->info("Dumping from $cloneDb");
        if (shell_exec("mysqldump -h $cloneHost -u $cloneUser --add-drop-table -e --ignore-table=$cloneDb.log -q $cloneDb | gzip > $cloneSql")) {
            $this->error("Cannot dump database host=[$cloneHost] user=[$cloneUser] db=[$cloneDb]");
            return 1;
        }

        // Load database up here.
        $user = config('database.connections.mysql.username');
        $pwd = config('database.connections.mysql.password');
        $db = config('database.connections.mysql.database');
        $host = config('database.connections.mysql.host');
        putenv("MYSQL_PWD=$pwd");
        $this->info("Loading into $db");
        if (shell_exec("gunzip < clone.sql.gz | mysql -h $host -u $user $db")) {
            $this->error("Could not load database from production server");
            unlink($cloneSql);
            return 1;
        }

        $this->info('Redacting and Ground Hog Day-zing the database');
        RedactDatabase::execute($year);
        GroundHogDay::build($groundHogDay);

        unlink($cloneSql);
        $this->info("Dumping training database to $dumpFile");
        // password set above
        if (shell_exec("mysqldump -h $host -u $user $db | gzip > $dumpFile")) {
            $this->error("Failed to dump training database");
            unlink($dumpFile);
            return 1;
        }

        $this->info("Training database created. Database $db setup.");
        return 0;
    }
}
