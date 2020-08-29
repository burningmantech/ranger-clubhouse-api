<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;
use App\Helpers\SqlHelper;

use App\Lib\WorkSummary;

use App\Helpers\DateHelper;

use App\Models\ApiModel;
use App\Models\Person;
use App\Models\Position;
use App\Models\PositionCredits;
use App\Models\Slot;


class Timesheet extends ApiModel
{
    protected $table = 'timesheet';
    protected $auditModel = true;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    const EXCLUDE_POSITIONS_FOR_YEARS = [
      Position::ALPHA,
      Position::TRAINING,
    ];

    protected $fillable = [
        'notes',
        'off_duty',
        'on_duty',
        'person_id',
        'position_id',
        'review_status',
        'reviewed_at',
        'reviewer_notes',
        'reviewer_person_id',
        'timesheet_confirmed_at',
        'timesheet_confirmed',
        'verified',
        'slot_id'
    ];

    protected $rules = [
        'person_id' => 'required|integer',
        'position_id' => 'required|integer',
        'off_duty'    => 'nullable|sometimes|after_or_equal:on_duty'
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

    protected $casts = [
        'verified' => 'boolean',
        'is_incorrect' => 'boolean'
    ];

    const RELATIONSHIPS = [ 'reviewer_person:id,callsign', 'verified_person:id,callsign', 'position:id,title,count_hours' ];

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

    public static function find($id)
    {
        return self::selectBase()->where('id', $id)->first();
    }

    public static function findOrFail($id)
    {
        return self::selectBase()->where('id', $id)->firstOrFail();
    }

    public static function selectBase()
    {
        return self::select('timesheet.*', DB::raw('TIMESTAMPDIFF(SECOND, on_duty, IFNULL(off_duty,now())) as duration'))
            ->with(self::RELATIONSHIPS);
    }

    public static function findForQuery($query)
    {
        $year = 0;
        $sql = self::selectBase();

        $year = $query['year'] ?? null;
        $personId = $query['person_id'] ?? null;
        $onDuty = $query['on_duty'] ?? false;
        $dutyDate = $query['duty_date'] ?? null;
        $overHours = $query['over_hours'] ?? 0;

        if ($year) {
            $sql->whereYear('on_duty', $year);
        }

        if ($personId) {
            $sql->where('person_id', $personId);
        } else {
            $sql->with('person:id,callsign');
        }

        if ($onDuty) {
            $sql->whereNull('off_duty');
            if ($overHours) {
                $sql->whereRaw("TIMESTAMPDIFF(HOUR, on_duty, now()) >= ?", [ $overHours ]);
            }
        }

        if ($dutyDate) {
            $sql->where('on_duty', '<=', $dutyDate);
            $sql->whereRaw('IFNULL(off_duty, NOW()) >= ?', [ $dutyDate ]);
        }

        $rows = $sql->orderBy('on_duty', 'asc', 'off_duty', 'asc')->get();

        if (!$personId) {
            $rows = $rows->sortBy('person.callsign', SORT_NATURAL|SORT_FLAG_CASE)->values();
        }

        return $rows;
    }

    public static function findPersonOnDuty($personId)
    {
        return self::where('person_id', $personId)
                ->whereYear('on_duty', current_year())
                ->whereNull('off_duty')
                ->with([ 'position:id,title' ])
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
            ->with('position:id,title')
            ->first();
    }

    /*
     * Find an existing overlapping timesheet entry for a date range
     */

    public static function findOverlapForPerson($personId, $onduty, $offduty)
    {
        return self::where('person_id', $personId)
            ->where(function ($sql) use ($onduty, $offduty) {
                $sql->whereBetween('on_duty', [ $onduty, $offduty ]);
                $sql->orWhereBetween('off_duty', [ $onduty, $offduty ]);
                $sql->orWhereRaw('? BETWEEN on_duty AND off_duty', [ $onduty ]);
                $sql->orWhereRaw('? BETWEEN on_duty AND off_duty', [ $offduty ]);
            })->first();
    }

    public static function findShiftWithinMinutes($personId, $startTime, $withinMinutes)
    {
        return self::with([ 'position:id,title' ])
            ->where('person_id', $personId)
            ->whereRaw(
                'on_duty BETWEEN DATE_SUB(?, INTERVAL ? MINUTE) AND DATE_ADD(?, INTERVAL ? MINUTE)',
                [ $startTime, $withinMinutes, $startTime, $withinMinutes]
            )->first();
    }


    /*
     * Find the years a person was on working
     *
     * @param integer $everything if true include all scheduled years as well
     */

    public static function years($personId, $everything=false)
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

        $rows = DB::select("SELECT person_id, COUNT(year) as years FROM (SELECT YEAR(on_duty) as year, person_id FROM timesheet WHERE person_id in (".implode(',', $ids).") AND  position_id not in (1, 13, 29, 30) GROUP BY person_id,year ORDER BY year) as rangers group by person_id");


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

    /*
     * Retrieve all corrections requests for a given year
     */

    public static function retrieveCorrectionRequestsForYear($year)
    {
        // Find all the unverified timesheets
        $rows = self::with([ 'person:id,callsign', 'position:id,title'])
            ->whereYear('on_duty', $year)
            ->where('verified', false)
            ->where('notes', '!=', '')
            ->where('review_status', 'pending')
            ->whereNotNull('off_duty')
            ->orderBy('on_duty')
            ->get();

        // Warm up the position credit cache so the database is not being slammed.
        PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));

        return $rows->sortBy(function ($p) {
            return $p->person->callsign;
        }, SORT_NATURAL|SORT_FLAG_CASE)->values();
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
     * Retrieve folks who earned a t-shirt
     */


    public static function retrieveEarnedShirts($year, $thresholdSS, $thresholdLS)
    {
        $hoursEarned = DB::select(
        "SELECT person_id, SUM(TIMESTAMPDIFF(second, on_duty,off_duty)) as seconds FROM timesheet JOIN position ON position.id=timesheet.position_id WHERE YEAR(off_duty)=? AND position.count_hours IS TRUE AND position_id != ? GROUP BY person_id HAVING (SUM(TIMESTAMPDIFF(second, on_duty,off_duty))/3600) >= ?", [ $year, Position::ALPHA, $thresholdSS ]);
        if (empty($hoursEarned)) {
            return [];
        }

        $hoursEarned = collect($hoursEarned);
        $personIds = $hoursEarned->pluck('person_id');
        $hoursByPerson = $hoursEarned->keyBy('person_id');

        $people = Person::select('id', 'callsign', 'status', 'first_name', 'last_name', 'longsleeveshirt_size_style', 'teeshirt_size_style')
                ->whereIn('id', $personIds)
                ->where('status', Person::ACTIVE)
                ->orderBy('callsign')
                ->get();

        return $people->map(function ($person) use ($thresholdSS, $thresholdLS, $hoursByPerson) {
            $hours  = $hoursByPerson[$person->id]->seconds / 3600.00;

            return [
                'id'    => $person->id,
                'callsign'  => $person->callsign,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'status'    => $person->status,
                'email'     => $person->email,
                'longsleeveshirt_size_style' => $person->longsleeveshirt_size_style,
                'earned_ls' => ($hours >= $thresholdLS),
                'teeshirt_size_style' => $person->teeshirt_size_style,
                'earned_ss' => ($hours >= $thresholdSS), // gonna be true always, but just in case the selection above changes.
                'hours'   => round($hours, 2),
            ];
        });
    }

    /*
     * Retrieve folks who potentially earned a t-shirt
     */

    public static function retrievePotentialEarnedShirts($year, $thresholdSS, $thresholdLS)
    {
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
					( actual_hours >= ? OR estimated_hours >= ? )
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
				ORDER BY
					person.callsign
        "
				, [$year, $year, $thresholdSS, $thresholdSS, $year, Position::ALPHA]
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
     * Build a Freanking Years report - how long a person has rangered, the first year rangered, the last year to ranger,
     * and if the person intends to ranger in the intended year (usually the current year)
     */

    public static function retrieveFreakingYears($showAll=false, $intendToWorkYear)
    {
        $excludePositionIds = implode(',', [ Position::ALPHA, Position::HQ_RUNNER ]);
        $statusCond = $showAll ? '' : 'person.status="active" AND ';

        $rows = DB::select(
                'SELECT E.person_id, sum(year) AS years, '.
                "(SELECT YEAR(on_duty) FROM timesheet ts WHERE ts.person_id=E.person_id AND YEAR(ts.on_duty) > 0 GROUP BY YEAR(ts.on_duty) ORDER BY YEAR(ts.on_duty) ASC LIMIT 1) AS first_year, ".
                "(SELECT YEAR(on_duty) FROM timesheet ts WHERE ts.person_id=E.person_id AND YEAR(ts.on_duty) > 0 GROUP BY YEAR(ts.on_duty) ORDER BY YEAR(ts.on_duty) DESC LIMIT 1) AS last_year, ".
                "EXISTS (SELECT 1 FROM person_slot JOIN slot ON slot.id=person_slot.slot_id AND YEAR(slot.begins)=$intendToWorkYear WHERE person_slot.person_id=E.person_id LIMIT 1) AS signed_up ".
               'FROM (SELECT person.id as person_id, COUNT(DISTINCT(YEAR(on_duty))) AS year FROM ' .
               "person, timesheet WHERE $statusCond person.id = person_id ".
               "AND position_id  NOT IN ($excludePositionIds)".
               'GROUP BY person.id, YEAR(on_duty)) AS E ' .
               'GROUP BY E.person_id'
        );
        if (empty($rows)) {
            return [];
        }

        $personIds = array_column($rows, 'person_id');
        $people = Person::select('id', 'callsign', 'first_name', 'last_name', 'status')
                ->whereIn('id', $personIds)
                ->get()
                ->keyBy('id');

        $freaks = array_map(function ($row) use ($people) {
            $person = $people[$row->person_id];
            return [
                'id'         => $row->person_id,
                'callsign'   => $person->callsign,
                'status'     => $person->status,
                'first_name' => $person->first_name,
                'last_name'  => $person->last_name,
                'years'      => (int) $row->years,
                'first_year' => (int) $row->first_year,
                'last_year'  => (int) $row->last_year,
                'signed_up'  => (int) $row->signed_up,
            ];
        }, $rows);

        usort($freaks, function ($a, $b) {
            if ($a['years'] == $b['years']) {
                return strcasecmp($a['callsign'], $b['callsign']);
            } else {
                return $b['years'] - $a['years'];
            }
        });

        return collect($freaks)->groupBy('years')->map(function ($people, $year) {
            return [ 'years' => $year, 'people' => $people ];
        })->values();
    }

    /**
     *
     * Retrieve the top N hours worked within a given year range.
     */

    public static function retrieveTopHourEarners($startYear, $endYear, $topLimit)
    {
        // Find all eligible candidates
        $people = Person::select('id', 'callsign', 'status', 'email')
                    ->whereIn('status', [ Person::ACTIVE, Person::INACTIVE ])
                    ->get();

        $cadidates = collect([]);

        foreach ($people as $person) {
            for ($year = $endYear; $year >= $startYear; $year = $year - 1) {
                // Walk backward thru time and find the most recent year worked.
                $seconds = self::join('position', 'timesheet.position_id', 'position.id')
                        ->where('person_id', $person->id)
                        ->whereYear('on_duty', $year)
                        ->where('position.count_hours', true)
                        ->get()
                        ->sum('duration');
                if ($seconds > 0) {
                    // Hey found a candidate
                    $cadidates[] = (object) [
                        'person'    => $person,
                        'seconds'   => $seconds,
                        'year'      => $year
                    ];
                    break;
                }
            }
        }

        $cadidates = $cadidates->sortByDesc('seconds')->splice(0, $topLimit);

        return $cadidates->map(function ($c) {
            $person = $c->person;
            return [
                'id'       => $person->id,
                'callsign' => $person->callsign,
                'status'   => $person->status,
                'email'    => $person->email,
                'hours'    => round($c->seconds / 3600.0, 2),
                'year'     => $c->year,
            ];
        });
    }

    /*
     * Radio Eligibility
     *
      * Takes the current year, and figures out:
      * - How many hours worked (excluding Alpha & Training shifts) in the previous two years
      * - If the person is signed up to work in the current year.
     */
    public static function retrieveRadioEligilibity($currentYear)
    {
        $lastYear = $currentYear-1;
        $prevYear = $currentYear-2;

        $people = DB::select("SELECT person.id, person.callsign,
                (SELECT SUM(TIMESTAMPDIFF(second, on_duty, off_duty))/3600.0 FROM timesheet WHERE person.id=timesheet.person_id AND year(on_duty)=$lastYear AND position_id NOT IN (1,13)) as hours_last_year,
                (SELECT SUM(TIMESTAMPDIFF(second, on_duty, off_duty))/3600.0 FROM timesheet WHERE person.id=timesheet.person_id AND year(on_duty)=$prevYear AND position_id NOT IN (1,13)) as hours_prev_year,
                EXISTS (SELECT 1 FROM person_position WHERE person_position.person_id=person.id AND person_position.position_id IN (10,12) LIMIT 1) AS shift_lead,
                EXISTS (SELECT 1 FROM person_slot JOIN slot ON person_slot.slot_id=slot.id AND YEAR(slot.begins)=$currentYear AND slot.begins >= '$currentYear-08-15 00:00:00' AND position_id NOT IN (1,13) WHERE person_slot.person_id=person.id LIMIT 1) as signed_up
                FROM person WHERE person.status IN ('active', 'inactive', 'inactive extension', 'retired')
                ORDER by callsign");

        // Person must have worked in one of the previous two years, or is a shift lead
        $people = array_values(array_filter($people, function ($p) {
            return $p->hours_prev_year || $p->hours_last_year || $p->shift_lead;
        }));

        foreach ($people as $person) {
            // Normalized the hours - no timesheets found in a given years will result in null
            if (!$person->hours_last_year) {
                $person->hours_last_year = 0.0;
            }
            $person->hours_last_year = round($person->hours_last_year, 2);

            if (!$person->hours_prev_year) {
                $person->hours_prev_year = 0.0;
            }
            $person->hours_prev_year = round($person->hours_prev_year, 2);

            // Qualified radio hours is last year, OR the previous year if last year
            // was less than 10 hours and the previous year was greater than last year.
            $person->radio_hours = $person->hours_last_year;
            if ($person->hours_last_year < 10.0 && ($person->hours_prev_year > $person->hours_last_year)) {
                $person->radio_hours = $person->hours_prev_year;
            }
        }

        return $people;
    }

    /*
     * Retrieve time worked on special teams
     */

    public static function retrieveSpecialTeamsWork($positionIds, $startYear, $endYear, $includeInactive, $viewEmail = false)
    {
        $sql = DB::table('person')
            ->select(
                    'person.id as person_id',
                    DB::raw('IFNULL(person_position.position_id, timesheet.position_id) as position_id'),
                    DB::raw('YEAR(timesheet.on_duty) as year'),
                    DB::raw('SUM( TIMESTAMPDIFF( SECOND, timesheet.on_duty, timesheet.off_duty ) ) AS duration')
                )
            ->leftJoin('person_position', function ($q) use ($positionIds) {
                $q->on('person_position.person_id', 'person.id');
                $q->whereIn('person_position.position_id', $positionIds);
            })
            ->leftJoin('timesheet', function ($q) use ($startYear, $endYear, $positionIds) {
                $q->on('timesheet.person_id', 'person.id');
                $q->whereYear('on_duty', '>=', $startYear);
                $q->whereYear('on_duty', '<=', $endYear);
                $q->whereIn('timesheet.position_id', $positionIds);
            })
            ->where(function ($q) {
                $q->whereNotNull('timesheet.id');
                $q->orWhereNotNull('person_position.position_id');
            })
            ->groupBy('person.id')
            ->groupBy(DB::raw('IFNULL(person_position.position_id, timesheet.position_id)'))
            ->groupBy('year')
            ->orderBy('person.id')
            ->orderBy('year');

        if (!$includeInactive) {
            $sql->whereNotNull('timesheet.id');
        }

        $rows = $sql->get();
        $peopleByIds = Person::whereIn('id', $rows->pluck('person_id')->unique())->get()->keyBy('id');
        $rows = $rows->groupBy('person_id');

        $results = [];

        foreach ($rows as $personId => $worked) {
            $timeByYear = $worked->keyBy('year');

            $person = $peopleByIds[$personId];

            $years = [];
            $totalDuration = 0;
            for ($year = $startYear; $year <= $endYear; $year++) {
                $duration = (int)($timeByYear->has($year) ? $timeByYear[$year]->duration : 0);
                $years[] = $duration;
                $totalDuration += $duration;
            }

            $result = [
                'id'         => $person->id,
                'callsign'   => $person->callsign,
                'first_name' => $person->first_name,
                'last_name'  => $person->last_name,
                'status'     => $person->status,
                'years'      => $years,
                'total_duration' => $totalDuration
            ];

            if ($viewEmail) {
                $result['email'] = $person->email;
            }

            $results[] = $result;
        }

        usort($results, function ($a, $b) {
            return strcasecmp($a['callsign'], $b['callsign']);
        });

        return $results;
    }

    /*
     * Retrieve all timesheets for a given year, grouped by callsign
     */

    public static function retrieveAllForYearByCallsign($year)
    {
        $rows = self::whereYear('on_duty', $year)
                ->with([ 'person:id,callsign,status', 'position:id,title,count_hours' ])
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
                            'id'    => $t->position_id,
                            'title' => $t->position ? $t->position->title : "Position #".$t->position_id,
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
                ->with([ 'person:id,callsign,status,email', 'position:id,title' ])
                ->orderBy('on_duty')
                ->get()
                ->groupBy('position_id');

        $results = [];

        foreach ($rows as $positionId => $entries) {
            $position = $entries[0]->position;
            $results[] = [
                'id'    => $position->id,
                'title' => $position->title,
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
            $positionIds = [ $positionIds ];
        }

        $cutoff = current_year() - $years;
        return DB::table('timesheet')
            ->where('person_id', $personId)
            ->whereYear('on_duty', '>=', $cutoff)
            ->whereIn('position_id', $positionIds)
            ->limit(1)
            ->exists();
    }

    /*
     * Calcuate how many credits earned for a year
     */

    public static function earnedCreditsForYear($personId, $year)
    {
        $rows = Timesheet::findForQuery([ 'person_id' => $personId, 'year' => $year]);
        if (!$rows->isEmpty()) {
            PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));
        }

        return $rows->pluck('credits')->sum();
    }

    public static function workSummaryForPersonYear($personId, $year)
    {
        $rows = Timesheet::findForQuery([ 'person_id' => $personId, 'year' => $year]);

        $eventDates = EventDate::findForYear($year);

        if (!$rows->isEmpty()) {
            PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));
        }

        if (!$eventDates) {
            // No event dates - return everything as happening during the event
            $time = $rows->pluck('duration')->sum();
            $credits = $rows->pluck('credits')->sum();

            return [
                'pre_event_duration'  => 0,
                'pre_event_credits'   => 0,
                'event_duration'      => $time,
                'event_credits'       => $credits,
                'post_event_duration' => 0,
                'post_event_credits'  => 0,
                'other_duration'      => 0,
                'counted_duration'    => 0,
                'total_duration'      => $time,
                'total_credits'       => $credits,
                'no_event_dates'      => true,
            ];
        }

        $summary = new WorkSummary($eventDates->event_start->timestamp, $eventDates->event_end->timestamp, $year);

        foreach ($rows as $row) {
            $summary->computeTotals(
                    $row->position_id,
                    $row->on_duty->timestamp,
                    ($row->off_duty ?? SqlHelper::now())->timestamp,
                    $row->position->count_hours
                );
        }

        return [
            'pre_event_duration'  => $summary->pre_event_duration,
            'pre_event_credits'   => $summary->pre_event_credits,
            'event_duration'      => $summary->event_duration,
            'event_credits'       => $summary->event_credits,
            'post_event_duration' => $summary->post_event_duration,
            'post_event_credits'  => $summary->post_event_credits,
            'total_duration'      => ($summary->pre_event_duration + $summary->event_duration + $summary->post_event_duration + $summary->other_duration),
            'total_credits'       => ($summary->pre_event_credits + $summary->event_credits + $summary->post_event_credits),
            'other_duration'      => $summary->other_duration,
            'counted_duration'    => ($summary->pre_event_duration + $summary->event_duration + $summary->post_event_duration),
            'event_start'         => (string) $eventDates->event_start,
            'event_end'           => (string) $eventDates->event_end,
        ];
    }

    public static function retrieveHoursCredits($year)
    {
        $eventDates = EventDate::findForYear($year);

        if (!$eventDates) {
            return [
                'event_start' => '',
                'event_end'   => '',
                'people'      => []
            ];
        }

        $people = Person::whereNotIn('status', [ 'alpha', 'auditor', 'bonked', 'past prospective', 'prospective' ])
            ->whereRaw('EXISTS (SELECT 1 FROM timesheet WHERE timesheet.person_id=person.id AND YEAR(on_duty)=? LIMIT 1)', [ $year ])
            ->orderBy('callsign')
            ->get();

        if ($people->isEmpty()) {
            return [
                'event_start' => (string) $eventDates->event_start,
                'event_end'   => (string) $eventDates->event_end,
                'people'      => []
            ];
        }


        $personIds = $people->pluck('id');
        $yearsByIds = self::yearsRangeredCountForIds($personIds);

        PositionCredit::warmYearCache($year, []);

        $entriesByPerson = self::whereIn('person_id', $personIds)
            ->whereYear('on_duty', $year)
            ->with([ 'position:id,count_hours' ])
            ->get()
            ->groupBy('person_id');

        $results = [];
        $now = SqlHelper::now()->timestamp;

        foreach ($people as $person) {
            $entries = $entriesByPerson[$person->id] ?? null;
            if (!$entries) {
                continue;
            }

            $summary = new WorkSummary($eventDates->event_start->timestamp, $eventDates->event_end->timestamp, $year);
            foreach ($entries as $entry) {
                $summary->computeTotals(
                        $entry->position_id,
                         $entry->on_duty->timestamp,
                         $entry->off_duty ? $entry->off_duty->timestamp :  $now,
                         $entry->position->count_hours
                );
            }

            $results[] = [
                'id'                  => $person->id,
                'callsign'            => $person->callsign,
                'status'              => $person->status,
                'first_name'          => $person->first_name,
                'last_name'           => $person->last_name,
                'email'               => $person->email,
                'years'               => $yearsByIds[$person->id] ?? 0,
                'pre_event_duration'  => $summary->pre_event_duration,
                'pre_event_credits'   => $summary->pre_event_credits,
                'event_duration'      => $summary->event_duration,
                'event_credits'       => $summary->event_credits,
                'post_event_duration' => $summary->post_event_duration,
                'post_event_credits'  => $summary->post_event_credits,
                'total_duration'      => ($summary->pre_event_duration + $summary->event_duration + $summary->post_event_duration + $summary->other_duration),
                'total_credits'       => ($summary->pre_event_credits + $summary->event_credits + $summary->post_event_credits),
                'other_duration'      => $summary->other_duration,
                'counted_duration'    => ($summary->pre_event_duration + $summary->event_duration + $summary->post_event_duration),
            ];
        }

        return [
            'event_start' => (string) $eventDates->event_start,
            'event_end'   => (string) $eventDates->event_end,
            'people'      => $results
        ];
    }

    /*
     * Run through the timesheets in a given year, and sniff for problematic entries.
     */

    public static function sanityChecker($year)
    {
        $withBase = [ 'position:id,title', 'person:id,callsign' ];

        $rows = self::whereYear('on_duty', $year)
                ->whereNull('off_duty')
                ->with($withBase)
                ->get()
                ->sortBy('person.callsign')
                ->values();

        $onDutyEntries = $rows->map(function ($row) {
            return [
                'person'    => [
                    'id'    => $row->person_id,
                    'callsign'  => $row->person ? $row->person->callsign : 'Person #'.$row->person_id,
                ],
                'callsign'  => $row->person ? $row->person->callsign : 'Person #'.$row->person_id,
                'on_duty'   => (string) $row->on_duty,
                'duration'  => $row->duration,
                'credits'   => $row->credits,
                'position'  => [
                    'id'    => $row->position_id,
                    'title' => $row->position ? $row->position->title : 'Position #'.$row->position_id,
                ]
            ];
        })->values();

        $rows = self::whereYear('on_duty', $year)
                ->whereRaw('on_duty > off_duty')
                ->whereNotNull('off_duty')
                ->with($withBase)
                ->get()
                ->sortBy('person.callsign')
                ->values();

        /*
         * Do any entries have the end time before the start time?
         * (should never happen..famous last words)
         */

        $endBeforeStartEntries = $rows->map(function ($row) {
            return [
                'person'    => [
                    'id'    => $row->person_id,
                    'callsign'  => $row->person ? $row->person->callsign : 'Person #'.$row->person_id,
                ],
                'on_duty'   => (string) $row->on_duty,
                'off_duty'  => (string) $row->off_duty,
                'duration'  => $row->duration,
                'position'  => [
                    'id'    => $row->position_id,
                    'title' => $row->position ? $row->position->title : 'Position #'.$row->position_id,
                ]
            ];
        });

        /*
         * Look for overlapping entries
         */

        $people = self::whereYear('on_duty', $year)
                ->whereNotNull('off_duty')
                ->with($withBase)
                ->orderBy('person_id')
                ->orderBy('on_duty')
                ->get()
                ->groupBy('person_id');

        $overlappingPeople = [];
        foreach ($people as $personId => $entries) {
            $overlapping = [];

            $prevEntry = null;
            foreach ($entries as $entry) {
                if ($prevEntry) {
                    if ($entry->on_duty->timestamp < ($prevEntry->on_duty->timestamp + $prevEntry->duration)) {
                        $overlapping[] = [
                            [
                                'timesheet_id'  => $prevEntry->id,
                                'position' => [
                                    'id'    => $prevEntry->position_id,
                                    'title' => $prevEntry->position ? $prevEntry->position->title : 'Position #'.$prevEntry->position_id,
                                ],
                                'on_duty'   => (string) $prevEntry->on_duty,
                                'off_duty'  => (string) $prevEntry->off_duty,
                                'duration'  => $prevEntry->duration,
                            ],
                            [
                                'timesheet_id'  => $entry->id,
                                'position' => [
                                    'id'    => $entry->position_id,
                                    'title' => $entry->position ? $entry->position->title : 'Position #'.$entry->position_id,
                                ],
                                'on_duty'   => (string) $entry->on_duty,
                                'off_duty'  => (string) $entry->off_duty,
                                'duration'  => $entry->duration,
                            ]
                        ];
                    }
                }
                $prevEntry = $entry;
            }

            if (!empty($overlapping)) {
                $first = $entries[0];
                $overlappingPeople[] = [
                    'person'    => [
                        'id'    => $first->person_id,
                        'callsign' => $first->person ? $first->person->callsign : 'Person #'.$first->person_id
                    ],
                    'entries' => $overlapping
                ];
            }
        }

        usort($overlappingPeople, function ($a, $b) {
            return strcasecmp($a['person']['callsign'], $b['person']['callsign']);
        });

        $minHour = 24;
        foreach (Position::PROBLEM_HOURS as $positionId => $hours) {
            if ($hours < $minHour) {
                $minHour = $hours;
            }
        }

        $rows = self:: select('timesheet.*', DB::raw('TIMESTAMPDIFF(SECOND, on_duty, IFNULL(off_duty,now())) as duration'))
                ->whereYear('on_duty', $year)
                ->whereRaw("TIMESTAMPDIFF(HOUR, on_duty, IFNULL(off_duty,now())) >= $minHour")
                ->with($withBase)
                ->orderBy('duration', 'desc')
                ->get();

        /*
         * Look for entries that may be too long. (i.e. a person forgot to signout in a timely manner.)
         */

        $tooLongEntries = $rows->filter(function ($row) {
            if (!isset(Position::PROBLEM_HOURS[$row->position_id])) {
                return true;
            }

            return Position::PROBLEM_HOURS[$row->position_id] < ($row->duration / 3600.0);
        })->values()->map(function ($row) {
            return [
                'person'    => [
                    'id'       => $row->person_id,
                    'callsign' => $row->person ? $row->person->callsign : 'Person #'.$row->person_id,
                ],
                'on_duty'   => (string) $row->on_duty,
                'off_duty'  => (string) $row->off_duty,
                'duration'  => $row->duration,
                'position'  => [
                    'id'    => $row->position_id,
                    'title' => $row->position ? $row->position->title : 'Position #'.$row->position_id,
                ]
            ];
        });

        return [
            'on_duty'          => $onDutyEntries,
            'end_before_start' => $endBeforeStartEntries,
            'overlapping'      => $overlappingPeople,
            'too_long'         => $tooLongEntries
        ];
    }

    public static function retrievePeopleToThank($year)
    {
        $people = Person::whereNotIn('status', [ Person::ALPHA, Person::AUDITOR, Person::BONKED, Person::PAST_PROSPECTIVE, Person::PROSPECTIVE, Person::SUSPENDED, Person::UBERBONKED ])
            ->whereRaw('EXISTS (SELECT 1 FROM timesheet WHERE timesheet.person_id=person.id AND YEAR(on_duty)=? LIMIT 1)', [ $year ])
            ->orderBy('callsign')
            ->get();

        return $people->map(function ($row) {
            return [
                'id'         => $row->id,
                'first_name' => $row->first_name,
                'last_name'  => $row->last_name,
                'callsign'   => $row->callsign,
                'status'     => $row->status,
                'email'      => $row->email,
                'bpguid'     => $row->bpguid,
                'street1'    => $row->street1,
                'street2'    => $row->street2,
                'city'       => $row->city,
                'state'      => $row->state,
                'zip'        => $row->zip,
                'country'    => $row->country,
            ];
        })->values();
    }

    /*
     * Find everyone who worked in a given year, and summarize the positions (total time & credits)
     */

    public static function retrieveTimesheetTotals($year)
    {
        $rows = Timesheet::whereYear('on_duty', $year)
            ->join('position', 'position.id', 'timesheet.position_id')
            ->where('position.count_hours', true)
            ->with([ 'person:id,callsign,status', 'position:id,title'])
            ->orderBy('timesheet.person_id')
            ->get();

        if (!$rows->isEmpty()) {
            PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));
        }

        $timesheetByPerson = $rows->groupBy('person_id');

        $results = [];

        foreach ($timesheetByPerson as $personId => $entries) {
            $person = $entries[0]->person;

            $group = $entries->groupBy('position_id');
            $positions = [];
            $totalDuration = 0;
            $totalCredits = 0.0;

            // Summarize the positions worked
            foreach ($group as $positionId => $posEntries) {
                $position = $posEntries[0];
                $duration = $posEntries->pluck('duration')->sum();
                $totalDuration += $duration;
                $credits = $posEntries->pluck('credits')->sum();
                $totalCredits += $credits;
                $positions[] = [
                    'id'       => $positionId,
                    'title'    => $position->title,
                    'duration' => $duration,
                    'credits'  => $credits,
                ];
            }

            // Sort by position title
            usort($positions, function ($a,$b) {
                return strcasecmp($a['title'], $b['title']);
            });

            $results[] = [
                'id'        => $personId,
                'callsign'  => $person ? $person->callsign : 'Person #'.$personId,
                'status'    => $person->status,
                'positions' => $positions,
                'total_duration' => $totalDuration,
                'total_credits'  => $totalCredits,
            ];
        }

        usort($results, function ($a, $b) {
            return strcasecmp($a['callsign'], $b['callsign']);
        });

        return $results;
    }

    /*
     * Return the total seconds on duty.
     */

    public function getDurationAttribute()
    {
        // Did a SQL SELECT already compute this?
        if (isset($this->attributes['duration'])) {
            return (int)$this->attributes['duration'];
        }

        $on_duty = $this->getOriginal('on_duty');
        if ($this->off_duty) {
            return $this->off_duty->diffInSeconds($this->on_duty);
        }

        // Still on duty - return how many seconds have elasped so far
        return Carbon::parse(SqlHelper::now())->diffInSeconds($this->on_duty);
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
            ($this->off_duty ?? SqlHelper::now())->timestamp,
            $this->on_duty->year
        );

        $this->attributes['credits'] = $credits;

        return $credits;
    }

    public function setOnDutyToNow()
    {
        $this->on_duty = SqlHelper::now();
    }

    public function setOffDutyToNow()
    {
        $this->off_duty = SqlHelper::now();
    }

    public function setVerifiedAtToNow()
    {
        $this->verified_at = SqlHelper::now();
    }
}
