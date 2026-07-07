<?php

namespace Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubhouseRangersWhoNeedWAPsReportCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * When the TAS_SAP_Report_Email setting is not configured, the command
     * exits early without building the report. Previously it returned
     * `true`, which Laravel casts to a failure exit code (1); it must
     * report success instead.
     *
     * @return void
     */

    public function test_handle_returns_success_when_report_email_is_not_configured(): void
    {
        $this->artisan('clubhouse:ranger-waps-report')
            ->assertExitCode(Command::SUCCESS);
    }
}
