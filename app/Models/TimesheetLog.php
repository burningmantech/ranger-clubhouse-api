<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\ApiModel;

class TimesheetLog extends ApiModel
{
    protected $table = "timesheet_log";

    // all mass assignment
    protected $guarded = [];

    /*
     * Record a timesheet signon/off, creation, update, deletion, and person confirmation.
     *
     * $action is one of the following:
     *
     * 'created' - full timeshift record created. Both on_duty & off_duty set. (bulk up, missing timesheet request)
     * 'signon' - shift started
     * 'signoff' - shift ended
     * 'delete' - timesheet removed
     * 'confirmed' - person confirmed or unconfirmed timesheets are correct (aka set person.timesheet_confirmed)
     * 'review' - review status change (approved, denied)
     * 'verify' - verification status update.
     *
     * @param string $action timesheet action
     * @param int $personId the timesheet owner
     * @param int $userId user performing the action
     * @param int $timesheetId timesheet id (maybe null for 'confirmed')
     * @param string $message required message usually includes modified columns.
     */

    public static function record($action, $personId, $userId, $timesheetId, $message)
    {
        $columns = [
           'action'           => $action,
           'person_id'        => $personId,
           'create_person_id' => $userId,
           'message'          => $message,
        ];

        if ($timesheetId) {
            $columns['timesheet_id'] = $timesheetId;
        }

        self::create($columns);
    }

}
