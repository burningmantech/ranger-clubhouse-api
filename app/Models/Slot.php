<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Slot extends ApiModel
{
    // related tables to be loaded with row
    const WITH_POSITION_TRAINER = [
        'position:id,title,type,contact_email,prevent_multiple_enrollments,alert_when_empty',
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

    public function trainer_slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class, 'trainer_slot_id');
    }

    public function person_slot(): HasMany
    {
        return $this->hasMany(PersonSlot::class);
    }

    public function trainer_status(): HasMany
    {
        return $this->hasMany(TrainerStatus::class, 'trainer_slot_id');
    }

    public function trainee_statuses(): HasMany
    {
        return $this->hasMany(TraineeStatus::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function loadRelationships()
    {
        $this->load(self::WITH_POSITION_TRAINER);
    }

    /**
     * Override Eloquent find method to add associations always desired
     *
     * @param $id
     * @param string[] $columns
     * @return mixed
     */

    public static function find($id, $columns = ['*']): mixed
    {
        if (is_array($id)) {
            return self::baseSql()->whereIntegerInRaw('id', $id)->get($columns);
        }
        return self::baseSql()->where('id', $id)->first($columns);
    }

    /**
     * Override Eloquent findOrFail method to add associations always desired
     *
     * @param $id
     * @param string[] $columns
     * @return mixed
     */

    public static function findOrFail($id, $columns = ['*']) : mixed
    {
        if (is_array($id)) {
            $rows = self::baseSql()->whereIntegerInRaw('id', $id)->get($columns);
            if ($rows->isEmpty()) {
                throw (new ModelNotFoundException)->setModel(__CLASS__, $id);
            }
            return $rows;
        }
        return self::baseSql()->where('id', $id)->firstOrFail($columns);
    }

    /**
     * Find slots based on the given criteria
     *
     * @param $query
     * @return \Illuminate\Database\Eloquent\Collection
     */

    public static function findForQuery($query): \Illuminate\Database\Eloquent\Collection
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
        }

        if ($forRollcall) {
            $sql->whereRaw('begins < DATE_ADD(?, INTERVAL 2 HOUR)', [now()]);
            $sql->whereRaw('begins > DATE_SUB(?, INTERVAL 4 HOUR)', [now()]);
        }

        return $sql->orderBy('begins')->get();
    }

    /**
     * Add various select columns to calculate if the shift is started and/or ended, the duration
     * in seconds and the associated trainer position (if training slot)
     *
     * @return Builder
     */

    public static function baseSql(): Builder
    {
        $now = now();
        return self::select(
            'slot.*',
            DB::raw('IF(slot.begins < ?, TRUE, FALSE) as has_started'),
            DB::raw('IF(slot.ends < ?, TRUE, FALSE) as has_ended'),
            DB::raw('TIMESTAMPDIFF(SECOND, slot.begins, slot.ends) as duration')
        )->setBindings([$now, $now])->with(self::WITH_POSITION_TRAINER);
    }

    /**
     * Find all the slots with sign-ups for a given year.
     *
     * @param int $year
     * @return \Illuminate\Database\Eloquent\Collection
     */

    public static function findWithSignupsForYear(int $year): \Illuminate\Database\Eloquent\Collection
    {
        return self::whereYear('begins', $year)
            ->where('signed_up', '>', 0)
            ->with('position:id,title')
            ->orderBy('begins')
            ->get();
    }

    /**
     * Find all the signed up folks for a given slot.
     *
     * @param int $slotId
     * @param bool $includeOnDuty
     * @param bool $includePhoto
     * @return Collection
     */

    public static function findSignUps(int $slotId, bool $includeOnDuty = false, bool $includePhoto = false): Collection
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
        $entries = Timesheet::whereIntegerInRaw('person_id', $ids)
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

    /**
     * Find all the signs up for a given person, position, and year.
     *
     * @param $personId
     * @param $positionId
     * @param $year
     * @return ?Slot
     */

    public static function findFirstSignUp($personId, $positionId, $year): ?Slot
    {
        return self::join('person_slot', function ($q) use ($personId) {
            $q->on('person_slot.slot_id', 'slot.id');
            $q->where('person_slot.person_id', $personId);
        })->where('position_id', $positionId)
            ->where('slot.active', true)
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

    public static function findYears(): array
    {
        return self::selectRaw('YEAR(begins) as year')
            ->groupBy(DB::raw('YEAR(begins)'))
            ->pluck('year')
            ->toArray();
    }

    /**
     * Check to see if an activated slot exists for a given position in the
     * current year.
     *
     * @param int $positionId
     * @return bool
     */

    public static function haveActiveForPosition(int $positionId): bool
    {
        return self::whereYear('begins', current_year())
            ->where('position_id', $positionId)
            ->where('active', true)
            ->exists();
    }

    /**
     * Retrieve all the dirt shifts for a given year
     *
     * @param int $year
     * @return Collection
     */

    public static function retrieveDirtTimes(int $year): Collection
    {
        $rows = DB::table('slot')
            ->select('position_id', 'begins', 'ends', DB::raw('timestampdiff(second, begins, ends) as duration'))
            ->whereYear('begins', $year)
            ->whereIn('position_id', [Position::DIRT, Position::DIRT_PRE_EVENT, Position::DIRT_POST_EVENT])
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

    /**
     * Retrieve the credits potential for the slot
     *
     * @return float
     */

    public function getCreditsAttribute(): float
    {
        if ($this->position_id) {
            return PositionCredit::computeCredits($this->position_id, $this->begins->timestamp, $this->ends->timestamp, $this->begins->year);
        }

        return 0.0;
    }

    /**
     * Retrieve the position title for the slot.
     *
     * @return string
     */

    public function getPositionTitleAttribute(): string
    {
        return $this->position ? $this->position->title : "Deleted #{$this->position_id}";
    }

    /**
     * Is this slot an ART training slot?
     *
     * @return bool
     */

    public function isArt(): bool
    {
        return ($this->position_id != Position::TRAINING);
    }

    /**
     * Humanized the begins datetime  - for sending emails
     *
     * @return string
     */

    public function getBeginsHumanFormatAttribute(): string
    {
        return $this->begins->format('l M d Y @ H:i');
    }

    /**
     * Humanized the ends datetime  - for sending emails
     *
     * @return string
     */

    public function getEndsHumanFormatAttribute(): string
    {
        return $this->ends->format('l M d Y @ H:i');
    }

    /**
     * Check to see if the slot begins within the pre-event period and is not a training slot
     *
     * @return bool
     */

    public function isPreEventRestricted(): bool
    {
        if (!$this->begins || !$this->position_id) {
            // No begin time or position associated (might see this happen during validation)
            return false;
        }

        $eventDate = EventDate::findForYear($this->begins->year);

        if (!$eventDate || !$eventDate->pre_event_slot_start || !$eventDate->pre_event_slot_end) {
            // Event dates not set
            return false;
        }

        if ($this->begins->lt($eventDate->pre_event_slot_start) || $this->begins->gte($eventDate->pre_event_slot_end)) {
            // Huzzah! Outside of Pre-Event period
            return false;
        }

        return !$this->isTraining();
    }

    /**
     * Is this a training slot? (either trainer or trainee)
     *
     * @return bool
     */

    public function isTraining(): bool
    {
        return $this->position?->type == "Training";
    }

    /**
     * Is the slot part of a session group?
     *
     * @param $ourDescription
     * @param $theirDescription
     * @return bool
     */

    public static function isPartOfSessionGroup($ourDescription, $theirDescription): bool
    {
        return (self::sessionGroupPart($ourDescription)
            && self::sessionGroupPart($theirDescription)
            && self::sessionGroupName($ourDescription) == self::sessionGroupName($theirDescription));
    }

    /**
     * Find and return the session part number if it exists.
     *
     * @param $description
     * @return int
     */

    public static function sessionGroupPart($description): int
    {
        $matched = preg_match('/\bPart (\d)\b/i', $description, $matches);
        return $matched ? (int)$matches[1] : 0;
    }

    /**
     * Grab the session name minus any "- Part N" suffix.
     *
     * "Pre-Event - Part 1" becomes "Pre-Event"
     *
     * @param string $description
     * @return mixed|null
     */

    public static function sessionGroupName(string $description): mixed
    {
        $matched = preg_match('/^(.*?)\s*-?\s*\bPart\s*\d\s*$/', $description, $matches);
        return $matched ? $matches[1] : null;
    }
}
