<?php

namespace App\Console\Commands;

use App\Mail\DailyReportMail;

use App\Models\ActionLog;
use App\Models\Broadcast;
use App\Models\ErrorLog;
use App\Models\PersonStatus;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClubhouseDailyReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:daily-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build and email the daily report';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $failedBroadcasts = Broadcast::findLogs(['lastday' => true, 'failed' => true]);
        $errorLogs = ErrorLog::findForQuery(['lastday' => true, 'page_size' => 1000])['error_logs'];

        $roleLogs = ActionLog::findForQuery(['lastday' => 'true', 'page_size' => 1000, 'events' => ['person-role-add', 'person-role-remove']], false)['action_logs'];
        $statusLogs = PersonStatus::where('created_at', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 25 HOUR)'))->with(['person:id,callsign', 'person_source:id,callsign'])->get();

        mail_to(setting('DailyReportEmail'), new DailyReportMail($failedBroadcasts, $errorLogs, $roleLogs, $statusLogs));
    }
}
