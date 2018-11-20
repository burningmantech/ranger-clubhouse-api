<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\ApiModel;

use App\Models\Person;
use App\Models\Timesheet;

class TimesheetLog extends ApiModel
{
    protected $table = "timesheet_log";

    // all mass assignment
    protected $guarded = [];

    protected $casts = [
        'created_at'    => 'datetime'
    ];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function creator()
    {
        return $this->belongsTo(Person::class, 'create_person_id');
    }

    public function timesheet()
    {
        return $this->belongsTo(Timesheet::class);
    }

    /**
     * Find the logs for a person & year. Include the timesheet and positions
     *
     * @param integer $personId
     * @param integer $year
     */

    public static function findForPersonYear($personId, $year)
    {
        $timesheets = self::with([
                    'person:id,callsign', 'creator:id,callsign',
                    'timesheet:id,on_duty,off_duty,position_id',
                    'timesheet.position:id,title'
            ])
            ->where('person_id', $personId)
            ->whereYear('created_at', $year)
            ->whereNotNull('timesheet_id')
            ->orderBy('created_at')
            ->get();

        $other = self::with([ 'person:id,callsign', 'creator:id,callsign'])
            ->where('person_id', $personId)
            ->whereYear('created_at', $year)
            ->whereNull('timesheet_id')
            ->orderBy('created_at')->get();

        return [ $timesheets, $other ];
    }

    /**
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
     * @param string $action      timesheet action
     * @param int    $personId    the timesheet owner
     * @param int    $userId      user performing the action
     * @param int    $timesheetId timesheet id (maybe null for 'confirmed')
     * @param string $message     required message usually includes modified columns.
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
