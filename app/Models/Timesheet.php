<?php

namespace App\Models;

use App\Attributes\NullIfEmptyAttribute;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * @property ?Carbon $off_duty
 * @property ?Carbon $timesheet_confirmed_at
 * @property ?Carbon $verified_at
 * @property ?int $reviewer_person_id
 * @property ?int $slot_id
 * @property ?int $verified_person_id
 * @property ?string $notes
 * @property Carbon $on_duty
 * @property Carbon $reviewed_at
 * @property bool $is_non_ranger
 * @property bool $timesheet_confirmed
 * @property int $person_id
 * @property int $position_id
 * @property string $review_status
 * @property-read int $duration
 * @property-read float $credits
 * @property-read ?Position $position
 * @property-read ?Slot $slot
 * @property-read ?Person $reviewer_person
 * @property-read ?Person $verified_person
 * @property bool $suppress_duration_warning
 */
class Timesheet extends ApiModel
{
    protected $table = 'timesheet';
    protected bool $auditModel = true;
    public array $auditExclude = [
        'credits',
        'duration'
    ];

    const string STATUS_PENDING = 'pending';
    const string STATUS_APPROVED = 'approved';
    const string STATUS_REJECTED = 'rejected';
    const string STATUS_VERIFIED = 'verified';
    const string STATUS_UNVERIFIED = 'unverified';

    // type for findYears()
    const string YEARS_SEEN = 'seen';           // All years the person worked and/or had signups
    const string YEARS_COMBINED = 'combined';   // All the years worked as both a ranger and contributor
    const string YEARS_AS_RANGER = 'as-ranger'; // All the years worked as a ranger
    const string YEARS_AS_CONTRIBUTOR = 'as-contributor'; // All the years worked as a contributor / non-ranger

    const array EXCLUDE_POSITIONS_FOR_YEARS = [
        Position::ALPHA,
        Position::TRAINING,
    ];

    const int TOO_SHORT_LENGTH = (15 * 60);

    protected $fillable = [
        'desired_position_id',
        'desired_off_duty',
        'desired_on_duty',
        'is_non_ranger',
        'off_duty',
        'on_duty',
        'person_id',
        'position_id',
        'review_status',
        'reviewed_at',
        'reviewer_person_id',
        'slot_id',
        'suppress_duration_warning',
        'timesheet_confirmed',
        'timesheet_confirmed_at',

        // Pseudo fields used to create timesheet notes of various types
        'additional_notes',
        'additional_wrangler_notes',
        'additional_admin_notes',
    ];

    protected $rules = [
        'person_id' => 'required|integer',
        'position_id' => 'required|integer',
        'on_duty' => 'required|date',
        'off_duty' => 'nullable|sometimes|date|gte:on_duty',
        'desired_position_id' => 'sometimes|nullable|integer|exists:position,id',
        'desired_on_duty' => 'sometimes|nullable|date',
        'desired_off_duty' => 'sometimes|nullable|date',
    ];

    protected $appends = [
        'duration',
        'credits',
    ];

    protected function casts(): array
    {
        return [
            'desired_off_duty' => 'datetime',
            'desired_on_duty' => 'datetime',
            'off_duty' => 'datetime',
            'on_duty' => 'datetime',
            'reviewed_at' => 'datetime',
            'suppress_duration_warning' => 'boolean',
            'timesheet_confirmed_at' => 'datetime',
            'verified_at' => 'datetime',
            'was_signin_forced' => 'boolean',
        ];
    }

    protected $virtualColumns = [
        'additional_admin_notes',
        'additional_notes',
        'additional_wrangler_notes',
        'credits',
        'duration',
        'photo_url',
        'position_title',
        'time_warnings',
        'desired_warnings',
    ];

    const array RELATIONSHIPS = [
        'notes',
        'notes.create_person:id,callsign',
        'reviewer_person:id,callsign',
        'verified_person:id,callsign',
        'position:id,title,count_hours,paycode,no_payroll_hours_adjustment',
        'desired_position:id,title',
        'slot'
    ];

    const array ADMIN_NOTES_RELATIONSHIPS = [
        'admin_notes',
        'admin_notes.create_person:id,callsign'
    ];

    public ?string $photo_url = null;
    public ?array $time_warnings = null;
    public ?array $desired_warnings = null;

    public ?string $additionalNotes = null;
    public ?string $additionalAdminNotes = null;
    public ?string $additionalWranglerNotes = null;

    public static function boot(): void
    {
        parent::boot();

        /*
         * When a timesheet entry is about to be created, mark the entry as a 'non ranger'
         * entry if the person is a Non Ranger. (i.e. volunteer working for the Rangers but is
         * not actual Ranger)
         */

        self::creating(function (Timesheet $model) {
            if ($model->person?->status == Person::NON_RANGER) {
                $model->is_non_ranger = true;
            }
        });

        /*
         * Associate the timesheet entry with a slot. Preference is given to a shift sign up, followed by
         * a matching shift in the full schedule.
         */

        self::saving(function (Timesheet $model) {
            if (!$model->isDirty('slot_id')
                && $model->on_duty && $model->position_id
                && ($model->isDirty('position_id') || $model->isDirty('on_duty'))
            ) {
                // Find new sign up to associate with
                $model->slot_id = Schedule::findSlotIdSignUpByPositionTime($model->person_id, $model->position_id, $model->on_duty);
                if (!$model->slot_id) {
                    $start = $model->on_duty->clone()->subMinutes(Schedule::LOCATE_SHIFT_START_WITHIN);
                    $end = $model->on_duty->clone()->addMinutes(Schedule::LOCATE_SHIFT_START_WITHIN);
                    $slot = DB::table('slot')
                        ->select('id')
                        ->whereBetween('begins', [$start, $end])
                        ->where('position_id', $model->position_id)
                        ->first();
                    $model->slot_id = $slot?->id;
                }

            }

            if ($model->isDirty('desired_position_id') && $model->desired_position_id == $model->position_id) {
                $model->desired_position_id = null;
            }

            if ($model->isDirty('desired_on_duty')
                && is_a($model->on_duty, Carbon::class)
                && $model->desired_on_duty
                && $model->on_duty->eq($model->desired_on_duty)) {
                $model->desired_on_duty = null;
            }

            if ($model->isDirty('desired_off_duty')
                && is_a($model->off_duty, Carbon::class)
                && $model->desired_off_duty
                && $model->off_duty->eq($model->desired_off_duty)) {
                $model->desired_off_duty = null;
            }
        });

        self::saved(function (Timesheet $model) {
            $offDuty = $model->getChangedValues()['off_duty'] ?? null;

            // Did the off duty column go from nothing to a value? (i.e. shift ended)
            if ($offDuty && !$offDuty[0] && $offDuty[1]) {
                Pod::shiftEnded($model->person_id, $model->id);
            }

            $userId = Auth::id();
            $id = $model->id;

            if ($model->additionalNotes) {
                TimesheetNote::record($id, $userId, $model->additionalNotes, ($userId == $model->person_id) ? TimesheetNote::TYPE_USER : TimesheetNote::TYPE_HQ_WORKER);
            }

            if ($model->additionalAdminNotes) {
                TimesheetNote::record($id, $userId, $model->additionalAdminNotes, TimesheetNote::TYPE_ADMIN);
            }

            if ($model->additionalWranglerNotes) {
                TimesheetNote::record($id, $userId, $model->additionalWranglerNotes, TimesheetNote::TYPE_WRANGLER);
            }
            $onDuty = $model->getChangedValues()['on_duty'] ?? null;
            $isNonRanger = $model->getChangedValues()['is_non_ranger'] ?? null;
            if ($offDuty || $onDuty || $isNonRanger) {
                $model->computePersonYears();
            }
        });

        self::deleted(function (Timesheet $model) {
            TimesheetNote::where('timesheet_id', $model->id)->delete();
            $model->log(TimesheetLog::DELETE, [
                    'position_id' => $model->position_id,
                    'on_duty' => (string)$model->on_duty,
                    'off_duty' => (string)$model->off_duty
                ]
            );
            $model->computePersonYears();
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function reviewer_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function verified_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TimesheetNote::class)->where('type', '!=', TimesheetNote::TYPE_ADMIN);
    }

    public function admin_notes(): HasMany
    {
        return $this->hasMany(TimesheetNote::class)->where('type', TimesheetNote::TYPE_ADMIN);
    }

    public function desired_position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function loadRelationships($loadAdminNotes = false): void
    {
        $this->load(self::RELATIONSHIPS);
        if ($loadAdminNotes) {
            $this->load(self::ADMIN_NOTES_RELATIONSHIPS);
        }
    }

    public function timesheet_log(): HasMany
    {
        return $this->hasMany(TimesheetLog::class);
    }

    public function save($options = []): bool
    {
        if ($this->off_duty && $this->on_duty) {
            $this->rules['off_duty'] = 'required|date_format:Y-m-d H:i:s|after_or_equal:' . $this->on_duty->format('Y-m-d H:i:s');
        }

        return parent::save($options);
    }

    /**
     * Find timesheet entries based on the given criteria
     *
     * @param array $query
     * @return Collection
     */

    public static function findForQuery(array $query): Collection
    {
        $sql = self::query();

        $year = $query['year'] ?? null;
        $personId = $query['person_id'] ?? null;
        $isOnDuty = $query['is_on_duty'] ?? false;
        $dutyDate = $query['duty_date'] ?? null;
        $overHours = $query['over_hours'] ?? 0;
        $onDutyStart = $query['on_duty_start'] ?? null;
        $onDutyEnd = $query['on_duty_end'] ?? null;
        $positionId = $query['position_id'] ?? null;
        $positionIds = $query['position_ids'] ?? null;
        $includePhoto = $query['include_photo'] ?? false;
        $includeAdminNotes = $query['include_admin_notes'] ?? false;
        $checkTimes = $query['check_times'] ?? false;

        if ($year) {
            $sql->whereYear('on_duty', $year);
        }

        if ($personId) {
            $sql->where('person_id', $personId);
        } else {
            $sql->with('person:id,callsign');
        }

        if ($isOnDuty) {
            $sql->whereNull('off_duty');
            if ($overHours) {
                $sql->whereRaw("TIMESTAMPDIFF(HOUR, on_duty, ?) >= ?", [now(), $overHours]);
            }
        }

        if ($dutyDate) {
            $sql->where('on_duty', '<=', $dutyDate);
            $sql->whereRaw('IFNULL(off_duty, ?) >= ?', [now(), $dutyDate]);
        }

        if ($onDutyStart) {
            $sql->where('on_duty', '>=', $onDutyStart);
        }

        if ($onDutyEnd) {
            $sql->where('on_duty', '<=', $onDutyEnd);
        }

        if ($positionId) {
            $sql->where('position_id', $positionId);
        }

        if ($positionIds) {
            $sql->whereIn('position_id', $positionIds);
        }

        $sql->with(self::RELATIONSHIPS);

        if ($includeAdminNotes) {
            $sql->with(self::ADMIN_NOTES_RELATIONSHIPS);
        }

        $rows = $sql->orderBy('on_duty')->get();

        if ($includePhoto) {
            foreach ($rows as $row) {
                $row->photo_url = PersonPhoto::retrieveProfileUrlForPerson($row->person_id);
                $row->appends[] = 'photo_url';
            }
        }

        if ($checkTimes) {
            foreach ($rows as $row) {
                $row->checkTimes();
            }
        }

        if (!$personId) {
            $rows = $rows->sortBy('person.callsign', SORT_NATURAL | SORT_FLAG_CASE)->values();
        }

        return $rows;
    }

    /**
     * Find the (still) on duty timesheet for a person
     *
     * @param int $personId
     * @return Model|null
     */

    public static function findPersonOnDuty(int $personId): ?Model
    {
        return self::where('person_id', $personId)
            ->whereYear('on_duty', current_year())
            ->whereNull('off_duty')
            ->with(['position:id,title,type'])
            ->first();
    }

    /**
     * Check to see if a person is signed in to a position(s)
     *
     * @param int $personId
     * @param $positionIds
     * @return bool
     */

    public static function isPersonSignedIn(int $personId, $positionIds): bool
    {
        $sql = self::where('person_id', $personId)->whereNull('off_duty');
        if (is_array($positionIds)) {
            $sql->whereIn('position_id', $positionIds);
        } else {
            $sql->where('position_id', $positionIds);
        }

        return $sql->exists();
    }

    /**
     * Find everyone who is signed in to a given position
     *
     * @param int $positionId
     * @return mixed
     */

    public static function retrieveSignedInPeople(int $positionId): mixed
    {
        return DB::table('timesheet')
            ->select('timesheet.person_id', 'timesheet.on_duty', 'slot.begins', 'timesheet.slot_id')
            ->leftJoin('slot', 'slot.id', 'timesheet.slot_id')
            ->where('timesheet.position_id', $positionId)
            ->whereNull('off_duty')
            ->get()
            ->keyBy('person_id');
    }

    /**
     * Find an existing overlapping timesheet entry for a date range
     *
     * @param int $personId
     * @param $onduty
     * @param $offduty
     * @return Timesheet|null
     */

    public static function findOverlapForPerson(int $personId, $onduty, $offduty): Timesheet|null
    {
        return self::where('person_id', $personId)
            ->where(function ($sql) use ($onduty, $offduty) {
                $sql->whereBetween('on_duty', [$onduty, $offduty]);
                $sql->orWhereBetween('off_duty', [$onduty, $offduty]);
                $sql->orWhereRaw('? BETWEEN on_duty AND off_duty', [$onduty]);
                $sql->orWhereRaw('? BETWEEN on_duty AND off_duty', [$offduty]);
            })->first();
    }

    /**
     * Find all overlapping timesheets
     *
     * @param int $personId
     * @param Carbon|string $onduty
     * @param Carbon|string $offduty
     * @param int|null $timesheetId
     * @return \Illuminate\Support\Collection
     */

    public static function findAllOverlapsForPerson(int $personId, Carbon|string $onduty, Carbon|string $offduty, ?int $timesheetId): \Illuminate\Support\Collection
    {
        $sql = self::where('person_id', $personId)
            ->where(function ($sql) use ($onduty, $offduty) {
                $sql->whereBetween('on_duty', [$onduty, $offduty]);
                $sql->orWhereBetween('off_duty', [$onduty, $offduty]);
                $sql->orWhereRaw('? BETWEEN on_duty AND off_duty', [$onduty]);
                $sql->orWhereRaw('? BETWEEN on_duty AND off_duty', [$offduty]);
            })
            ->with('position:id,title')
            ->orderBy('on_duty');

        if ($timesheetId) {
            $sql->where('id', '!=', $timesheetId);
        }

        return $sql->get();
    }

    /**
     * Find an entry for a given person at a time within the given +/- minutes
     * (used to help find a shift partner for missing timesheet entry requests)
     *
     * @param int $personId
     * @param Carbon|int $startTime
     * @param int $withinMinutes
     * @return Timesheet|null
     */

    public static function findShiftWithinMinutes(int $personId, Carbon|int $startTime, int $withinMinutes): Timesheet|null
    {
        return self::with(['position:id,title'])
            ->where('person_id', $personId)
            ->whereRaw(
                'on_duty BETWEEN DATE_SUB(?, INTERVAL ? MINUTE) AND DATE_ADD(?, INTERVAL ? MINUTE)',
                [$startTime, $withinMinutes, $startTime, $withinMinutes]
            )->first();
    }

    /**
     * Find the years a person has based on $type:
     *
     * YEARS_SEEN: Timesheet years *including* Alpha & Training combined with shift sign up years
     * YEARS_COMBINED: Combined timesheet years excluding Alpha & Training entries for both Ranger and Non-Ranger work.
     * YEARS_AS_RANGER: Timesheet years excluding Alpha & Training entries as a Ranger (is_non_ranger=false)
     * YEARS_AS_CONTRIBUTOR: Timesheet years excluding Alpha & Training entries as a Non Ranger (is_non_ranger=true)
     *
     * @param int $personId
     * @param string $type
     * @return array
     */

    public static function findYears(int $personId, string $type): array
    {
        $everything = ($type == self::YEARS_SEEN);

        $sql = DB::table('timesheet')
            ->selectRaw("YEAR(on_duty) as year")
            ->where('person_id', $personId)
            ->groupBy("year")
            ->orderBy("year", "asc");

        if (!$everything) {
            $sql = $sql->whereNotIn("position_id", self::EXCLUDE_POSITIONS_FOR_YEARS);
        }

        if ($type == self::YEARS_AS_CONTRIBUTOR) {
            $sql->where('is_non_ranger', true);
        } else if ($type == self::YEARS_AS_RANGER) {
            $sql->where('is_non_ranger', false);
        }

        $years = $sql->pluck('year')->toArray();

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

    public function computePersonYears(): void
    {
        $person = $this->person;
        if (!$person) {
            // Account may have been deleted
            return;
        }

        $personId = $person->id;
        $person->years_seen = Timesheet::findYears($personId, Timesheet::YEARS_SEEN);
        $person->years_as_contributor = Timesheet::findYears($personId, Timesheet::YEARS_AS_CONTRIBUTOR);
        $person->years_as_ranger = Timesheet::findYears($personId, Timesheet::YEARS_AS_RANGER);
        $person->years_combined = Timesheet::findYears($personId, Timesheet::YEARS_COMBINED);
        $person->auditReason = 'years recompute';
        $person->saveWithoutValidation();
    }

    /**
     * Is the person a binary Ranger (0 or 1 years experience).
     * Current year is excluded to handle dashboard & training concerns.
     *
     * (e.g., Hubcap was a shiny penny in 2018, and works their first shift in 2019 - the current year,
     *  which means they have two years by simply counting timesheet entries. We don't want that because
     * then it throws things off with the dashboard & training checks.)
     *
     * @param Person $person
     * @return bool
     */

    public static function isPersonBinary(Person $person): bool
    {
        if ($person->status != Person::ACTIVE) {
            return false;
        }

        $years = self::selectRaw("YEAR(on_duty) as year")
            ->where('person_id', $person->id)
            ->whereYear('on_duty', '!=', current_year())
            ->whereNotIn("position_id", self::EXCLUDE_POSITIONS_FOR_YEARS)
            ->groupBy("year")
            ->get();

        return $years->count() <= 1;
    }

    /**
     * Find the latest timesheet entry for a person in a position and given year
     *
     * @param int $personId
     * @param int $positionId
     * @param int $year
     * @return Timesheet|null
     */

    public static function findLatestForPersonPosition(int $personId, int $positionId, int $year): Timesheet|null
    {
        return self::where('person_id', $personId)
            ->where('position_id', $positionId)
            ->whereYear('on_duty', $year)
            ->orderBy('on_duty', 'desc')
            ->first();
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

    public static function yearsRangeredCountForIds($personIds): array
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

        $excludePositions = implode(',', [Position::ALPHA, Position::TRAINING]);
        $rows = DB::select("SELECT person_id, COUNT(year) as years FROM (SELECT YEAR(on_duty) as year, person_id FROM timesheet WHERE person_id in (" . implode(',', $ids) . ") AND  position_id not in ($excludePositions) AND is_non_ranger IS FALSE GROUP BY person_id,year ORDER BY year) as rangers group by person_id");

        $people = [];
        foreach ($rows as $row) {
            $people[$row->person_id] = $row->years;
        }

        return $people;
    }

    /**
     * Does the given person have an Alpha entry for the given or current year?
     *
     * @param int $personId
     * @param int|null $year
     * @return bool
     */

    public static function hasAlphaEntry(int $personId, ?int $year = null): bool
    {
        return Timesheet::where('person_id', $personId)
            ->whereYear('on_duty', $year ?? current_year())
            ->where('position_id', Position::ALPHA)
            ->exists();
    }

    /**
     * Retrieve all the timesheet for the given people and position.
     *
     * @param array $personIds
     * @param int $positionId
     * @return \Illuminate\Support\Collection group by person_id and sub-grouped by year
     */

    public static function retrieveAllForPositionIds(array $personIds, int $positionId): \Illuminate\Support\Collection
    {
        return self::whereIntegerInRaw('person_id', $personIds)
            ->where('position_id', $positionId)
            ->orderBy('on_duty')
            ->get()
            ->groupBy([
                'person_id',
                fn($row) => $row->on_duty->year
            ]);
    }

    /**
     * Count the number of unverified timesheet entries for a given person and year
     *
     * @param int $personId
     * @param int $year
     * @return int
     */

    public static function countUnverifiedForPersonYear(int $personId, int $year): int
    {
        // Find all the unverified timesheet entries (unverified, and entries that were
        // corrected but the correction not verified by the person)
        return Timesheet::where('person_id', $personId)
            ->whereYear('on_duty', $year)
            ->whereIn('review_status', [Timesheet::STATUS_UNVERIFIED, Timesheet::STATUS_APPROVED])
            ->whereNotNull('off_duty')
            ->count();
    }

    /**
     * Determine if the given person has worked one or more positions in the last X years
     *
     * @param int $personId
     * @param int $years
     * @param $positionIds
     * @return bool
     */

    public static function didPersonWorkPosition(int $personId, int $years, $positionIds): bool
    {
        if (!is_array($positionIds)) {
            $positionIds = [$positionIds];
        }

        $cutoff = current_year() - $years;
        return DB::table('timesheet')
            ->where('person_id', $personId)
            ->whereYear('on_duty', '>=', $cutoff)
            ->whereIn('position_id', $positionIds)
            ->limit(1)
            ->exists();
    }

    /**
     * Did the given person work (or walked an Alpha shift) in a given year?
     *
     * @param int $personId
     * @param int $year
     * @return bool
     */
    public static function didPersonWork(int $personId, int $year): bool
    {
        return DB::table('timesheet')
            ->where('person_id', $personId)
            ->whereYear('on_duty', $year)
            ->where('position_id', '!=', Position::TRAINING)
            ->limit(1)
            ->exists();
    }

    /**
     * Did the given person work (or walked an Alpha shift) in a given year?
     *
     * @param int $personId
     * @param int $positionId
     * @param int $year
     * @return bool
     */

    public static function didWorkPositionInYear(int $personId, int $positionId, int $year): bool
    {
        return DB::table('timesheet')
            ->where('person_id', $personId)
            ->whereYear('on_duty', $year)
            ->where('position_id', $positionId)
            ->limit(1)
            ->exists();
    }

    /**
     * Calculate how many credits earned for a year
     * @param int $personId
     * @param int $year
     * @return float
     */

    public static function earnedCreditsForYear(int $personId, int $year): float
    {
        $rows = Timesheet::findForQuery(['person_id' => $personId, 'year' => $year]);
        if (!$rows->isEmpty()) {
            PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));
        }

        return $rows->pluck('credits')->sum();
    }

    /**
     * Run a check on the on & off duty times.
     *
     * @return void
     */

    public function checkTimes(): void
    {
        if (!$this->off_duty) {
            // Still on duty, can't do anything at moment
            return;
        }

        $this->time_warnings = Slot::checkDateTimeForPosition($this->position_id, $this->on_duty, $this->off_duty);
        $this->appends[] = 'time_warnings';

        if (!$this->desired_position_id && !$this->desired_on_duty && !$this->desired_off_duty) {
            // Still on duty, or no desired changes recorded.
            return;
        }

        $this->desired_warnings = Slot::checkDateTimeForPosition(
            $this->desired_position_id ?? $this->position_id,
            $this->desired_on_duty ?? $this->on_duty,
            $this->desired_off_duty ?? $this->off_duty,
        );

        $this->appends[] = 'desired_warnings';
    }


    /**
     * Create a Timesheet audit log for the entry
     *
     * @param string $action See TimesheetLog for actions
     * @param null $data
     */

    public function log(string $action, $data = null): void
    {
        TimesheetLog::record($action, $this->person_id, Auth::id(), $this->id, $data, $this->on_duty->year);
    }

    /**
     * Return the total seconds on duty.
     * @return int
     */

    public function getDurationAttribute(): int
    {
        if (isset($this->attributes['duration'])) {
            return $this->attributes['duration'];
        }

        $offDuty = $this->off_duty ?: now();
        $duration = (int)$this->on_duty->diffInSeconds($offDuty);
        $this->attributes['duration'] = $duration;
        return $duration;
    }

    /**
     * Return the position title (if record was joined with the position table)
     *
     * @return string
     */

    public function getPositionTitleAttribute(): string
    {
        return $this->attributes['position_title'] ?? '';
    }

    /**
     * Return the credits earned
     *
     * @return float
     * @throws InvalidArgumentException
     */

    public function getCreditsAttribute(): float
    {
        // Already computed?
        if (isset($this->attributes['credits'])) {
            return $this->attributes['credits'];
        }

        if (!$this->on_duty) {
            return 0;
        }

        // Go forth and get the tasty credits!
        $credits = PositionCredit::computeCredits(
            $this->position_id,
            $this->on_duty->timestamp,
            ($this->off_duty ?? now())->timestamp,
            $this->on_duty->year
        );

        $this->attributes['credits'] = $credits;

        return $credits;
    }

    /**
     * Set the off duty time to now
     */

    public function setOffDutyToNow(): void
    {
        $this->off_duty = now();
    }

    /**
     * Set the off duty to null and save the record. Cannot use $model->off_duty = null because
     * Eloquent will insist on using Carbon::parse(null) which yields the current time. Sigh.
     */

    public function setOffDutyToNullAndSave(string $reason): void
    {
        $oldValue = (string)$this->off_duty;
        DB::update("UPDATE timesheet SET off_duty=NULL WHERE id=?", [$this->id]);
        ActionLog::record(Auth::user(), 'timesheet-update', $reason, [
            'id' => $this->id,
            'off_duty' => [$oldValue, null]
        ], $this->person_id);

        $this->refresh();
        $this->loadRelationships();
    }

    /**
     * Return the position subtype for the timesheet entry.
     *
     * @return string|null
     */

    public function getPositionSubtypeAttribute(): string|null
    {
        return $this->position->subtype;
    }

    /**
     * Build on duty information
     *
     * @return array
     */

    public function buildOnDutyInfo(): array
    {
        return [
            'id' => $this->position_id,
            'title' => $this->position->title,
            'type' => $this->position->type,
            'subtype' => $this->position->subtype,
        ];
    }

    public function setAdditionalNotesAttribute(?string $value): void
    {
        if ($value) {
            $value = trim($value);
        }
        $value = empty($value) ? null : $value;
        $this->additionalNotes = $value;
    }

    public function setAdditionalAdminNotesAttribute(?string $value): void
    {
        if ($value) {
            $value = trim($value);
        }
        $value = empty($value) ? null : $value;
        $this->additionalAdminNotes = $value;
    }

    public function setAdditionalWranglerNotesAttribute(?string $value): void
    {
        if ($value) {
            $value = trim($value);
        }
        $value = empty($value) ? null : $value;
        $this->additionalWranglerNotes = $value;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_url;
    }

    public function getTimeWarningsAttribute(): ?array
    {
        return $this->time_warnings;
    }

    public function getDesiredWarningsAttribute(): ?array
    {
        return $this->desired_warnings;
    }

    public function desiredOnDuty(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function desiredOffDuty(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function desiredPositionId(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

}
