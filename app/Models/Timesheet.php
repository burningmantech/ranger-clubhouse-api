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

    public function loadRelationships() {
        return $this->load(self::RELATIONSHIPS);
    }

    public static function findForQuery($query)
    {
        $year = 0;
        $sql = self::with(self::RELATIONSHIPS);

        if (isset($query['year'])) {
            $year = $query['year'];
            $sql = $sql->whereYear('on_duty', $year);
        }

        if (isset($query['person_id'])) {
            $sql = $sql->where('person_id', $query['person_id']);
        }

        return $sql->orderBy('on_duty', 'asc', 'off_duty', 'asc')->get();
    }

    public static function isPersonOnDuty($personId)
    {
        return self::where('person_id', $personId)->whereNull('off_duty')->exists();
    }

    public static function findShiftWithinMinutes($personId, $startTime, $withinMinutes)
    {

        return self::with([ 'position:id,title' ])
            ->where('person_id', $personId)
            ->whereRaw('on_duty BETWEEN DATE_SUB(?, INTERVAL ? MINUTE) AND DATE_ADD(?, INTERVAL ? MINUTE)',
                [ $startTime, $withinMinutes, $startTime, $withinMinutes]
            )->first();
    }


    /*
     * Find the years a person was on working
     */

    public static function yearsRangered($personId, $everything=false)
    {
        $query = self::selectRaw("YEAR(on_duty) as year")
                ->where('person_id', $personId)
                ->groupBy("year")
                ->orderBy("year", "asc");

        if (!$everything) {
            $query = $query->whereNotIn("position_id", self::EXCLUDE_POSITIONS_FOR_YEARS);
        }

        return $query->pluck('year')->toArray();
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

        return $rows->sortBy(function ($p) { return $p->person->callsign; }, SORT_NATURAL|SORT_FLAG_CASE)->values();
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
               ORDER BY callsign", [ $year, $year ]);
     }

    /*
     * Retrieve folks who earned a t-shirt
     */


    public static function retrieveEarnedTShirts($year, $thresholdSS, $thresholdLS)
    {
        $hoursEarned = DB::select("SELECT person_id, SUM(TIMESTAMPDIFF(second, on_duty,off_duty)) as seconds FROM timesheet WHERE YEAR(off_duty)=? AND position_id != ? GROUP BY person_id HAVING (SUM(TIMESTAMPDIFF(second, on_duty,off_duty))/3600) >= ?", [ $year, Position::ALPHA, $thresholdSS ]);
        if (empty($hoursEarned)) {
            return [];
        }

        $hoursEarned = collect($hoursEarned);
        $personIds = $hoursEarned->pluck('person_id');
        $hoursByPerson = $hoursEarned->keyBy('person_id');

        $people = Person::select('id', 'callsign', 'status', 'first_name', 'last_name', 'longsleeveshirt_size_style', 'teeshirt_size_style')
                ->whereIn('id',  $personIds)
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
                'earned_ss' => ($hours >= $thresholdSS ), // gonna be true always, but just in case the selection above changes.
                'hours'   => round($hours, 2),
            ];
        });
    }

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
               'GROUP BY E.person_id');
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
        $on_duty = $this->getOriginal('on_duty');

        if ($this->off_duty) {
            return $this->off_duty->diffInSeconds($this->on_duty);
        }

        return Carbon::parse(SqlHelper::now())->diffInSeconds($this->on_duty);
    }

    public function getPositionTitleAttribute() {
        return $this->attributes['position_title'];
    }

    public function getCreditsAttribute() {
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

    public function setVerifiedAtToNow() {
        $this->verified_at = SqlHelper::now();
    }
}
