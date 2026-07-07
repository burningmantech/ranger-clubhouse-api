<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClubhouseCleanupMailLogCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Insert a minimal mail_log row with the given created_at timestamp.
     *
     * @param string $createdAt
     * @return int the inserted row id
     */

    private function insertMailLog(string $createdAt): int
    {
        return DB::table('mail_log')->insertGetId([
            'from_email' => 'sender@example.com',
            'to_email' => 'recipient@example.com',
            'message_id' => 'msg-' . uniqid(),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    /**
     * The purge cutoff must be computed with subMonthsNoOverflow() so that running
     * the command on a month-end date (e.g., Aug 31) does not overflow into March
     * and wrongly delete records that are actually less than 6 months old.
     *
     * @return void
     */

    public function test_handle_does_not_purge_records_younger_than_six_months_on_month_end(): void
    {
        Carbon::setTestNow('2027-08-31 12:00:00');

        // Correct cutoff (subMonthsNoOverflow) is 2027-02-28 12:00:00.
        // Buggy cutoff (subMonth overflow) would be 2027-03-03 12:00:00.
        // This record falls between the two cutoffs, so it must survive.
        $boundaryId = $this->insertMailLog('2027-03-01 12:00:00');

        // Well beyond 6 months old; must be purged regardless of the bug.
        $oldId = $this->insertMailLog('2027-01-01 12:00:00');

        $this->artisan('clubhouse:cleanup-maillog')
            ->assertExitCode(0);

        $this->assertDatabaseHas('mail_log', ['id' => $boundaryId]);
        $this->assertDatabaseMissing('mail_log', ['id' => $oldId]);
    }
}
