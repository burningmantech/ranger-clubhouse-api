<?php

namespace App\Models;

use Carbon\Carbon;

use App\Models\ApiModel;
use App\Models\Position;
use App\Models\Person;

class TimesheetMissing extends ApiModel
{
    protected $table = "timesheet_missing";

    protected $fillable = [
        'notes',
        'off_duty',
        'on_duty',
        'partner',
        'person_id',
        'position_id',
        'review_status',
        'reviewer_notes'
    ];

    protected $dates = [
        'created_at',
        'reviewed_at',
        'on_duty',
        'off_duty'
    ];

    protected $appends = [
        'duration',
        'credits',
    ];

    protected $rules = [
        'notes'     => 'required|string',
        'on_duty'   => 'required|date',
        'off_duty'  => 'required|date|after:on_duty',
        'person_id' => 'required|integer'
    ];

    const RELATIONSHIPS = [ 'position:id,title', 'reviewer_person:id,callsign' ];

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function reviewer_person()
    {
        return $this->belongsTo(Person::class);
    }

    public static function findForQuery($query)
    {
        $sql = self::with(self::RELATIONSHIPS);

        if (isset($query['person_id'])) {
            $sql = $sql->where('person_id', $query['person_id']);
        }

        if (isset($query['year'])) {
            $sql = $sql->whereYear('on_duty', $query['year']);
        }

        return $sql->orderBy('on_duty', 'asc')->get();
    }

    public function loadRelationships() {
        return $this->load(self::RELATIONSHIPS);
    }

    public function getDurationAttribute()
    {
        $on_duty = $this->getOriginal('on_duty');
        $off_duty = $this->getOriginal('off_duty');

        return Carbon::parse($off_duty)->diffInSeconds(Carbon::parse($on_duty));
    }

    public function getCreditsAttribute() {
        return PositionCredit::computeCredits(
                $this->position_id,
                $this->getOriginal('on_duty'),
                $this->getOriginal('off_duty'));
    }

    public function setPartnerAttribute($value)
    {
        $this->attributes['partner'] = empty($value) ? '' : $value;
    }
}
