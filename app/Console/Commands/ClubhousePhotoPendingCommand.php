<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PersonPhoto;
use App\Mail\PhotoPendingMail;
use App\Models\TaskLog;

class ClubhousePhotoPendingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:photo-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send an email to people listed in PhotoPendingNotifyEmail if photos are queued for review';

     /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (TaskLog::attemptToStart($this->signature) == false) {
            $this->info("command has been run already");
            return;
        }

        $pendingPhotos = PersonPhoto::findAllPending();

        if ($pendingPhotos->isNotEmpty()){
            mail_to(setting('PhotoPendingNotifyEmail'), new PhotoPendingMail($pendingPhotos));
        }
    }
}
