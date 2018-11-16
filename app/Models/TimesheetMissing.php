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
        'partner_info',
    ];

    protected $rules = [
        'notes'     => 'required|string',
        'on_duty'   => 'required|date',
        'off_duty'  => 'required|date|after:on_duty',
        'person_id' => 'required|integer'
    ];

    const PARTNER_SHIFT_STARTS_WITHIN = 30;

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

    /*
     * Figure out who the partners are and what their shifts were that started within
     * a certain period of on_duty.
     *
     * Null is returned if partner is empty or matches 'na', 'n/a', 'none' or 'no partner'.
     *
     * If partner name contains ampersands, commas, or the word 'and', the name will be split
     * up and multiple searches run.
     *
     * For each name found will contain the following:
     *
     *  callsign, person_id (if callsign found)
     *    ... and the shift was found..
     *  on_duty, off_duty, position_id, position_title
     *
     */
    public function getPartnerInfoAttribute() {
        $name = preg_quote($this->partner, '/');
        if (empty($this->partner) || preg_grep("/^\s*{$name}\s*$/i", [ 'na', 'n/a', 'no partner', 'none'])) {
            return null;
        }

        $people = preg_split("/(\band\b|\s*(&|,)\s*)/i", $this->partner);

        $partners = [];

        foreach ($people as $name) {
            $name = trim($name);
            $sql = Person::where('callsign', $name);
            if (strpos($name, ' ') !== false) {
                $sql = $sql->orWhere('callsign', str_replace(' ', '', $name));
            }

            $partner = $sql->get(['id', 'callsign'])->first();

            if (!$partner) {
                // Try soundex lookup
                $partner = Person::whereRaw('SOUNDEX(callsign)=SOUNDEX(?)', [ $name ])->get(['id', 'callsign'])->first();
                if (!$partner) {
                    $partners[] = [ 'callsign' => $name ];
                    continue;
                }
            }

            $partnerShift = Timesheet::findShiftWithinMinutes($partner->id, $this->on_duty, self::PARTNER_SHIFT_STARTS_WITHIN);
            if ($partnerShift) {
                $info = [
                    'timesheet_id'   => $partnerShift->id,
                    'position_title' => $partnerShift->position->title,
                    'position_id'    => $partnerShift->position_id,
                    'on_duty'        => (string)$partnerShift->on_duty,
                    'off_duty'       => (string)$partnerShift->off_duty
                ];
            } else {
                $info = [];
            }

            $info['callsign'] = $partner->callsign;
            $info['person_id'] = $partner->id;
            $info['name'] = $name;

            $partners[] = $info;
        }
        return $partners;
    }
}
