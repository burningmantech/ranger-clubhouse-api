<?php

namespace App\Console\Commands;

use App\Mail\DailyReportMail;
use App\Models\ActionLog;
use App\Models\Broadcast;
use App\Models\ErrorLog;
use App\Models\EventDate;
use App\Models\PersonStatus;
use Illuminate\Console\Command;

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
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $failedBroadcasts = Broadcast::findLogs(['lastday' => true, 'failed' => true]);
        $errorLogs = ErrorLog::findForQuery(['lastday' => true, 'page_size' => 20])['error_logs'];

        $roleLogs = ActionLog::findForQuery([
            'lastday' => 'true',
            'page_size' => 1000,
            'events' => ['person-role-add', 'person-role-remove']], false)['action_logs'];

        $statusLogs = PersonStatus::whereRaw('created_at >= DATE_SUB(?, INTERVAL 1 DAY)', [now()])
            ->with(['person:id,callsign', 'person_source:id,callsign'])
            ->get();

        $settings = setting([
            'DashboardPeriod',
            'LoginManageOnPlayaEnabled',
            'OnlineTrainingDisabledAllowSignups',
            'OnlineTrainingEnabled',
            'PhotoUploadEnable',
            'TicketingPeriod',
            'TimesheetCorrectionEnable',
            'TrainingSeasonalRoleEnabled',
        ]);

        $settingLogs = ActionLog::findForQuery(
            [
                'lastday' => true,
                'page_size' => 1000,
                'events' => ['setting-update'],
            ],
            false
        )['action_logs'];

        $emailIssues = ActionLog::findForQuery(
            [
                'lastday' => true,
                'page_size' => 1000,
                'events' => ['email-bouncing', 'email-complaint'],
            ],
            false
        )['action_logs'];

        mail_to(setting('DailyReportEmail'),
            new DailyReportMail(
                $failedBroadcasts,
                $errorLogs,
                $roleLogs,
                $statusLogs,
                $settings,
                $settingLogs,
                $emailIssues,
                EventDate::calculatePeriod()
            ));

        return true;
    }
}
