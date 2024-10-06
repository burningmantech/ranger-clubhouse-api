<?php

namespace App\Console\Commands;

use App\Mail\ProspectiveApplicant\ApprovedReminderMail;
use App\Models\ProspectiveApplication;
use Illuminate\Console\Command;

class ClubhousePendingApplicationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:pending-applications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Email the V.C.s about pending applications.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $applications = ProspectiveApplication::retrieveApproved();

        if ($applications->isNotEmpty()) {
            mail_send(new ApprovedReminderMail($applications));
        }
    }
}
