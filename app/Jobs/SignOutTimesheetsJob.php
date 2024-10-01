<?php

namespace App\Jobs;

use App\Mail\AutomaticSignOutMail;
use App\Models\Timesheet;
use App\Models\TimesheetLog;
use App\Models\TimesheetNote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SignOutTimesheetsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Scan for timesheets eligible to be signed out.
     */

    public function handle(): void
    {
        $rows = Timesheet::select('timesheet.*')
            ->join('position', 'position.id', 'timesheet.position_id')
            ->whereNull('off_duty')
            ->where('position.auto_sign_out', true)
            ->where('sign_out_hour_cap', '>', 0)
            // Give a hour grace period just in case.
            ->whereRaw('TIME_TO_SEC(TIMEDIFF(?, on_duty)) >= (sign_out_hour_cap * 3600)', [now()])
            ->with(['position:id,title,sign_out_hour_cap,auto_sign_out,contact_email', 'person:id,callsign,status'])
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $positionIds = [];
        foreach ($rows as $row) {
            $row->auditReason = 'automatic signout';
            $row->off_duty = $row->on_duty->clone()->addSeconds((int)($row->position->sign_out_hour_cap * 3600));
            $row->save();
            $row->log(TimesheetLog::SIGNOFF, [
                'position_id' => $row->position_id,
                'off_duty' => (string)$row->off_duty,
            ]);

            TimesheetNote::record($row->id, null, "Hello from the Clubhouse Timesheet Bot! The timesheet entry has been automatically signed out of and capped at {$row->position->sign_out_hour_cap} hours. Please submit a correction request if the times are not accurate.", TimesheetNote::TYPE_WRANGLER);

            if (!isset($positionIds[$row->position_id])) {
                $positionIds[$row->position_id] = [
                    'title' => $row->position->title,
                    'contact_email' => $row->position->contact_email,
                    'hour_cap' => $row->position->sign_out_hour_cap,
                    'entries' => []
                ];
            }
            $positionIds[$row->position_id]['entries'][] = $row;
        }

        foreach ($positionIds as $positionId => $position) {
            $email = $position['contact_email'] ?? null;
            if (empty($email)) {
                continue;
            }
            mail_to($position['contact_email'], new AutomaticSignOutMail($position['contact_email'], $position['title'], $position['hour_cap'], $position['entries']), false);
        }
    }
}
