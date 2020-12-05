<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

use App\Lib\WorkSummary;

use App\Models\ApiModel;
use App\Models\Person;
use App\Models\PositionCredit;
use App\Models\Slot;


class Timesheet extends ApiModel
{
    protected $table = 'timesheet';
    protected $auditModel = true;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_VERIFIED = 'verified';
    const STATUS_UNVERIFIED = 'unverified';

    const EXCLUDE_POSITIONS_FOR_YEARS = [
        Position::ALPHA,
        Position::TRAINING,
    ];

    protected $fillable = [
        'off_duty',
        'on_duty',
        'person_id',
        'position_id',
        'review_status',
        'reviewed_at',
        'reviewer_person_id',
        'timesheet_confirmed_at',
        'timesheet_confirmed',
        'slot_id',

        'additional_notes',  // pseudo field -- appends to notes
        'additional_reviewer_notes'  // pseudo field -- appends to reviewer_notes

        // no longer directly settable: notes, reviewer_notes
    ];

    protected $rules = [
        'person_id' => 'required|integer',
        'position_id' => 'required|integer',
        'off_duty' => 'nullable|sometimes|after_or_equal:on_duty'
    ];

    protected $appends = [
        'duration',
        'credits',
    ];

    protected $dates = [
        'off_duty',
        'on_duty',
        'reviewed_at',
        'timesheet_confirmed_at',
        'verified_at',
    ];

    const RELATIONSHIPS = ['reviewer_person:id,callsign', 'verified_person:id,callsign', 'position:id,title,count_hours'];

    public $_additional_notes;
    public $_additional_reviewer_notes;

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function reviewer_person()
    {
        return $this->belongsTo(Person::class);
    }

    public function verified_person()
    {
        return $this->belongsTo(Person::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function slot()
    {
        return $this->belongsTo(Slot::class);
    }

    public function loadRelationships()
    {
        return $this->load(self::RELATIONSHIPS);
    }

    public static function findForQuery($query)
    {
        $year = 0;
        $sql = self::query();

        $year = $query['year'] ?? null;
        $personId = $query['person_id'] ?? null;
        $isOnDuty = $query['is_on_duty'] ?? false;
        $dutyDate = $query['duty_date'] ?? null;
        $overHours = $query['over_hours'] ?? 0;
        $onDutyStart = $query['on_duty_start'] ?? null;
        $onDutyEnd = $query['on_duty_end'] ?? null;
        $positionId = $query['position_id'] ?? null;

        if ($year) {
            $sql->whereYear('on_duty', $year);
        }

        if ($personId) {
            $sql->where('person_id', $personId);
        } else {
            $sql->with('person:id,callsign');
        }

        if ($isOnDuty) {
            $sql->whereNull('off_duty');
            if ($overHours) {
                $sql->whereRaw("TIMESTAMPDIFF(HOUR, on_duty, ?) >= ?", [now(), $overHours]);
            }
        }

        if ($dutyDate) {
            $sql->where('on_duty', '<=', $dutyDate);
            $sql->whereRaw('IFNULL(off_duty, ?) >= ?', [now(), $dutyDate]);
        }

        if ($onDutyStart) {
            $sql->where('on_duty', '>=', $onDutyStart);
        }

        if ($onDutyEnd) {
            $sql->where('on_duty', '<=', $onDutyEnd);
        }

        if ($positionId) {
            $sql->where('position_id', $positionId);
        }

        $sql->with(self::RELATIONSHIPS);

        $rows = $sql->orderBy('on_duty', 'asc')->get();

        if (!$personId) {
            $rows = $rows->sortBy('person.callsign', SORT_NATURAL | SORT_FLAG_CASE)->values();
        }

        return $rows;
    }

    public static function findPersonOnDuty($personId)
    {
        return self::where('person_id', $personId)
            ->whereYear('on_duty', current_year())
            ->whereNull('off_duty')
            ->with(['position:id,title,type'])
            ->first();
    }

    /*
     * Check to see if a person is signed into a position(s)
     */

    public static function isPersonSignIn($personId, $positionIds)
    {
        $sql = self::where('person_id', $personId)->whereNull('off_duty');
        if (is_array($positionIds)) {
            $sql->whereIn('position_id', $positionIds);
        } else {
            $sql->where('position_id', $positionIds);
        }

        return $sql->exists();
    }

    public static function findOnDutyForPersonYear($personId, $year)
    {
        return self::where('person_id', $personId)
            ->whereNull('off_duty')
            ->whereYear('on_duty', $year)
            ->with('position:id,title,type')
            ->first();
    }

    /*
     * Find an existing overlapping timesheet entry for a date range
     */

    public static function findOverlapForPerson($personId, $onduty, $offduty)
    {
        return self::where('person_id', $personId)
            ->where(function ($sql) use ($onduty, $offduty) {
                $sql->whereBetween('on_duty', [$onduty, $offduty]);
                $sql->orWhereBetween('off_duty', [$onduty, $offduty]);
                $sql->orWhereRaw('? BETWEEN on_duty AND off_duty', [$onduty]);
                $sql->orWhereRaw('? BETWEEN on_duty AND off_duty', [$offduty]);
            })->first();
    }

    public static function findShiftWithinMinutes($personId, $startTime, $withinMinutes)
    {
        return self::with(['position:id,title'])
            ->where('person_id', $personId)
            ->whereRaw(
                'on_duty BETWEEN DATE_SUB(?, INTERVAL ? MINUTE) AND DATE_ADD(?, INTERVAL ? MINUTE)',
                [$startTime, $withinMinutes, $startTime, $withinMinutes]
            )->first();
    }

    /**
     * Find the years a person was on working
     *
     * @param int $personId
     * @param bool $everything if true include all scheduled years as well
     * @return array
     */

    public static function years(int $personId, bool $everything = false)
    {
        $query = self::selectRaw("YEAR(on_duty) as year")
            ->where('person_id', $personId)
            ->groupBy("year")
            ->orderBy("year", "asc");

        if (!$everything) {
            $query = $query->whereNotIn("position_id", self::EXCLUDE_POSITIONS_FOR_YEARS);
        }

        $years = $query->pluck('year')->toArray();

        if (!$everything) {
            return $years;
        }

        // Look at the sign up schedule as well
        $signUpYears = DB::table('person_slot')
            ->selectRaw("YEAR(begins) as year")
            ->join('slot', 'slot.id', '=', 'person_slot.slot_id')
            ->where('person_id', $personId)
            ->groupBy('year')
            ->orderBy('year')
            ->pluck('year')
            ->toArray();

        $years = array_unique(array_merge($years, $signUpYears));
        sort($years, SORT_NUMERIC);

        return $years;
    }

    /*
     * Find the latest timesheet entry for a person in a position and given year
     */

    public static function findLatestForPersonPosition($personId, $positionId, $year)
    {
        return self::where('person_id', $personId)
            ->where('position_id', $positionId)
            ->whereYear('on_duty', $year)
            ->orderBy('on_duty', 'desc')
            ->first();
    }

    /*
     * Find out how many years list of people have rangered.
     *
     * If the person has never rangered for whatever reason that person
     * will not be included in the return list of person/years.
     *
     * @param array $personIds  list of person ids
     * @return array years rangered keyed by person id.
     *   [ 'person1_id' => 'years', 'person2_id' => 'years' ]
     */

    public static function yearsRangeredCountForIds($personIds)
    {
        $ids = [];
        foreach ($personIds as $id) {
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if (empty($ids)) {
            return [];
        }

        $excludePositions = implode(',', [Position::ALPHA, Position::TRAINING]);
        $rows = DB::select("SELECT person_id, COUNT(year) as years FROM (SELECT YEAR(on_duty) as year, person_id FROM timesheet WHERE person_id in (" . implode(',', $ids) . ") AND  position_id not in ($excludePositions) GROUP BY person_id,year ORDER BY year) as rangers group by person_id");

        $people = [];
        foreach ($rows as $row) {
            $people[$row->person_id] = $row->years;
        }

        return $people;
    }

    /*
     * Find out if the person has an alpha timesheet entry for the current year
     */

    public static function hasAlphaEntry($personId)
    {
        return Timesheet::where('person_id', $personId)
            ->whereYear('on_duty', current_year())
            ->where('position_id', Position::ALPHA)
            ->exists();
    }

    /**
     * Retrieve all the timesheet for the given people and position.
     *
     * @param array $personIds
     * @param int $positionId
     * @return Collection group by person_id and sub-grouped by year
     */

    public static function retrieveAllForPositionIds(array $personIds, int $positionId)
    {
        return self::whereIn('person_id', $personIds)
            ->where('position_id', $positionId)
            ->orderBy('on_duty')
            ->get()
            ->groupBy([
                'person_id',
                function ($row) {
                    return $row->on_duty->year;
                }
            ]);
    }

    public static function countUnverifiedForPersonYear(int $personId, int $year)
    {
        // Find all the unverified timesheets
        return Timesheet::where('person_id', $personId)
            ->whereYear('on_duty', $year)
            ->whereIn('review_status', [Timesheet::STATUS_UNVERIFIED, Timesheet::STATUS_APPROVED])
            ->whereNotNull('off_duty')
            ->count();
    }

    public static function retrieveCombinedCorrectionRequestsForYear($year)
    {
        $corrections = self::retrieveCorrectionRequestsForYear($year);

        $requests = [];
        foreach ($corrections as $req) {
            $requests[] = [
                'person'    => $req->person,
                'position'  => $req->position,
                'on_duty'   => (string)$req->on_duty,
                'off_duty'  => (string)$req->off_duty,
                'duration'  => $req->duration,
                'credits'   => $req->credits,
                'is_missing'=> false,
                'notes' => $req->notes
            ];
        }

        $missing = TimesheetMissing::retrieveForPersonOrAllForYear(null, $year);

        foreach ($missing as $req) {
            $requests[] = [
                'person'    => $req->person,
                'position'  => $req->position,
                'on_duty'   => (string)$req->on_duty,
                'off_duty'  => (string)$req->off_duty,
                'duration'  => $req->duration,
                'credits'   => $req->credits,
                'is_missing'=> true,
                'notes' => $req->notes
            ];
        }

        usort($requests, function ($a, $b) {
            return strcasecmp($a['person']->callsign, $b['person']->callsign);
        });

        return $requests;
    }

    /*
     * Retrieve all people who has not indicated their timesheet entries are correct.
     */

    public static function retrieveUnconfirmedPeopleForYear($year)
    {
        return DB::select(
                "SELECT person.id, callsign, first_name, last_name, email, home_phone,
                    (SELECT count(*) FROM timesheet
                        WHERE person.id=timesheet.person_id
                          AND YEAR(timesheet.on_duty)=?
                          AND timesheet.verified IS FALSE) as unverified_count
               FROM person
               LEFT JOIN person_event ON person_event.person_id=person.id AND person_event.year=?
               WHERE status in ('active', 'inactive', 'inactive extension', 'retired')
                 AND IFNULL(person_event.timesheet_confirmed, FALSE) != TRUE
                 AND EXISTS (SELECT 1 FROM timesheet WHERE timesheet.person_id=person.id AND YEAR(timesheet.on_duty)=?)
               ORDER BY callsign",
             [ $year, $year, $year ]
         );
    }

    /*
     * Retrieve folks who potentially earned a t-shirt
     */

    public static function retrievePotentialEarnedShirts($year, $thresholdSS, $thresholdLS)
    {
      $active_statuses = implode("','", Person::ACTIVE_STATUSES);
      $report = DB::select(
       "SELECT
          person.id, person.callsign, person.status, person.first_name, person.mi, person.last_name,
          eh.estimated_hours,
          ah.actual_hours,
          person.teeshirt_size_style, person.longsleeveshirt_size_style
        FROM
          person
        LEFT JOIN (
          SELECT
            person_slot.person_id,
            round(sum(((TIMESTAMPDIFF(MINUTE, slot.begins, slot.ends))/60)),2) AS estimated_hours
          FROM
            slot
          JOIN
            person_slot ON person_slot.slot_id = slot.id
          JOIN
            position ON position.id = slot.position_id
          WHERE
            YEAR(slot.begins) = ?
            AND position.count_hours IS TRUE
          GROUP BY person_id
        ) eh ON eh.person_id = person.id

        LEFT JOIN (
          SELECT
            timesheet.person_id,
            round(sum(((TIMESTAMPDIFF(MINUTE, timesheet.on_duty, timesheet.off_duty))/60)),2) AS actual_hours
          FROM
            timesheet
          JOIN
            position ON position.id = timesheet.position_id
          WHERE
            YEAR(timesheet.on_duty) = ?
            AND position.count_hours IS TRUE
          GROUP BY person_id
        ) ah ON ah.person_id = person.id

        WHERE
          ( actual_hours > 0 OR estimated_hours > 0 )
          AND person.id NOT IN (
            SELECT
              timesheet.person_id
            FROM
              timesheet
            JOIN
              position ON position.id = timesheet.position_id
            WHERE
              YEAR(timesheet.on_duty) = ?
              AND position_id = ?
          )
          AND person.status IN ('" . $active_statuses . "')
        ORDER BY
          person.callsign
        "
        , [$year, $year, $year, Position::ALPHA]
      );

      if (empty($report)) {
        return [];
      }

      $report = collect($report);
      return $report->map(function ($row) use ($thresholdSS, $thresholdLS) {
        return [
          'id'    => $row->id,
          'callsign'  => $row->callsign,
          'first_name' => $row->first_name,
          'middle_initial' => $row->mi,
          'last_name' => $row->last_name,
          'estimated_hours' => $row->estimated_hours,
          'actual_hours' => $row->actual_hours,
          'longsleeveshirt_size_style' => $row->longsleeveshirt_size_style,
          'earned_ls' => ($row->actual_hours >= $thresholdLS),
          'teeshirt_size_style' => $row->teeshirt_size_style,
          'earned_ss' => ($row->actual_hours >= $thresholdSS), // gonna be true always, but just in case the selection above changes.
        ];
      });
    }

    /*
     * Retrieve all timesheets for a given year, grouped by callsign
     */

    public static function retrieveAllForYearByCallsign($year)
    {
        $rows = self::whereYear('on_duty', $year)
                ->with([ 'person:id,callsign,status',
                          'position:id,title,active,type,count_hours' ])
                ->orderBy('on_duty')
                ->get();

        if (!$rows->isEmpty()) {
            PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));
        }

        $personGroups = $rows->groupBy('person_id');

        return $personGroups->map(function ($group) {
            $person = $group[0]->person;

            return [
                'id' => $group[0]->person_id,
                'callsign' => $person ? $person->callsign : "Person #".$group[0]->person_id,
                'status' => $person ? $person->status : 'deleted',

                'total_credits' => $group->pluck('credits')->sum(),
                'total_duration' => $group->pluck('duration')->sum(),
                'total_appreciation_duration' => $group->filter(function ($t) {
                    return $t->position ? $t->position->count_hours : false;
                })->pluck('duration')->sum(),

                'timesheet' => $group->map(function ($t) {
                    return [
                        'on_duty'   => (string) $t->on_duty,
                        'off_duty'  => (string) $t->off_duty,
                        'duration'  => $t->duration,
                        'credits'   => $t->credits,
                        'position'   => [
                            'id'     => $t->position_id,
                            'title'  => $t->position ? $t->position->title : "Position #".$t->position_id,
                            'active' => $t->position->active,
                            'count_hours' => $t->position ? $t->position->count_hours : 0,
                        ]
                    ];
                })->values()
            ];
        })->sortBy('callsign', SORT_NATURAL|SORT_FLAG_CASE)->values();
    }

    /*
     * Breakdown the positions within a given year
     */

    public static function retrieveByPosition($year, $includeEmail=false)
    {
        $rows = Timesheet::whereYear('on_duty', $year)
                ->with([ 'person:id,callsign,status,email', 'position:id,title,active' ])
                ->orderBy('on_duty')
                ->get()
                ->groupBy('position_id');

        $results = [];

        foreach ($rows as $positionId => $entries) {
            $position = $entries[0]->position;
            $results[] = [
                'id'     => $position->id,
                'title'  => $position->title,
                'active' => $position->active,
                'timesheets' => $entries->map(function($r) use ($includeEmail) {
                    $person = $r->person;
                    $personInfo = [
                        'id'    => $r->person_id,
                        'callsign' => $person ? $person->callsign : 'Person #'.$r->person_id,
                        'status' => $person ? $person->status : 'deleted'
                    ];

                    if ($includeEmail) {
                        $personInfo['email'] = $person ? $person->email : '';
                    }

                    return [
                        'id'       => $r->id,
                        'on_duty'  => (string) $r->on_duty,
                        'off_duty' => (string) $r->off_duty,
                        'duration' => $r->duration,
                        'person'   => $personInfo
                    ];
                })
            ];
        }

        usort($results, function ($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });

        return $results;
    }

    /*
    * Determine if the person has worked one or more positions in the last X years
    */

    public static function didPersonWorkPosition($personId, $years, $positionIds)
    {
        if (!is_array($positionIds)) {
            $positionIds = [$positionIds];
        }

        $cutoff = current_year() - $years;
        return DB::table('timesheet')
            ->where('person_id', $personId)
            ->whereYear('on_duty', '>=', $cutoff)
            ->whereIn('position_id', $positionIds)
            ->limit(1)
            ->exists();
    }

    /**
     * Did the given person work in a given year?
     *
     * @param int $personId
     * @param int $year
     * @return bool
     */
    public static function didPersonWork(int $personId, int $year): bool
    {
        return DB::table('timesheet')
            ->where('person_id', $personId)
            ->whereYear('on_duty', $year)
            ->whereNotIn('position_id', [Position::TRAINING, Position::ALPHA])
            ->limit(1)
            ->exists();
    }

    /*
     * Calculate how many credits earned for a year
     */

    public static function earnedCreditsForYear($personId, $year)
    {
        $rows = Timesheet::findForQuery(['person_id' => $personId, 'year' => $year]);
        if (!$rows->isEmpty()) {
            PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));
        }

        return $rows->pluck('credits')->sum();
    }

    public function log($action, $data=null) {
        TimesheetLog::record($action, $this->person_id, Auth::id(), $this->id, $data, $this->on_duty->year);
    }

    /*
    * Return the total seconds on duty.
    */

    public function getDurationAttribute()
    {
        $offDuty = $this->off_duty ?? now();
        return $offDuty->diffInSeconds($this->on_duty);
    }

    public function getPositionTitleAttribute()
    {
        return $this->attributes['position_title'] ?? '';
    }

    /*
     * Return the credits earned
     */

    public function getCreditsAttribute()
    {
        // Already computed?
        if (isset($this->attributes['credits'])) {
            return $this->attributes['credits'];
        }

        // Go forth and get the tasty credits!
        $credits = PositionCredit::computeCredits(
            $this->position_id,
            $this->on_duty->timestamp,
            ($this->off_duty ?? now())->timestamp,
            $this->on_duty->year
        );

        $this->attributes['credits'] = $credits;

        return $credits;
    }

    public function setOnDutyToNow()
    {
        $this->on_duty = now();
    }

    public function setOffDutyToNow()
    {
        $this->off_duty = now();
    }

    public function setVerifiedAtToNow()
    {
        $this->verified_at = now();
    }

    public function getPositionSubtypeAttribute()
    {
        return $this->position->subtype;
    }

    public function setAdditionalReviewerNotesAttribute($notes)
    {
        if (empty($notes)) {
            return;
        }

        $this->_additional_reviewer_notes = $notes;
        $user = Auth::user();
        $callsign = $user ? $user->callsign : '(unknown)';

        $date = date('Y/m/d H:m:s');
        $this->reviewer_notes = $this->reviewer_notes . "From $callsign on $date:\n$notes\n\n";
    }

    public function getAdditionalReviewerNotesAttribute()
    {
        return $this->_additional_reviewer_notes;
    }

    public function setAdditionalNotesAttribute($notes)
    {
        if (empty($notes)) {
            return;
        }

        $this->_additional_notes = $notes;
        $date = date('Y/m/d H:m:s');
        $this->notes = $this->notes . "$date:\n$notes\n\n";
    }

    public function getAdditionalNotesAttribute()
    {
        return $this->_additional_notes;
    }
}
