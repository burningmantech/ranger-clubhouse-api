<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * @property Carbon $begins
 * @property Carbon end
 * @property int $position_id
 * @property bool $active
 * @property string $description
 * @property int $max
 * @property int $min
 * @property int $signed_up
 * @property string $timezone
 * @property int|null $trainer_slot_id
 * @property string $url
 * @property-read float $credits
 * @property-read bool $has_started
 * @property-read bool $has_ended
 * @property-read int $duration
 * @property-read string $timezone_abbr
 */
class Slot extends ApiModel
{
    // related tables to be loaded with row
    const WITH_POSITION_TRAINER = [
        'position:id,title,type,contact_email,prevent_multiple_enrollments,alert_when_becomes_empty,alert_when_no_trainers',
        'trainer_slot',
        'trainer_slot.position:id,title'
    ];

    protected $table = 'slot';

    protected bool $auditModel = true;

    protected $with = self::WITH_POSITION_TRAINER;

    protected $fillable = [
        'active',
        'begins',
        'description',
        'ends',
        'max',
        'min',
        'position_id',
        'signed_up',
        'timezone',
        'trainer_slot_id',
        'url',
    ];

    // Computed from the other fields
    protected $guarded = [
        'begins_time',
        'begins_year',
        'duration',
        'ends_time',
        'timezone_abbr',
    ];

    protected $appends = [
        'credits',
        'has_started',
        'has_ended',
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

    protected $casts = [
        'ends' => 'datetime',
        'begins' => 'datetime',
    ];

    // Don't track changes to the signed up count.
    public array $auditExclude = [
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

    public function loadRelationships(): void
    {
        $this->load(self::WITH_POSITION_TRAINER);
    }

    public static function boot(): void
    {
        parent::boot();

        self::saving(function ($model) {
            // Compute the timezone abbreviation. Have to use the begins time because it could be PDT or PST.
            if (($model->isDirty('begins') || $model->isDirty('timezone')) && $model->begins) {
                $tz = $model->begins_adjusted->format('T');
                if (str_starts_with($tz, '+') || str_starts_with($tz, '-')) {
                    $tz = 'UTC' . $tz;
                }

                $model->timezone_abbr = $tz;
            }

            if ($model->isDirty('begins')) {
                $model->begins_year = $model->begins->year;
                $model->begins_time = $model->begins_adjusted->timestamp;
            }

            if ($model->isDirty('ends')) {
                $model->ends_time = $model->ends_adjusted->timestamp;
            }

            if ($model->isDirty('begins') || $model->isDirty('ends')) {
                $model->duration = $model->ends_time - $model->begins_time;
            }
        });
    }

    /**
     * Attempt to save the slot. Check for infinite recursion if the trainer slot is specified.
     * @param $options
     * @return bool
     */
    public function save($options = []): bool
    {
        if ($this->exists) {
            if ($this->isDirty('trainer_slot_id') && $this->trainer_slot_id) {
                if (DB::table('slot')->where('id', $this->trainer_slot_id)->where('trainer_slot_id', $this->id)->exists()) {
                    $this->addError('trainer_slot_id', 'This slot and the trainer slot cannot point to one another. On the trainer slot, the trainer multiplier slot should not be set.');
                    return false;
                }
            }

            if ($this->id == $this->trainer_slot_id) {
                $this->addError('trainer_slot_id', 'The trainer multiplier slot cannot be set to itself.');
                return false;
            }
        }

        return parent::save($options);
    }

    /**
     * Find slots based on the given criteria
     *
     * @param $query
     * @return \Illuminate\Database\Eloquent\Collection
     */

    public static function findForQuery($query): \Illuminate\Database\Eloquent\Collection
    {
        $sql = self::with(self::WITH_POSITION_TRAINER);

        $year = $query['year'] ?? null;
        $type = $query['type'] ?? null;
        $positionId = $query['position_id'] ?? null;
        $forRollcall = $query['for_rollcall'] ?? null;

        if ($year) {
            $sql->where('begins_year', $year);
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
     * Find all the slots with sign-ups for a given year.
     *
     * @param int $year
     * @return \Illuminate\Database\Eloquent\Collection
     */

    public static function findWithSignupsForYear(int $year): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('begins_year', $year)
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
                $row->photo_url = PersonPhoto::retrieveProfileUrlForPerson($row->id);
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
            ->where('begins_year', $year)
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
        return self::select('begins_year')
            ->groupBy('begins_year')
            ->pluck('begins_year')
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
        return self::where('begins_year', current_year())
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
            ->select('position_id', 'begins', 'ends', 'duration')
            ->where('begins_year', $year)
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
     * @throws InvalidArgumentException
     */

    public function getCreditsAttribute(): float
    {
        if ($this->position_id) {
            return PositionCredit::computeCredits($this->position_id, $this->begins_time, $this->ends_time, $this->begins_year);
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
     * Has the shift started?
     *
     * @return bool
     */

    public function getHasStartedAttribute(): bool
    {
        return $this->begins_adjusted?->lt(now()) ?? false;
    }

    /**
     * Has the slot ended?
     *
     * @return bool
     */

    public function getHasEndedAttribute(): bool
    {
        return $this->ends_adjusted?->lte(now()) ?? false;
    }

    /**
     * Set the timezone, default to Pacific timezone if blank.
     *
     * @return Attribute
     */

    public function timezone(): Attribute
    {
        return Attribute::make(
            set: fn($timezone) => empty($timezone) ? 'America/Los_Angeles' : $timezone
        );
    }

    /**
     * Adjust the timezone if one was given.
     *
     * @param ?Carbon $time
     * @return ?Carbon
     */

    public function adjustTimezone(?Carbon $time): ?Carbon
    {
        return $time?->clone()->shiftTimezone($this->timezone);
    }

    /**
     * How many seconds remaining in this shift?
     *
     * @return int
     */

    public function getRemainingAttribute(): int
    {
        return max(now()->timestamp - $this->ends_adjusted->timestamp, 0);
    }

    public function getBeginsAdjustedAttribute(): ?Carbon
    {
        return $this->adjustTimezone($this->begins);
    }

    public function getEndsAdjustedAttribute(): ?Carbon
    {
        return $this->adjustTimezone($this->ends);
    }


    /**
     * Check to see if the slot begins within the pre-event period and is not a training slot
     *
     * @return bool
     */

    public function isPreEventRestricted(): bool
    {
        if (empty($this->begins) || !$this->position_id) {
            // No begin time or position associated (might see this happen during validation)
            return false;
        }

        // Note: don't use begins_year since it may have not been set yet.
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
        return $this->position?->type == Position::TYPE_TRAINING;
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
     * @return string|null
     */

    public static function sessionGroupName(string $description): ?string
    {
        $matched = preg_match('/^(.*?)\s*-?\s*\bPart\s*\d\s*$/', $description, $matches);
        return $matched ? $matches[1] : null;
    }

}
