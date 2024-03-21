<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\Role;
use App\Models\Timesheet;
use App\Models\TimesheetLog;
use App\Models\Training;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

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
     * Is the person allowed to work the given position?
     *
     * @param Person $person
     * @param int $positionId
     * @param int $requiredPositionId
     * @param $response
     * @param bool $signonForced
     * @param $unqualifiedReason
     * @return bool
     */

    public static function checkWorkAuthorization(Person $person,
                                                  int    $positionId,
                                                  int    &$requiredPositionId,
                                                         &$response,
                                                  bool   &$signonForced,
                                                         &$unqualifiedReason): bool
    {
        $canForceSignon = Auth::user()?->hasRole([Role::ADMIN, Role::CAN_FORCE_SHIFT]);

        $personId = $person->id;

        // Confirm the person is allowed to sign in to the position
        if (!PersonPosition::havePosition($personId, $positionId)) {
            $response = ['status' => 'position-not-held'];
            return false;
        }

        $position = Position::find($positionId);
        // Are they trained for this position?
        if (!$position->no_training_required && !Training::isPersonTrained($person, $positionId, current_year(), $requiredPositionId)) {
            $positionRequired = Position::retrieveTitle($requiredPositionId);
            if ($canForceSignon) {
                $signonForced = true;
                $unqualifiedReason =  Position::UNQUALIFIED_UNTRAINED;
            } else {
                $response = [
                    'status' => 'not-trained',
                    'position_title' => $positionRequired,
                    'position_id' => $requiredPositionId
                ];
                return false;
            }
        }

        /**
         * A person must have an employee id if working a paid position. For international volunteers who may worked
         * but not get paid, a dummy code of "0" is fine.
         */

        // can't use empty() because "0" is treated as empty. feh.
        if ($position->paycode && is_null($person->employee_id)) {
            $response = [
                'status' => 'no-employee-id',
            ];
            return false;
        }

        // Sandman blocker - must be qualified
        if ($positionId == Position::SANDMAN && !Position::isSandmanQualified($person, $unqualifiedReason)) {
            if ($canForceSignon) {
                $signonForced = true;
            } else {
                 $response = [
                    'status' => 'not-qualified',
                    'unqualified_reason' => $unqualifiedReason,
                    'unqualified_message' => Position::UNQUALIFIED_MESSAGES[$unqualifiedReason],
                ];
                return false;
            }
        }

        return true;
    }

    /**
     * Return response for a timesheet sign-in or creation.
     *
     * @param string $action
     * @param Timesheet $timesheet
     * @param bool $signonForced
     * @param int $requiredPositionId
     * @param $unqualifiedReason
     * @param $log
     * @return JsonResponse
     */

    public static function reportSignIn(string    $action,
                                        Timesheet $timesheet,
                                        bool      $signonForced,
                                        int       $requiredPositionId,
                                                  $unqualifiedReason,
                                                  $log): JsonResponse
    {
        $response = [
            'status' => 'success',
            'timesheet_id' => $timesheet->id,
            'on_duty' => (string)$timesheet->on_duty,
        ];

        if ($signonForced) {
            $response['forced'] = true;
            $response['unqualified_reason'] = $unqualifiedReason;
            $response['unqualified_message'] = Position::UNQUALIFIED_MESSAGES[$unqualifiedReason];
            if ($requiredPositionId) {
                $response['required_training'] = Position::retrieveTitle($requiredPositionId);
            }

            $log['forced'] = ['reason' => $unqualifiedReason];
            if ($unqualifiedReason == Position::UNQUALIFIED_UNTRAINED) {
                $log['forced']['position_id'] = $requiredPositionId;
            }
        }

        $timesheet->log($action, $log);

        return response()->json($response);
    }
}