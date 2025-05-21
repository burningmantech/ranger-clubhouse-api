<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\Slot;
use App\Models\Timesheet;
use App\Models\TimesheetLog;
use App\Models\Training;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TimesheetManagement
{
    /**
     * Unconfirm the entire timesheet
     *
     * @param Timesheet $timesheet
     * @param string $reason
     * @return void
     */
    public static function unconfirmTimesheet(Timesheet $timesheet, string $reason): void
    {
        $year = $timesheet->on_duty->year;

        // Only unconfirm a timesheet if it's the current year.
        if ($year != current_year()) {
            return;
        }

        $event = PersonEvent::firstOrNewForPersonYear($timesheet->person_id, $year);
        if (!$event->timesheet_confirmed) {
            return;
        }

        $event->timesheet_confirmed = false;
        $event->timesheet_confirmed_at = null;
        $event->saveWithoutValidation();
        $timesheet->log(TimesheetLog::UNCONFIRMED, "timesheet #{$timesheet->id} {$reason}");
    }

    /**
     * Return response for a timesheet sign-in or creation.
     *
     * @param string $action
     * @param Timesheet $timesheet
     * @param bool $signInForced
     * @param array $blockers
     * @param $log
     * @return JsonResponse
     */

    public static function reportSignIn(string    $action,
                                        Timesheet $timesheet,
                                        bool      $signInForced,
                                        array     $blockers,
                                                  $log): JsonResponse
    {
        $response = [
            'status' => 'success',
            'timesheet_id' => $timesheet->id,
            'on_duty' => (string)$timesheet->on_duty,
            'slot_url' => $timesheet->slot?->url,
            'position_title' => $timesheet->position->title,
        ];

        if ($signInForced) {
            $response['forced'] = true;
            $log['forced'] = true;
            $log['blockers'] = $blockers;
        }

        $timesheet->log($action, $log);

        return response()->json($response);
    }
}