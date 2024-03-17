<?php

use App\Jobs\SignOutTimesheetsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

/*
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');
*/

if (config('clubhouse.DeploymentEnvironment') == 'Production' && ! is_ghd_server()) {
    // Let someone know what's been happening in the Clubhouse
    Schedule::command('clubhouse:daily-report')->dailyAt('03:00')->onOneServer();

    // Let the photo reviewers know if photos are queued up.
    Schedule::command('clubhouse:photo-pending')->twiceDaily(9, 21)->onOneServer();

    // Let the vehicle request reviewers know if vehicles are queued up.
    Schedule::command('clubhouse:vehicle-pending')->dailyAt('19:00')->onOneServer();

    // Talk with Moodle to see who completed online course
    // Runs every 30 mins April through mid-September
    Schedule::command('clubhouse:moodle-completion')->cron('0,10,20,30,40,50 * * 4-8 *')->onOneServer();
    Schedule::command('clubhouse:moodle-completion')->cron('0,10,20,30,40,50 * 1-10 9 *')->onOneServer();

    // Run the Rangers Who Need WAPs Report
    // At 02:30 on Mondays July through August.
    Schedule::command('clubhouse:ranger-waps-report')
        ->cron('30 2 * 7-8 1')
        ->onOneServer();

    // Cleanup the mail log
    Schedule::command('clubhouse:cleanup-maillog')->dailyAt('03:30')->onOneServer();

    // Cleanup oauth codes
    Schedule::command('clubhouse:expire-oauth-codes')->dailyAt('03:00')->onOneServer();

    // Cleanup session tokens
    Schedule::command('sanctum:prune-expired --hours=24')->daily()->onOneServer();

    // Sign out timesheets
    Schedule::job(new SignOutTimesheetsJob())->cron('0,10,20,30,40,50 * 15-31 8 *')->onOneServer();
    Schedule::job(new SignOutTimesheetsJob())->cron('0,10,20,30,40,50 * 1-10 9 *')->onOneServer();

    // Let the VCs know applications are pending.
    Schedule::command('clubhouse:pending-applications')->dailyAt('17:00')->onOneServer();

    // Prune failed jobs
    Schedule::command('queue:prune-failed --hours=48')->dailyAt('04:00')->onOneServer();

    // Expire stale MOTDs
    Schedule::command('clubhouse:expire-announcements')->dailyAt('05:00')->onOneServer();

    if (config('telescope.enabled')) {
        // Purge Laravel Telescope logs
        Schedule::command('telescope:prune --hours=72')->dailyAt('05:30')->onOneServer();
    }
}

if (is_ghd_server()) {
    // Reset the GHD Server
    Schedule::command('clubhouse:reload-training-db')->dailyAt('04:00')->onOneServer();
}
