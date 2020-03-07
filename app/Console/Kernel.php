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
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        error_log("Scheduler route called Environment is [".config('clubhouse.DeploymentEnvironment')."]");
        //if (config('clubhouse.DeploymentEnvironment') == 'Production') {
            // Let someone know what's been happening in the Clubhouse
            $schedule->command('clubhouse:daily-report')->everyMinute()->onOneServer()->emailOutputTo('frankenstein@burningman.org');

            // Let the photo reviewers know if photos are queued up.
            $schedule->command('clubhouse:photo-pending')->twiceDaily(9, 21)->onOneServer();
//        }
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
