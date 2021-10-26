<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class Slot extends ApiModel
{
    const WITH_POSITION_TRAINER = [
        'position:id,title,type,contact_email,prevent_multiple_enrollments',
        'trainer_slot:id,position_id,description,begins,ends',
        'trainer_slot.position:id,title'
    ];
    protected $table = 'slot';
    protected $auditModel = true;
    protected $fillable = [
        'active',
        'begins',
        'description',
        'ends',
        'max',
        'min',
        'position_id',
        'signed_up',
        'trainer_slot_id',
        'url',
        'begins_time',
        'ends_time',
        'has_started',
        'has_ended'
    ];

    protected $appends = [
        'credits'
    ];

    // related tables to be loaded with row
    protected $rules = [
        'begins' => 'required|date|before:ends',
        'description' => 'required|string|max:40',
        'url' => 'sometimes|string|max:512|nullable',
        'ends' => 'required|date|after:begins',
        'max' => 'required|integer',
        'position_id' => 'required|integer',
        'trainer_slot_id' => 'nullable|integer'
    ];
    protected $dates = [
        'ends',
        'begins',
    ];

    // Don't track changes to the signed up count.
    public $auditExclude = [
      'signed_up'
    ];

    public static function findForQuery($query)
    {
        $sql = self::baseSql();
        $year = $query['year'] ?? null;
        $type = $query['type'] ?? null;
        $positionId = $query['position_id'] ?? null;
        $forRollcall = $query['for_rollcall'] ?? null;

        if ($year) {
            $sql->whereYear('begins', $year);
        }

        if ($type) {
            $sql->where('type', $type);
        }

        if ($positionId) {
            $sql->where('position_id', $positionId);
            // if GPE
             $sql->where('position_id', '>=', '500');
        }

        if ($forRollcall) {
            $sql->whereRaw('begins < DATE_ADD(?, INTERVAL 2 HOUR)', [now()]);
            $sql->whereRaw('begins > DATE_SUB(?, INTERVAL 4 HOUR)', [now()]);
        }

        return $sql->orderBy('begins')->get();
    }

    public static function baseSql()
    {
        $now = now();
        return self::select(
            'slot.*',
            DB::raw('IF(slot.begins < ?, TRUE, FALSE) as has_started'),
            DB::raw('IF(slot.ends < ?, TRUE, FALSE) as has_ended'),
            DB::raw('TIMESTAMPDIFF(SECOND, slot.begins, slot.ends) as duration')
        )->setBindings([$now, $now])->with(self::WITH_POSITION_TRAINER);
    }

    public static function find($slotId)
    {
        if (is_array($slotId)) {
            return self::baseSql()->whereIn('id', $slotId)->get();
        }
        return self::baseSql()->where('id', $slotId)->first();
    }

    public static function findOrFail($slotId)
    {
        if (is_array($slotId)) {
            $rows = self::baseSql()->whereIn('id', $slotId)->get();
            if ($rows->isEmpty()) {
                throw (new ModelNotFoundException)->setModel(__CLASS__, $slotId);
            }
            return $rows;
        }
        return self::baseSql()->where('id', $slotId)->firstOrFail();
    }

    public static function findWithSignupsForYear($year)
    {
        return self::whereYear('begins', $year)
            ->where('signed_up', '>', 0)
            ->with('position:id,title')
            ->orderBy('begins')
            ->get();
    }

    public static function findSignUps($slotId, $includeOnDuty = false, $includePhoto = false)
    {
        $rows = DB::table('person_slot')
            ->select('person.id', 'person.callsign')
            ->join('person', 'person.id', '=', 'person_slot.person_id')
            ->where('person_slot.slot_id', $slotId)
            ->orderBy('person.callsign', 'asc')
            ->get();

        if (!$includeOnDuty || $rows->isEmpty()) {
            return $rows;
        }

        $ids = $rows->pluck('id');
        $entries = Timesheet::whereIn('person_id', $ids)
            ->whereNull('off_duty')
            ->with('position:id,title')
            ->get();

        $byPerson = $rows->keyBy('id');
        foreach ($entries as $entry) {
            $byPerson[$entry->person_id]->on_duty = [
                'id' => $entry->id,
                'position' => ['id' => $entry->position->id, 'title' => $entry->position->title],
                'on_duty' => (string)$entry->on_duty,
                'duration' => $entry->duration,
            ];
        }

        if ($includePhoto) {
            foreach ($rows as $row) {
                $row->photo_url = PersonPhoto::retrieveImageUrlForPerson($row->id);
            }

        }

        return $rows;
    }

    public static function findFirstSignUp($personId, $positionId, $year)
    {
        return self::join('person_slot', function ($q) use ($personId) {
            $q->on('person_slot.slot_id', 'slot.id');
            $q->where('person_slot.person_id', $personId);
        })->where('position_id', $positionId)
            ->whereYear('begins', $year)
            ->where('slot.position_id', $positionId)
            ->orderBy('begins')
            ->first();
    }

    /**
     * Find all the years we have slots for
     *
     * @return array list of years
     */

    public static function findYears()
    {
        return self::selectRaw('YEAR(begins) as year')
            ->groupBy(DB::raw('YEAR(begins)'))
            ->pluck('year')->toArray();
    }

    /**
     * Check to see if an activated slot exists for a given position in the
     * current year.
     *
     * @param integer $positionId Position to find
     * @param bool true if a slot was found.
     */

    public static function haveActiveForPosition($positionId)
    {
        return self::whereYear('begins', current_year())
            ->where('position_id', $positionId)
            ->where('active', true)
            ->exists();
    }

    public static function retrieveDirtTimes($year)
    {
        $rows = DB::table('slot')
            ->select('position_id', 'begins', 'ends', DB::raw('timestampdiff(second, begins, ends) as duration'))
            ->whereYear('begins', $year)
            ->whereIn('position_id', [
                Position::DIRT, Position::DIRT_PRE_EVENT, Position::DIRT_POST_EVENT,
                Position::ONE_GERLACH_PATROL_DIRT
            ])
            ->orderBy('begins')
            ->get();

        foreach ($rows as $row) {
            $start = new Carbon($row->begins);

            if ($start->timestamp % 3600) {
                // If it's not on an hour boundary, bump it by 15 minutes
                $start->addMinutes(15);
                $row->duration -= 30 * 60;
            }

            $row->shift_start = (string)$start;
        }

        return $rows;
    }

    public static function isPartOfSessionGroup($ourDescription, $theirDescription)
    {
        return (self::sessionGroupPart($ourDescription)
            && self::sessionGroupPart($theirDescription)
            && self::sessionGroupName($ourDescription) == self::sessionGroupName($theirDescription));
    }

    public static function sessionGroupPart($description)
    {
        $matched = preg_match('/\bPart (\d)\b/i', $description, $matches);

        if (!$matched) {
            return 0;
        } else {
            return (int)$matches[1];
        }
    }

    public static function sessionGroupName($description)
    {
        $matched = preg_match('/^(.*?)\s*-?\s*\bPart\s*\d\s*$/', $description, $matches);
        return $matched ? $matches[1] : null;
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function trainer_slot()
    {
        return $this->belongsTo(Slot::class, 'trainer_slot_id');
    }

    public function person_slot()
    {
        return $this->hasMany(PersonSlot::class);
    }

    public function trainer_status()
    {
        return $this->hasMany(TrainerStatus::class, 'trainer_slot_id');
    }

    public function trainee_statuses()
    {
        return $this->hasMany(TraineeStatus::class);
    }

    public function getPositionTitleAttribute()
    {
        return $this->position ? $this->position->title : "Position #{$this->position_id}";
    }

    public function loadRelationships()
    {
        $this->load(self::WITH_POSITION_TRAINER);
    }

    /*
     * Humanized datetime formats - for sending emails
     */

    public function getCreditsAttribute(): float
    {
        if ($this->position_id ?? null) {
            return PositionCredit::computeCredits($this->position_id, $this->begins->timestamp, $this->ends->timestamp, $this->begins->year);
        }
        return 0.0;
    }

    public function isArt(): bool
    {
        return ($this->position_id != Position::TRAINING);
    }

    /*
     * Check to see if the slot begins within the pre-event period and
     * is not a training slot
     */

    public function getBeginsHumanFormatAttribute(): string
    {
        return $this->begins->format('l M d Y @ H:i');
    }

    /*
     * Find and return the session part number if it exists.
     */

    public function getEndsHumanFormatAttribute(): string
    {
        return $this->ends->format('l M d Y @ H:i');
    }

    /*
     * Grab the session name minus any "- Part N" suffix.
     *
     * "Pre-Event - Part 1" becomes "Pre-Event"
     */

    public function isPreEventRestricted(): bool
    {
        if (!$this->begins || !$this->position_id) {
            return false;
        }

        $eventDate = EventDate::findForYear($this->begins->year);

        if (!$eventDate || !$eventDate->pre_event_slot_start || !$eventDate->pre_event_slot_end) {
            return false;
        }

        if ($this->begins->lt($eventDate->pre_event_slot_start) || $this->begins->gte($eventDate->pre_event_slot_end)) {
            // Outside of Pre-Event period
            return false;
        }

        return !$this->isTraining();
    }

    /*
     * Is the slot part of a session group?
     */

    public function isTraining(): bool
    {
        $position = $this->position;
        if ($position == null) {
            return false;
        }

        return $position->type == "Training" && stripos($position->title, "trainer") === false;
    }
}
