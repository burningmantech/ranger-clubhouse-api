<?php

namespace Tests\Feature;

use Illuminate\Console\Command;
use Tests\TestCase;

class ClubhouseRedactDatabaseCommandTest extends TestCase
{
    /**
     * The command must refuse to run, before touching the database or
     * shelling out to mysqldump/mysql, when --tempdb names the same
     * database the command is redacting from.
     *
     * @return void
     */
    public function test_handle_fails_fast_when_tempdb_matches_source_database(): void
    {
        $db = config('database.connections.mysql.database');

        $this->artisan('clubhouse:redact-db', ['--tempdb' => $db])
            ->assertExitCode(Command::FAILURE);
    }
}
