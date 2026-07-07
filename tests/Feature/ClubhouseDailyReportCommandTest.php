<?php

namespace Tests\Feature;

use App\Mail\DailyReportMail;
use App\Models\ErrorLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClubhouseDailyReportCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ErrorLog::findForQuery() only recognizes the 'last_day' query key (see
     * ErrorLog::findForQuery()). The command previously passed 'lastday', so the
     * filter silently never applied and every error log ever recorded was mailed
     * out, not just the last 24 hours' worth.
     *
     * @return void
     */

    public function test_handle_only_includes_error_logs_from_the_last_day(): void
    {
        Mail::fake();

        ErrorLog::create([
            'error_type' => 'old-error',
            'data' => '{}',
            'created_at' => now()->subDays(2),
        ]);

        ErrorLog::create([
            'error_type' => 'recent-error',
            'data' => '{}',
            'created_at' => now()->subHours(2),
        ]);

        $this->artisan('clubhouse:daily-report')->assertExitCode(0);

        $mail = Mail::sent(DailyReportMail::class)->first()
            ?? Mail::queued(DailyReportMail::class)->first();

        $this->assertNotNull($mail, 'DailyReportMail was not sent or queued.');

        $errorTypes = $mail->errorLogs->pluck('error_type')->all();

        $this->assertContains('recent-error', $errorTypes);
        $this->assertNotContains('old-error', $errorTypes);
    }
}
