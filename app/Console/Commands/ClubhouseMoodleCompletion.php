<?php

namespace App\Console\Commands;

use App\Exceptions\MoodleConnectFailureException;
use App\Exceptions\MoodleDownForMaintenanceException;
use App\Lib\Moodle;
use App\Models\OnlineCourse;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ClubhouseMoodleCompletion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:moodle-completion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Query the Moodle server and mark those who have completed online course';

    /**
     * Scan the Moodle courses to see who completed.
     *
     * @return void
     */

    public function handle(): void
    {
        if (!setting('OnlineCourseEnabled')) {
            $this->info("Online course is disabled. Aborting.");
            return;
        }

        $courses = OnlineCourse::findForQuery([ 'year' => current_year() ]);
        $this->info('Connecting to the Moodle Server.');
        $moodle = new Moodle;

        foreach ($courses as $course) {
            $this->info("Scanning course id #{$course->id}, lms course id {$course->course_id}");
            try {
                $moodle->processCourseCompletion($course);
            } catch (MoodleDownForMaintenanceException $e) {
                $this->error("Moodle down for maintenance");
                return;
            } catch (MoodleConnectFailureException $e) {
                $this->error("Failed to connect to Moodle server ".$e->getMessage());
            }
        }
    }
}
