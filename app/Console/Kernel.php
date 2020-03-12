<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        'App\Console\Commands\ClubhouseDailyReportCommand',
        'App\Console\Commands\ClubhousePhotoPendingCommand',
        'App\Console\Commands\ClubhouseDoceboCompletion',
    ];

    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        /*
         * Note: to avoid the use of a cache server (Laravel task scheduler onOneServer() relies on it),
         * each scheduled task must ensure it has not been run by another API instance by calling
         * TaskLog::attemptToStart()
         */

        if (config('clubhouse.DeploymentEnvironment') == 'Production') {
            // Let someone know what's been happening in the Clubhouse
            $schedule->command('clubhouse:daily-report')->hourly()->emailOutputTo('frankenstein@burningman.org');

            // Let the photo reviewers know if photos are queued up.
            $schedule->command('clubhouse:photo-pending')->twiceDaily(9, 21)->emailOutputTo('frankenstein@burningman.org');

            // Talk with Docebo to see who completed online training
            // Runs every 15 mins March thru September
            $schedule->command('clubhouse:docebo-completion')->cron('0,15,30,45 * * 3-9 *');
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
