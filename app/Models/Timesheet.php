<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Helpers\SqlHelper;

use App\Models\ApiModel;
use App\Helpers\DateHelper;
use App\Models\Position;
use App\Models\Person;
use DB;

class Timesheet extends ApiModel
{
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    const EXCLUDE_POSITIONS_FOR_YEARS = [
      Position::ALPHA,
      Position::TRAINING,
    ];

    protected $table = 'timesheet';

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
    ];

    protected $rules = [
        'person_id' => 'required|integer',
        'position_id' => 'required|integer'
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
    ];

    public $credits;

    const RELATIONSHIPS = [ 'reviewer_person:id,callsign', 'verified_person:id,callsign', 'position:id,title' ];

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

    public function loadRelationships()
    {
        return $this->load(self::RELATIONSHIPS);
    }

    public static function findForQuery($query)
    {
        $year = 0;
        $sql = self::select('timesheet.*', DB::raw('TIMESTAMPDIFF(SECOND, on_duty, IFNULL(off_duty,now())) as duration'))
            ->with(self::RELATIONSHIPS);

        $year = $query['year'] ?? null;
        $personId = $query['person_id'] ?? null;
        $onDuty = $query['on_duty'] ?? false;
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

        $rows = $sql->orderBy('on_duty', 'asc', 'off_duty', 'asc')->get();

        if (!$personId) {
            $rows = $rows->sortBy('person.callsign')->values();
        }

        return $rows;
    }

    public static function isPersonOnDuty($personId)
    {
        return self::where('person_id', $personId)
                ->whereYear('on_duty', current_year())
                ->whereNull('off_duty')
                ->exists();
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
     * Retrieve all corrections requests for a given year
     */

    public static function retrieveCorrectionRequestsForYear($year)
    {
        // Find all the unverified timesheets
        $rows = self::with([ 'person:id,callsign', 'position:id,title'])
            ->whereYear('on_duty', $year)
            ->where('verified', false)
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

    /*
     * Retrieve all people who has not indicated their timesheet entries are correct.
     */

    public static function retrieveUnconfirmedPeopleForYear($year)
    {
        return DB::select(
                "SELECT
                    person.id, callsign,
                    first_name, last_name,
                    email, home_phone,
                    (SELECT count(*) FROM timesheet WHERE person.id=timesheet.person_id AND YEAR(timesheet.on_duty)=? AND timesheet.verified IS FALSE AND (timesheet.notes is null OR timesheet.notes='') AND timesheet.review_status='pending') as unverified_count
               FROM person
               WHERE status='active'
                 AND timesheet_confirmed IS FALSE
                 AND EXISTS (SELECT 1 FROM timesheet WHERE timesheet.person_id=person.id AND YEAR(timesheet.on_duty)=?)
               ORDER BY callsign",
             [ $year, $year ]
         );
    }

    /*
     * Retrieve folks who earned a t-shirt
     */


    public static function retrieveEarnedShirts($year, $thresholdSS, $thresholdLS)
    {
        $hoursEarned = DB::select("SELECT person_id, SUM(TIMESTAMPDIFF(second, on_duty,off_duty)) as seconds FROM timesheet WHERE YEAR(off_duty)=? AND position_id != ? GROUP BY person_id HAVING (SUM(TIMESTAMPDIFF(second, on_duty,off_duty))/3600) >= ?", [ $year, Position::ALPHA, $thresholdSS ]);
        if (empty($hoursEarned)) {
            return [];
        }

        $hoursEarned = collect($hoursEarned);
        $personIds = $hoursEarned->pluck('person_id');
        $hoursByPerson = $hoursEarned->keyBy('person_id');

        $people = Person::select('id', 'callsign', 'status', 'first_name', 'last_name', 'longsleeveshirt_size_style', 'teeshirt_size_style')
                ->whereIn('id', $personIds)
                ->where('status', 'active')
                ->where('user_authorized', true)
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
                return strcmp($a['callsign'], $b['callsign']);
            } else {
                return $b['years'] - $a['years'];
            }
        });

        return collect($freaks)->groupBy('years')->map(function ($people, $year) {
            return [ 'years' => $year, 'people' => $people ];
        })->values();
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
        $people = array_values(array_filter($people, function($p) {
            return $p->hours_prev_year || $p->hours_last_year || $p->shift_lead;
        }));

        foreach ($people as $person) {
            // Normalized the hours - no timesheets found in a given years will result in null
            if (!$person->hours_last_year) {
                $person->hours_last_year = 0.0;
            }
            $person->hours_last_year = round($person->hours_last_year);

            if (!$person->hours_prev_year) {
                $person->hours_prev_year = 0.0;
            }
            $person->hours_prev_year = round($person->hours_prev_year);

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


    public function getDurationAttribute()
    {
        // Did a select already compute this?
        if (isset($this->attributes['duration'])) {
            return (int)$this->attributes['duration'];
        }

        $on_duty = $this->getOriginal('on_duty');

        if ($this->off_duty) {
            return $this->off_duty->diffInSeconds($this->on_duty);
        }

        return Carbon::parse(SqlHelper::now())->diffInSeconds($this->on_duty);
    }

    public function getPositionTitleAttribute()
    {
        return $this->attributes['position_title'];
    }

    public function getCreditsAttribute()
    {
        if ($this->off_duty) {
            $offDuty = $this->off_duty;
        } else {
            $offDuty = SqlHelper::now();
        }

        return PositionCredit::computeCredits(
            $this->position_id,
            $this->on_duty->timestamp,
            $offDuty->timestamp,
            $this->on_duty->year
        );
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
