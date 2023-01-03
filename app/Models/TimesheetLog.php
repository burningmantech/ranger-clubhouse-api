<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class TimesheetLog extends ApiModel
{
    protected $table = "timesheet_log";

    // allow mass assignment - all records are created behind the scenes
    protected $guarded = [];

    const UNCONFIRMED = 'unconfirmed';  // Entire timesheet marked unconfirmed (usually an entry was updated/created)
    const CONFIRMED = 'confirmed';      // Entire timesheet marked confirmed

    const SIGNON = 'signon';    // Timesheet entry created via shift start
    const SIGNOFF = 'signoff';  // Shift was ended
    const UPDATE = 'update';    // Timesheet entry updated
    const DELETE = 'delete';    // Timesheet entry deleted

    const UNVERIFIED = 'unverified'; // Entry was marked unverified
    const VERIFY = 'verify';    // Entry marked verified (aka correct)

    const CREATED = 'created';  // created via bulk update or missing timesheet request

    const VIA_BULK_UPLOAD = 'bulk-upload';  // Entry created via Bulk Uploader
    const VIA_MISSING_ENTRY = 'missing-entry';  // Entry created via Missing Timesheet Request

    protected $casts = [
        'created_at' => 'datetime'
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
     * Use the on_duty date to lookup, timesheet_log.created_at may
     * be in different year.
     *
     * @param integer $personId
     * @param integer $year
     */

    public static function findForPersonYear($personId, $year)
    {
        $timesheets = self::select('timesheet_log.*')
            ->with([
                'person:id,callsign', 'creator:id,callsign',
                'timesheet:id,on_duty,off_duty,position_id',
                'timesheet.position:id,title'
            ])
            ->leftJoin('timesheet', 'timesheet.id', 'timesheet_log.timesheet_id')
            ->whereNotNull('timesheet_log.timesheet_id')
            ->where('timesheet_log.person_id', $personId)
            ->where('timesheet_log.year', $year)
            ->orderBy('timesheet_log.created_at')
            ->get();

        // Possible issue: if the person confirms their timesheet in the year following
        // it may not be seen in the year being view. (.e.g, hubcap worked in 2018, yet
        // did not confirm his timesheets until 2019. The confirmation would appear in the 2019 log.)
        $other = self::with(['person:id,callsign', 'creator:id,callsign'])
            ->where('person_id', $personId)
            ->whereYear('created_at', $year)
            ->whereNull('timesheet_id')
            ->orderBy('created_at')
            ->get();

        return [$timesheets, $other];
    }

    public static function updateYear($timesheetId, $year)
    {
        DB::update("UPDATE timesheet_log SET year=? WHERE timesheet_id=? ", [$timesheetId, $year]);
    }

    /**
     * Record a timesheet signon/off, creation, update, deletion, and person confirmation.
     *
     * @param string $action timesheet action
     * @param int $personId the timesheet owner
     * @param int $userId user performing the action
     * @param int|null $timesheetId timesheet id (maybe null for 'confirmed')
     * @param mixed $data required data usually includes modified columns.
     * @param int $year
     */

    public static function record(string $action, int $personId, int $userId, int|null $timesheetId, $data, int $year = 0)
    {
        $columns = [
            'action' => $action,
            'person_id' => $personId,
            'create_person_id' => $userId,
            'data' => json_encode(is_array($data) ? $data : ['message' => $data]),
            'year' => $year ? $year : current_year(),
            'created_at' => now(),
        ];

        if ($timesheetId) {
            $columns['timesheet_id'] = $timesheetId;
        }

        self::create($columns);
    }

    public function decodeData()
    {
        $data = $this->data;

        if (!$data) {
            return null;
        }

        $decode = json_decode($data);
        if (!$decode) {
            return $data;
        }

        $positionId = $decode->position_id ?? null;
        if ($positionId) {
            if (is_array($positionId)) {
                list ($oldId, $newId) = $positionId;
                $decode->position_id = [
                    ['id' => $oldId, 'title' => Position::retrieveTitle($oldId)],
                    ['id' => $newId, 'title' => Position::retrieveTitle($newId)],
                ];
            } else {
                $decode->position_title = Position::retrieveTitle($decode->position_id);
            }
        }

        $forced = $decode->forced ?? null;
        if ($forced) {
            $positionId = $forced->position_id ?? null;
            if ($positionId) {
                $forced->position_title = Position::retrieveTitle($positionId);
            }

            $forced->message = Position::UNQUALIFIED_MESSAGES[$forced->reason] ?? "Unknown reason [{$forced->reason}]";
        }

        return $decode;
    }
}
