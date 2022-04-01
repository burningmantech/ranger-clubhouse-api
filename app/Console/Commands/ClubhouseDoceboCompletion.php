<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Lib\Docebo;
use App\Models\TaskLog;

class ClubhouseDoceboCompletion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:docebo-completion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Query the Docebo server and mark those who have completed online course';

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

        $courses = [ setting('DoceboHalfCourseId'), setting('DoceboFullCourseId') ];

        $this->info('Connecting to Decebo.');
        $docebo = new Docebo;

        foreach ($courses as $courseId) {
            if (empty($courseId)) {
                continue;
            }

            $this->info("Scanning course id #{$courseId}");
            $users = $docebo->retrieveCourseEnrollment($courseId);
            $docebo->linkUsersAndMarkComplete($users);
        }
    }
}
