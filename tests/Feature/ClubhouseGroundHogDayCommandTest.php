<?php

namespace Tests\Feature;

use Tests\TestCase;

class ClubhouseGroundHogDayCommandTest extends TestCase
{
    /**
     * When --tempdb matches the live/source database name, the command must
     * refuse to run before issuing any DROP DATABASE (or shell) command
     * against the live database, instead of dropping the source database.
     */
    public function test_handle_refuses_when_tempdb_matches_source_database(): void
    {
        $sourceDatabase = config('database.connections.mysql.database');

        $this->artisan('clubhouse:groundhog-day', ['--tempdb' => $sourceDatabase])
            ->expectsOutputToContain('cannot be the same as the source database')
            ->assertFailed();
    }
}
