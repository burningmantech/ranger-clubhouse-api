<?php

namespace App\Console\Commands;

use App\Lib\GrantPasses;
use App\Mail\RangersWhoNeedWorkAccessPassesMail;
use Illuminate\Console\Command;

class ClubhouseRangersWhoNeedWAPsReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:ranger-waps-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build and email a Rangers Who Need WAPs Report';

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public function handle(): mixed
    {
        $email = setting('TAS_WAP_Report_Email');
        if (empty($email)) {
            return true;
        }

        list ($people,$startYear) = GrantPasses::findRangersWhoNeedWAPs();
        mail_to($email, new RangersWhoNeedWorkAccessPassesMail($people,$startYear));

        return true;
    }
}
