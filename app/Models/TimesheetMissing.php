<?php

namespace App\Models;

use Carbon\Carbon;

use App\Models\ApiModel;
use App\Models\Position;
use App\Models\Person;
use App\Models\Timesheet;

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
        'reviewer_notes',

        // Used for creating new entries when review_status == 'approved'
        'create_entry',
        'new_on_duty',
        'new_off_duty',
        'new_position_id'
    ];

    protected $casts = [
        'create_entry' => 'boolean'
    ];

    protected $dates = [
        'created_at',
        'reviewed_at',
        'on_duty',
        'off_duty',
        'new_on_duty',
        'new_off_duty',
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
        'person_id' => 'required|integer',

        'create_entry' => 'sometimes|boolean|nullable',

        'new_on_duty'     => 'date|nullable|required_if:create_entry,true',
        'new_off_duty'    => 'date|nullable|after:new_on_duty|required_if:create_entry,true',
        'new_position_id' => 'integer|nullable|required_if:create_entry,true'
    ];

    public $create_new;
    public $new_off_duty;
    public $new_on_duty;
    public $new_position_id;

    const PARTNER_SHIFT_STARTS_WITHIN = 30;

    const RELATIONSHIPS = [ 'position:id,title', 'reviewer_person:id,callsign' ];

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function reviewer_person()
    {
        return $this->belongsTo(Person::class);
    }

    public function create_person()
    {
        return $this->belongsTo(Person::class);
    }

    public function partner_person()
    {
        return $this->belongsTo(Person::class, 'partner', 'callsign');
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
                $this->on_duty->timestamp,
                $this->off_duty->timestamp,
                $this->on_duty->year);
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

        $people = preg_split("/(\band\b|\s*[&\+\|]\s*)/i", $this->partner);

        $partners = [];

        foreach ($people as $name) {
            $name = trim($name);
            $sql = Person::where('callsign', $name);
            if (strpos($name, ' ') !== false) {
                $sql = $sql->orWhere('callsign', str_replace(' ', '', $name));
            }

            $partner = $sql->get(['id', 'callsign'])->first();

            if (!$partner) {
                // Try metaphone lookup
                $metaphone = metaphone($name);
                $partner = Person::where('callsign_soundex', $metaphone)->get(['id', 'callsign'])->first();
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

    /*
     * Find the missing timesheet requests for a person OR all outstanding requests for a given year.
     *
     * Credits are calculated and the partner shift searched for.
     *
     * @param int $personId if null, find all request, otherwise find for person
     * @param int $year year to search
     * @return array found missing requests
     */

    public static function retrieveForPersonOrAllForYear($personId, $year)
    {
        $sql = self::
            with([
                'position:id,title',
                'person:id,callsign',
                'create_person:id,callsign',
                'reviewer_person:id,callsign',
                'partner_person:id,callsign'
            ])
            ->whereYear('on_duty', $year)
            ->orderBy('on_duty');

        // Find for a person
        if ($personId !== null) {
            $sql = $sql->where('person_id', $personId);
        } else {
            $sql = $sql->where('review_status', 'pending');
        }

        $rows = $sql->get();

        return $rows->sortBy(function ($p) {
            return $p->person->callsign;
        }, SORT_NATURAL|SORT_FLAG_CASE)->values();
    }

    public function setCreateEntryAttribute($value) {
        $this->create_entry = $value;
    }

    public function setNewOnDutyAttribute($value) {
        $this->new_on_duty = $value;
    }

    public function setNewOffDutyAttribute($value) {
        $this->new_off_duty = $value;
    }

    public function setNewPositionIdAttribute($value) {
        $this->new_position_id = $value;
    }

}
