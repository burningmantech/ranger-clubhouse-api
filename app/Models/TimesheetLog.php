<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class TimesheetLog extends ApiModel
{
    protected $table = "timesheet_log";

    // allow mass assignment - all records are created behind the scenes
    protected $guarded = [];

    const string UNCONFIRMED = 'unconfirmed';  // Entire timesheet marked unconfirmed (usually an entry was updated/created)
    const string CONFIRMED = 'confirmed';      // Entire timesheet marked confirmed

    const string SIGNON = 'signon';    // Timesheet entry created via shift start
    const string SIGNOFF = 'signoff';  // Shift was ended
    const string UPDATE = 'update';    // Timesheet entry updated
    const string DELETE = 'delete';    // Timesheet entry deleted
    const string DELETE_MISTAKE = 'delete-mistake';    // Timesheet entry deleted due to accidental creation

    const string UNVERIFIED = 'unverified'; // Entry was marked unverified
    const string VERIFY = 'verify';    // Entry marked verified (aka correct)

    const string CREATED = 'created';  // created via bulk update or missing timesheet request

    const string VIA_BULK_UPLOAD = 'bulk-upload';  // Entry created via Bulk Uploader
    const string VIA_MISSING_ENTRY = 'missing-entry';  // Entry created via Missing Timesheet Request

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'data' => 'array'
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'create_person_id');
    }

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    /**
     * Find the logs for a person & year. Include the timesheet and positions
     *
     * Use the on_duty date to lookup, timesheet_log.created_at may
     * be in different year.
     *
     * @param int $personId
     * @param int $year
     * @return array
     */

    public static function findForPersonYear(int $personId, int $year): array
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

    public static function updateYear(int $timesheetId, int $year): void
    {
        DB::table('timesheet_log')->where('timesheet_id', $timesheetId)->update(['year' => $year]);
    }

    /**
     * Record a timesheet signon/off, creation, update, deletion, and person confirmation.
     *
     * @param string $action timesheet action
     * @param int $personId the timesheet owner
     * @param int|null $userId user performing the action
     * @param int|null $timesheetId timesheet id (maybe null for 'confirmed')
     * @param mixed $data required data usually includes modified columns.
     * @param int $year
     */

    public static function record(string $action, int $personId, ?int $userId, int|null $timesheetId, mixed $data, int $year = 0): void
    {
        $columns = [
            'action' => $action,
            'person_id' => $personId,
            'create_person_id' => $userId,
            'data' => is_array($data) ? $data : ['message' => $data],
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


        $positionId = $data->position_id ?? null;
        if ($positionId) {
            if (is_array($positionId)) {
                list ($oldId, $newId) = $positionId;
                $data->position_id = [
                    ['id' => $oldId, 'title' => Position::retrieveTitle($oldId)],
                    ['id' => $newId, 'title' => Position::retrieveTitle($newId)],
                ];
            } else {
                $data->position_title = Position::retrieveTitle($data->position_id);
            }
        }

        $forced = $data->forced ?? null;
        if ($forced) {
            $positionId = $forced->position_id ?? null;
            if ($positionId) {
                $forced->position_title = Position::retrieveTitle($positionId);
            }

            $forced->message = Position::UNQUALIFIED_MESSAGES[$forced->reason] ?? "Unknown reason [{$forced->reason}]";
        }

        return $data;
    }
}
