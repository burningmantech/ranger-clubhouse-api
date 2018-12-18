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
            return PositionCredit::computeCredits(
                    $this->position_id,
                    $this->on_duty->timestamp,
                    $this->off_duty->timestamp,
                    $this->on_duty->year
            );
        } else {
            return 0.0;
        }
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
