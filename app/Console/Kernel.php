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
        'App\Console\Commands\ClubhouseVehiclePendingCommand',
        'App\Console\Commands\ClubhouseMoodleCompletion',
    ];

    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        if (config('clubhouse.DeploymentEnvironment') == 'Production' && !is_ghd_server()) {
            // Let someone know what's been happening in the Clubhouse
            $schedule->command('clubhouse:daily-report')->dailyAt('03:00')->onOneServer();

            // Let the photo reviewers know if photos are queued up.
            $schedule->command('clubhouse:photo-pending')->twiceDaily(9, 21)->onOneServer();

            // Let the vehicle request reviewers know if vehicles are queued up.
            $schedule->command('clubhouse:vehicle-pending')->dailyAt('19:00')->onOneServer();

            // Talk with Moodle to see who completed online course
            // Runs every 30 mins April through mid-September
            $schedule->command('clubhouse:moodle-completion')->cron('0,10,20,30,40,50 * * 4-8 *')->onOneServer();
            $schedule->command('clubhouse:moodle-completion')->cron('0,10,20,30,40,50 * 1-10 9 *')->onOneServer();

            // Run the Rangers Who Need WAPs Report
            // At 02:30 on Mondays July through August.
            $schedule->command('clubhouse:ranger-waps-report')
                ->cron('30 2 * 7-8 1')
                ->onOneServer();

            // Cleanup the mail log
            $schedule->command('clubhouse:cleanup-maillog')->dailyAt('03:30')->onOneServer();
        }

        if (is_ghd_server()) {
            // Reset the GHD Server
            $schedule->command('clubhouse:reload-training-db')->dailyAt('04:00')->onOneServer();
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
