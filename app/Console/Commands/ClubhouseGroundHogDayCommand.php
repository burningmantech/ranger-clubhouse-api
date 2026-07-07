<?php

namespace App\Console\Commands;

use App\Lib\GroundHogDay;
use App\Lib\RedactDatabase;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClubhouseGroundHogDayCommand extends Command
{
    const GROUNDHOG_DATABASE = "rangers_ghd";

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:groundhog-day';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clone current database, convert into a groundhog day database, and dump into a file. The default is last year\'s Burn Night.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $date = Carbon::parse("first Monday of September ".(date('Y')-1))->subDays(2)->format('Y-m-d');
        $this->signature = 'clubhouse:groundhog-day
                    {--dumpfile= : filename to dump the groundhog day database into. Default is rangers-ghd-YYYY-MM-DD.sql}
                    {--tempdb=ranger_ghd : temporary database name}
                    {--day=' . $date . ' 18:00:00 : ground hog day date/time}
                    {--no-redact : do not react the database}
                    ';
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return bool
     */

    public function handle(): bool
    {
        $groundHogDay = Carbon::parse($this->option('day') ?? ((date('Y') - 1) . '-08-30 18:00:00'));
        $ghdname = $this->option('tempdb') ?? self::GROUNDHOG_DATABASE;
        $noRedact = $this->option('no-redact') ?? false;

        $year = $groundHogDay->year;
        $dumpDate = $groundHogDay->format('Y-m-d');

        $user = config('database.connections.mysql.username');
        $pwd = config('database.connections.mysql.password');
        $db = config('database.connections.mysql.database');

        if ($ghdname === $db) {
            $this->error("--tempdb ($ghdname) cannot be the same as the source database ($db).");
            return self::FAILURE;
        }

        // The current database is the Ground Hog Day database.
        // Create the groundhog day database
        DB::statement("DROP DATABASE IF EXISTS $ghdname");
        DB::statement("CREATE DATABASE $ghdname");
        putenv("MYSQL_PWD=$pwd");
        $this->info("Creating groundhog day database from $db for day $groundHogDay");
        $this->info("Cloning $db to $ghdname");

        exec("mysqldump --ignore-table=" . escapeshellarg($db) . ".log --ignore-table=" . escapeshellarg($db) . ".mail_log --ignore-table=" . escapeshellarg($db) . ".action_logs -u " . escapeshellarg($user) . " " . escapeshellarg($db) . " > dump-tmp.sql", $out, $exitCode);
        if ($exitCode !== 0) {
            $this->error("Cannot dump the ignore database.");
            return true;
        }

        exec("mysqldump --no-data  -u " . escapeshellarg($user) . " " . escapeshellarg($db) . " log mail_log >> dump-tmp.sql", $out, $exitCode);
        if ($exitCode !== 0) {
            $this->error("Cannot dump the database structure for selected tables.");
            return true;
        }

        exec("mysqldump --where=\"event in ('person-slot-add', 'person-slot-remove')\" -u " . escapeshellarg($user) . " " . escapeshellarg($db) . " action_logs  >> dump-tmp.sql", $out, $exitCode);
        if ($exitCode !== 0) {
            $this->error("Cannot dump the database structure for selected tables.");
            return true;
        }

        exec("mysql -u " . escapeshellarg($user) . " " . escapeshellarg($ghdname) . " < dump-tmp.sql", $out, $exitCode);
        if ($exitCode !== 0) {
            $this->error("Failed to load ghd database");
            return true;
        }

        unlink("dump-tmp.sql");

        // Switch databases
        config(['database.connections.mysql.database' => $ghdname]);

        // Connect to the temporary database
        DB::purge('mysql');

        if ($noRedact) {
            $this->info('NOT redacting the database.');
        } else {
            $this->info('Redacting the database');
            RedactDatabase::execute($year);
        }

        GroundHogDay::build($groundHogDay);
        $this->info("Creating mysql dump of groundhog database");
        $dump = $this->option('dumpfile') ?? "rangers-ghd-{$dumpDate}.sql";

        exec("mysqldump -u " . escapeshellarg($user) . " " . escapeshellarg($ghdname) . " > " . escapeshellarg($dump), $out, $exitCode);
        if ($exitCode !== 0) {
            $this->error("Failed to dump database - $ghdname has not been deleted.");
            return true;
        }
        DB::statement("DROP DATABASE IF EXISTS $ghdname");
        $this->info("** Done! Database has been successfully created and dumped to $dump");

        return false;
    }
}
