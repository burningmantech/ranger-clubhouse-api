<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Lib\Moodle;

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
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (setting('OnlineTrainingEnabled') == false) {
            $this->info("Online course is disabled. Aborting.");
            return;
        }

        $courses = [ setting('MoodleHalfCourseId'), setting('MoodleFullCourseId') ];

        $this->info('Connecting to the Moodle Server.');
        $moodle = new Moodle;

        foreach ($courses as $courseId) {
            if (empty($courseId)) {
                continue;
            }

            $this->info("Scanning course id #{$courseId}");
            $moodle->processCourseCompletion($courseId);
        }
    }
}
