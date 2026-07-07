<?php

namespace App\Console\Commands;

use App\Lib\RedactDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClubhouseRedactDatabaseCommand extends Command
{
    const string TEMP_DATABASE = "rangers_redacted_temp";

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:redact-db
                    {--dumpfile= : filename to dump the groundhog day database into. Default is rangers-redacted-YYYY-MM-DD.sql}
                    {--tempdb=rangers_redacted_temp : temporary database name}
                    {--super-redact : clear tickets, provisions, bmids, and photos. Set all passwords to abcdef. }
                    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a redacted database using current';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $redactedName = $this->option('tempdb') ?? self::TEMP_DATABASE;

        $user = config('database.connections.mysql.username');
        $pwd = config('database.connections.mysql.password');
        $db = config('database.connections.mysql.database');
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');

        if ($redactedName === $db) {
            $this->error("Temp database [$redactedName] cannot be the same as the source database [$db]");
            return self::FAILURE;
        }

        $this->info("Creating redacted database from $db");

        // Create the groundhog day database
        DB::statement("DROP DATABASE IF EXISTS $redactedName");
        DB::statement("CREATE DATABASE $redactedName");

        putenv("MYSQL_PWD=$pwd");

        $this->info("Cloning $db to $redactedName");
        $cloneDump = "redact-clone-dump.sql";

        exec(sprintf(
            'mysqldump --host=%s --port=%s -u %s %s > %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($db),
            escapeshellarg($cloneDump)
        ), $out, $exitCode);
        if ($exitCode !== 0) {
            $this->error("Cannot clone database");
            return self::FAILURE;
        }

        exec(sprintf(
            'mysql --host=%s --port=%s -u %s %s < %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($redactedName),
            escapeshellarg($cloneDump)
        ), $out, $exitCode);
        unlink($cloneDump);
        if ($exitCode !== 0) {
            $this->error("Cannot clone database");
            return self::FAILURE;
        }

        // Switch databases
        config([ 'database.connections.mysql.database' => $redactedName ]);
        DB::purge('mysql');

        RedactDatabase::execute(current_year(), $this->option('super-redact') ?? false);

        $this->info("Creating mysql redacted dump");
        $dump = $this->option('dumpfile') ?? "rangers-redacted-".date('Y-m-d').".sql";

        exec(sprintf(
            'mysqldump --host=%s --port=%s -u %s %s > %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($redactedName),
            escapeshellarg($dump)
        ), $out, $exitCode);
        if ($exitCode !== 0) {
            $this->info("Failed to dump database - $redactedName has not been deleted.");
            return self::FAILURE;
        }

        DB::statement("DROP DATABASE IF EXISTS $redactedName");
        $this->info("** Done! Database has been successfully created and dumped to $dump");

        return self::SUCCESS;
    }
}
