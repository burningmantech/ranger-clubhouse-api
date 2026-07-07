<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ClubhouseExpireOauthCodesCommandTest extends TestCase
{
    /**
     * Oauth codes expire 120 seconds after creation (see
     * OauthCode::EXPIRE_IN_SECONDS) and this command is the only thing that
     * enforces that expiry. If it were only scheduled once a day, an
     * "expired" code would remain redeemable for up to ~24 hours, so it must
     * run frequently instead.
     *
     * @return void
     */

    public function test_scheduled_every_five_minutes_not_daily(): void
    {
        config([
            'clubhouse.DeploymentEnvironment' => 'Production',
            'clubhouse.GroundhogDayTime' => null,
        ]);

        require base_path('routes/console.php');

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'clubhouse:expire-oauth-codes'));

        $this->assertNotNull($event, 'clubhouse:expire-oauth-codes is not scheduled.');
        $this->assertSame('*/5 * * * *', $event->expression);
    }
}
