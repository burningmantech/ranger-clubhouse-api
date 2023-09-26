<?php

namespace App\Console\Commands;

use App\Lib\RedactDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClubhouseRedactDatabaseCommand extends Command
{
    const TEMP_DATABASE = "rangers_redacted_temp";

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:redact-db
                    {--dumpfile= : filename to dump the groundhog day database into. Default is rangers-redacted-YYYY-MM-DD.sql}
                    {--tempdb=rangers_redacted_temp : temporary database name}
                    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a redacted database using current';

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
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $redactedName = $this->option('tempdb') ?? self::TEMP_DATABASE;

        $user = config('database.connections.mysql.username');
        $pwd = config('database.connections.mysql.password');
        $db = config('database.connections.mysql.database');

        $this->info("Creating redacted database from $db");

        // Create the groundhog day database
        DB::statement("DROP DATABASE IF EXISTS $redactedName");
        DB::statement("CREATE DATABASE $redactedName");

        putenv("MYSQL_PWD=$pwd");

        $this->info("Cloning $db to $redactedName");
        if (shell_exec("mysqldump -u $user  $db | mysql -u $user $redactedName")) {
            $this->fatal("Cannot clone database");
        }

        // Switch databases
        config([ 'database.connections.mysql.database' => $redactedName ]);
        DB::purge('mysql');

        RedactDatabase::execute(current_year());

        $this->info("Creating mysql redacted dump");
        $dump = $this->option('dumpfile') ?? "rangers-redacted-".date('Y-m-d').".sql";

        if (shell_exec("mysqldump -u $user $redactedName > $dump")) {
            $this->info("Failed to dump database - $redactedName has not been deleted.");
        } else {
            DB::statement("DROP DATABASE IF EXISTS $redactedName");
            $this->info("** Done! Database has been successfully created and dumped to $dump");
        }
    }
}
