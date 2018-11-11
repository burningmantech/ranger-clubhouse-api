<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Helpers\SqlHelper;

use App\Models\ApiModel;
use App\Helpers\DateHelper;
use App\Models\Position;
use App\Models\Person;

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

    /*
     * Find the years a person was on working
     * @var integer $id person to lookup
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
     * Calcuate how many credits earned for a year
     */

    public static function earnedCreditsForYear($personId, $year)
    {
        return Timesheet::findForQuery([ 'person_id' => $personId, 'year' => $year])->pluck('credits')->sum();
    }


    public function getDurationAttribute()
    {
        $on_duty = $this->getOriginal('on_duty');
        $off_duty = $this->getOriginal('off_duty');

        if ($on_duty) {
            if ($off_duty) {
                return Carbon::parse($off_duty)->diffInSeconds(Carbon::parse($on_duty));
            }

            return Carbon::now()->diffInSeconds(Carbon::parse($on_duty));
        } else {
            return 0;
        }
    }

    public function getPositionTitleAttribute() {
        return $this->attributes['position_title'];
    }

    public function getCreditsAttribute() {
        if ($this->off_duty) {
            return PositionCredit::computeCredits(
                    $this->position_id,
                    $this->getOriginal('on_duty'),
                    $this->getOriginal('off_duty'));
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
