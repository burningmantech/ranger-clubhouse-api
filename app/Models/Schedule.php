<?php

namespace App\Models;

use App\Lib\WorkSummary;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleException extends Exception
{
}

class Schedule extends ApiModel
{
    protected $fillable = [
        'position_id',
        'position_title',
        'position_type',
        'position_count_hours',
        'slot_id',
        'slot_active',
        'slot_begins',
        'slot_ends',
        'slot_description',
        'slot_signed_up',
        'slot_url',
        'trainers',
        'slot_begins_time',
        'slot_ends_time',
    ];

    // And the rest are calculated
    protected $appends = [
        'slot_duration',
        'year',
        'credits',
        'slot_max',
    ];

    protected $dates = [
        'slot_begins',
        'slot_ends'
    ];

    const LOCATE_SHIFT_START_WITHIN = 45; // Find a shift starting within +/- X minutes

    const MAY_START_SHIFT_WITHIN = 15; // For HQ Window Interface: most shifts are only to be signed in to within X minutes of the start time.

    /*
     * Sign up statuses
     */

    const SUCCESS = 'success'; // person was successfully signed up for a slot
    const FULL = 'full'; // slot is at capacity
    const MULTIPLE_ENROLLMENT = 'multiple-enrollment'; // person is already enrolled in another slot with same position (i.e. training)
    const NOT_ACTIVE = 'not-active'; // slot has not been activated
    const NO_POSITION = 'no-position'; // person does not hold the position the slot is associated with
    const NO_SLOT = 'no-slot'; // slot does not exist
    const HAS_STARTED = 'has-started'; // slot has already started
    const EXISTS = 'exists'; // person is already signed up for the slot.
    const MISSING_REQUIREMENTS = 'missing-requirements'; // person has not met all the requirements to sign up

    /*
     * Failed sign up statuses that are allowed to be forced if the user has the appropriate
     * privileges/positions/rolls
     */

    const MAY_FORCE_STATUSES = [
        self::FULL, self::HAS_STARTED, self::MULTIPLE_ENROLLMENT
    ];

    /**
     * Find slots in the given year the person may potentially sign up for and/or what slots
     * the person is already signed up for.
     *
     * NOTE: A slot may be returned where the person no longer holds the position. E.g., Alpha, ART mentees,
     * or is no longer part of a special team.
     *
     * @param int $personId
     * @param int $year
     * @param array $query
     * @return array
     */

    public static function findForQuery(int $personId, int $year, array $query = []): array
    {
        $now = (string)now();
        $onlySignups = $query['only_signups'] ?? false;
        $remaining = $query['remaining'] ?? false;

        $selectColumns = [
            DB::raw('DISTINCT slot.id as id'),
            'slot.position_id as position_id',
            'slot.begins AS slot_begins',
            'slot.ends AS slot_ends',
            'slot.description AS slot_description',
            'slot.signed_up AS slot_signed_up',
            'slot.max AS slot_max_potential',    // used to compute sign up limit.
            'slot.url AS slot_url',
            'slot.active as slot_active',
            'slot.trainer_slot_id',
            'trainer_slot.signed_up AS trainer_count',
            DB::raw('UNIX_TIMESTAMP(slot.begins) as slot_begins_time'),
            DB::raw('UNIX_TIMESTAMP(slot.ends) as slot_ends_time'),
            DB::raw("IF(slot.begins < '$now', TRUE, FALSE) as has_started"),
            DB::raw("IF(slot.ends < '$now', TRUE, FALSE) as has_ended"),
            DB::raw("IF(person_slot.person_id IS NULL, FALSE, TRUE) as person_assigned")
        ];

        // Find all slots the person is eligible for AND all slots the person is already signed up for
        // Note: a person may be signed up for a position they no longer hold. e.g., Alpha, Green Dot Mentee, etc.

        $sql = DB::table('slot')
            ->select($selectColumns)
            ->leftJoin('person_slot', function ($join) use ($personId) {
                $join->where('person_slot.person_id', $personId)
                    ->on('slot.id', 'person_slot.slot_id');
            })->leftJoin('slot as trainer_slot', 'trainer_slot.id', '=', 'slot.trainer_slot_id')
            ->whereYear('slot.begins', $year)
            ->orderBy('slot.begins', 'asc');

        if ($onlySignups) {
            $sql->whereNotNull('person_slot.person_id');
        } else {
            $sql->leftJoin('person_position', function ($join) use ($personId) {
                $join->where('person_position.person_id', $personId)
                    ->on('person_position.position_id', 'slot.position_id');
            })->whereRaw('(person_slot.person_id IS NOT NULL OR person_position.person_id IS NOT NULL)');
        }

        if ($remaining) {
            // Include slots already started but have not ended.
            $sql->where('slot.ends', '>=', now());
        }

        $rows = $sql->get();

        if (!$rows->isEmpty()) {
            $positions = DB::table('position')
                ->select('id', 'title', 'type', 'contact_email', 'count_hours')
                ->whereIn('id', $rows->pluck('position_id')->unique())
                ->get()
                ->toArray();
        } else {
            $positions = [];
        }


        // return an array of Schedule loaded from the rows
        return [Schedule::hydrate($rows->toArray()), $positions];
    }

    /**
     * Find the next shift for a person
     *
     * @param int $personId person to find the next shift for
     * @return mixed
     */

    public static function findNextShift(int $personId): mixed
    {
        return DB::table('person_slot')
            ->select('position.title', 'slot.description', 'begins')
            ->join('slot', function ($j) {
                $j->on('slot.id', '=', 'person_slot.slot_id');
                $j->where('slot.begins', '>', now());
            })->join('position', 'position.id', '=', 'slot.position_id')
            ->where('person_slot.person_id', $personId)
            ->orderBy('slot.begins')
            ->first();
    }

    /**
     * Add a person to a slot, and update the signed up count.
     * Also verify the signup does not already exist,the sign up
     * count is not maxed out, the person holds the slot's position,
     * and the slot exists.
     *
     * An array is returned with one of the following values:
     *   - Success full sign up
     *     status='success', signed_up=the signed up count include this person
     *   - Slot is maxed out
     *      status='full'
     *   - Person does not hold slot position
     *      status='no-position'
     *   - Person is already signed up for the slot
     *      status='exists'
     *
     * @param int $personId person to add
     * @param Slot $slot slot to add to
     * @param bool $force set true if to ignore the signup limit
     * @return array
     */

    public static function addToSchedule(int $personId, Slot $slot, bool $force = false): array
    {
        if (PersonSlot::haveSlot($personId, $slot->id)) {
            return ['status' => self::EXISTS, 'signed_up' => $slot->signed_up];
        }

        $now = now();

        if ($now->gt($slot->begins) && !$force) {
            // Shift has already started, don't allow a sign up
            return ['status' => self::HAS_STARTED, 'signed_up' => $slot->signed_up];
        }

        $max = $slot->max;
        if ($slot->trainer_slot_id) {
            $max = $max * PersonSlot::where('slot_id', $slot->trainer_slot_id)->count();
        }

        try {
            $isOvercapacity = false;

            DB::beginTransaction();
            // Re-read the slot for update.
            $updateSlot = Slot::where('id', $slot->id)->lockForUpdate()->first();
            if (!$updateSlot) {
                // shouldn't happen but ya never know...
                throw new ScheduleException(self::NO_SLOT);
            }

            // Cannot exceed sign up limit unless it is forced.
            if ($updateSlot->signed_up >= $max) {
                if (!$force) {
                    throw new ScheduleException(self::FULL);
                }

                $isOvercapacity = true;
            }

            // Sign up the person
            PersonSlot::create(['person_id' => $personId, 'slot_id' => $updateSlot->id]);

            $updateSlot->signed_up += 1;
            $updateSlot->save();

            DB::commit();

            return [
                'status' => self::SUCCESS,
                'signed_up' => $updateSlot->signed_up,
                'overcapacity' => $isOvercapacity,
            ];
        } catch (ScheduleException $e) {
            DB::rollback();
            return ['status' => $e->getMessage(), 'signed_up' => $updateSlot->signed_up];
        }
    }

    /**
     * Remove a sign-up for a person
     *
     * @param int $personId
     * @param int $slotId
     * @return array
     * @throws Exception
     */

    public static function deleteFromSchedule(int $personId, int $slotId): array
    {
        $personSlot = PersonSlot::where([
            ['person_id', $personId],
            ['slot_id', $slotId]
        ])->firstOrFail();

        try {
            DB::beginTransaction();
            $slot = $personSlot->belongsTo(Slot::class, 'slot_id')
                ->lockForUpdate()
                ->firstOrFail();
            $personSlot->delete();

            if ($slot->signed_up > 0) {
                $slot->update(['signed_up' => ($slot->signed_up - 1)]);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            throw $e;
        }

        return ['status' => self::SUCCESS, 'signed_up' => $slot->signed_up];
    }

    /**
     * Does a person have multiple enrollments for the same position (aka Training or Alpha shift)
     * and if so what are the enrollments?
     *
     * @param int $personId
     * @param int $positionId
     * @param int $year
     * @param $enrollments
     * @return bool
     */

    public static function haveMultipleEnrollments(int $personId, int $positionId, int $year, &$enrollments): bool
    {
        $slotIds = self::findEnrolledSlotIds($personId, $year, $positionId);

        if ($slotIds->isEmpty()) {
            $enrollments = null;
            return false;
        }

        $enrollments = Slot::whereIn('id', $slotIds)->with('position:id,title')->get();
        return true;
    }

    /**
     * Can the person join the training session?
     *
     * When the slot has a session part (a "Part X" in the description) hunt down the other
     * enrollments and see if the parts match.
     * @param int $personId
     * @param Slot $slot
     * @param $enrollments
     * @return bool
     */

    public static function canJoinTrainingSlot(int $personId, Slot $slot, &$enrollments): bool
    {
        $enrollments = null;

        $year = $slot->begins->year;
        $positionId = $slot->position_id;

        $slotIds = self::findEnrolledSlotIds($personId, $year, $positionId);
        if ($slotIds->isEmpty()) {
            // Good to go!
            return true;
        }

        $slots = Slot::whereIn('id', $slotIds)->with('position:id,title')->get();
        // Two or more enrollments is bad news, there's only two part session.
        if ($slots->count() >= 2) {
            $enrollments = $slots;
            return false;
        }

        foreach ($slots as $row) {
            if (Slot::isPartOfSessionGroup($row->description, $slot->description)) {
                return true;
            }
        }

        $enrollments = $slots;
        return false;
    }

    /**
     * Find all slot ids the person is signed up for a given year and position.
     *
     * @param int $personId
     * @param int $year
     * @param int $positionId
     * @return Collection
     */

    public static function findEnrolledSlotIds(int $personId, int $year, int $positionId): Collection
    {
        return self::findEnrolledSlots($personId, $year, $positionId)->pluck('slot_id');
    }

    /**
     * Find all slot rows the person is signed up for a given year and position.
     *
     * @param $personId
     * @param $year
     * @param $positionId
     * @return PersonSlot[]|\Illuminate\Database\Eloquent\Collection|Collection
     */

    public static function findEnrolledSlots($personId, $year, $positionId): \Illuminate\Database\Eloquent\Collection|array|Collection
    {
        return PersonSlot::where('person_slot.person_id', $personId)
            ->join('slot', function ($query) use ($positionId, $year) {
                $query->whereColumn('slot.id', 'person_slot.slot_id');
                $query->where('slot.position_id', $positionId);
                $query->whereYear('slot.begins', $year);
            })->orderBy('slot.begins')
            ->get();
    }

    /**
     * Find the slots where the person signed up beginning within +/- LOCATE_SHIFT_START_WITHIN minutes of now.
     *
     * @param int $personId
     * @return array
     */

    public static function retrieveStartingSlotsForPerson(int $personId): array
    {
        $now = now();
        $rows = PersonSlot::join('slot', 'slot.id', 'person_slot.slot_id')
            ->whereRaw(
                '? BETWEEN DATE_SUB(slot.begins, INTERVAL ? MINUTE) AND DATE_ADD(slot.begins, INTERVAL ? MINUTE)',
                [$now, self::LOCATE_SHIFT_START_WITHIN, self::LOCATE_SHIFT_START_WITHIN]
            )
            ->where('person_id', $personId)
            ->with('slot.position:id,title')
            ->get();

        return $rows->map(function ($row) use ($now) {
            $slot = $row->slot;
            $start = $slot->begins->clone()->subMinutes(self::MAY_START_SHIFT_WITHIN);
            $withinStart = $start->lte($now);
            $shift = [
                'slot_id' => $row->slot_id,
                'slot_description' => $slot->description,
                'position_id' => $slot->position_id,
                'position_title' => $slot->position->title,
                'slot_begins' => (string)$slot->begins,
                'slot_ends' => (string)$slot->ends,
                'is_within_start_time' => $withinStart,
            ];
            if (!$withinStart) {
                $shift['can_start_in'] = $now->diffInMinutes($start);
            }
            return $shift;
        })->toArray();
    }

    /**
     * Find the (probable) slot id sign up for a person based on the position and time.
     *
     * @param integer $personId the person in question
     * @param integer $positionId the position to search for
     * @param string|Carbon $begins the time to look for.
     * @return int|null return the id of the slot found, or null if nothing.
     */

    public static function findSlotIdSignUpByPositionTime(int $personId, int $positionId, string|Carbon $begins): int|null
    {
        $time = Carbon::parse($begins);
        $start = $time->clone()->subMinutes(self::LOCATE_SHIFT_START_WITHIN);
        $end = $time->clone()->addMinutes(self::LOCATE_SHIFT_START_WITHIN);

        $signUp = PersonSlot::join('slot', 'slot.id', 'person_slot.slot_id')
            ->whereRaw('slot.begins BETWEEN ? AND ?', [$start, $end])
            ->where('person_id', $personId)
            ->where('slot.position_id', $positionId)
            ->first();

        return $signUp?->slot_id;
    }

    /**
     * Does the person have a sign up occurring in the Burn Weekend?
     *
     * @param Person $person
     * @return bool
     */

    public static function haveBurnWeekendSignup(Person $person): bool
    {
        list ($start, $end) = EventDate::retrieveBurnWeekendPeriod();
        return Schedule::hasSignupInPeriod($person->id, $start, $end);
    }

    /**
     * Are there burn weekend shifts available for the person?
     *
     * @param Person $person
     * @return bool true if shifts are available
     */

    public static function haveAvailableBurnWeekendShiftsForPerson(Person $person): bool
    {
        list ($start, $end) = EventDate::retrieveBurnWeekendPeriod();

        return DB::table('slot')
            ->join('person_position', function ($j) use ($person) {
                $j->on('person_position.position_id', 'slot.position_id');
                $j->where('person_position.person_id', $person->id);
            })
            ->where('slot.active', true)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('slot.begins', [$start, $end]);
                $q->orWhereBetween('slot.ends', [$start, $end]);
            })
            ->exists();
    }


    /**
     * Have dirt shifts opened up?
     *
     * @return bool true if shifts are available
     */

    public static function areDirtShiftsAvailable(): bool
    {
        return DB::table('slot')
            ->where('slot.active', true)
            ->whereYear('slot.begins', current_year())
            ->where('slot.position_id', Position::DIRT)
            ->exists();
    }


    /**
     * Count the number of working (i.e. non-training) signups the person has in the current year.
     *
     * @param Person $person
     * @return array
     */

    public static function summarizeShiftSignups(Person $person): array
    {
        $year = current_year();
        list ($rows, $positions) = self::findForQuery($person->id, $year, [ 'only_signups' => true]);

        $positionsById = [];
        foreach ($positions as $position) {
            $positionsById[(int)$position->id] = $position;
        }
        $rows = $rows->filter(function ($r) use ($positionsById) {
            return $positionsById[(int)$r->position_id]->type != Position::TYPE_TRAINING;
        });

        if (!$rows->isEmpty()) {
            // Warm the position credit cache.
            PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));
        }

        $totalDuration = 0.0;
        $countedDuration = 0.0;
        $credits = 0.0;

        foreach ($rows as $row) {
            $credits += $row->credits;
            $totalDuration += $row->slot_duration;
            if ($row->position_count_hours) {
                $countedDuration += $row->slot_duration;
            }
        }

        return [
            'total_duration' => $totalDuration,
            'counted_duration' => $countedDuration,
            'credits' => $credits,
            'slot_count' => count($rows)
        ];
    }

    /**
     * Determine if a person is signed up for a shift occurring within a certain date range.
     *
     * @param int $personId
     * @param $start
     * @param $end
     * @return bool
     */

    public static function hasSignupInPeriod(int $personId, $start, $end)
    {
        return DB::table('person_slot')
            ->join('slot', 'person_slot.slot_id', 'slot.id')
            ->where('person_slot.person_id', $personId)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('slot.begins', [$start, $end]);
                $q->orWhereBetween('slot.ends', [$start, $end]);
            })
            ->exists();
    }

    /**
     * Build a schedule summary (credit / hour break down into pre-event, event, post-event, other)
     *
     * @param int $personId
     * @param int $year
     * @return array
     */

    public static function scheduleSummaryForPersonYear(int $personId, int $year, bool $remaining = false): object
    {
        $query = [
            'only_signups' => true
        ];

        if ($remaining){
            $query['remaining']  = true;
        }
        $now = now();

        list ($rows, $positions) = self::findForQuery($personId, $year, $query);

        $eventDates = EventDate::findForYear($year);

        if (!$rows->isEmpty()) {
            PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));
        }

        if (!$eventDates) {
            // No event dates - return everything as happening during the event
            $time = $rows->pluck('slot_duration')->sum();
            $credits = $rows->pluck('credits')->sum();

            return (object)[
                'pre_event_duration' => 0,
                'pre_event_credits' => 0,
                'event_duration' => $time,
                'event_credits' => $credits,
                'post_event_duration' => 0,
                'post_event_credits' => 0,
                'total_duration' => $time,
                'total_credits' => $credits,
                'other_duration' => 0,
                'counted_duration' => 0,
            ];
        }

        $summary = new WorkSummary($eventDates->event_start->timestamp, $eventDates->event_end->timestamp, $year);

        $positionsById = [];
        foreach ($positions as $position) {
            $positionsById[$position->id] = $position;
        }

        foreach ($rows as $row) {
            if ($remaining && $row->slot_begins->lt($now)) {
                // Truncate any shifts which have started
                $row->slot_begins = $now;
                $row->slot_begins_time = $now->timestamp;
            }
            $position = $positionsById[$row->position_id];
            $summary->computeTotals($row->position_id, $row->slot_begins_time, $row->slot_ends_time, $position->count_hours);
        }

        return (object) [
            'pre_event_duration' => $summary->pre_event_duration,
            'pre_event_credits' => $summary->pre_event_credits,
            'event_duration' => $summary->event_duration,
            'event_credits' => $summary->event_credits,
            'post_event_duration' => $summary->post_event_duration,
            'post_event_credits' => $summary->post_event_credits,
            'total_duration' => ($summary->pre_event_duration + $summary->event_duration + $summary->post_event_duration + $summary->other_duration),
            'total_credits' => ($summary->pre_event_credits + $summary->event_credits + $summary->post_event_credits),
            'other_duration' => $summary->other_duration,
            'counted_duration' => ($summary->pre_event_duration + $summary->event_duration + $summary->post_event_duration),
            'event_start' => (string)$eventDates->event_start,
            'event_end' => (string)$eventDates->event_end,
        ];
    }

    /**
     * Report on slots the given person signed up and/or was removed from.
     *
     * @param int $personId
     * @param int $year
     * @return array
     */

    public static function retrieveScheduleLog(int $personId, int $year): array
    {
        $rows = ActionLog::where('target_person_id', $personId)
            ->whereYear('created_at', $year)
            ->whereIn('event', ['person-slot-add', 'person-slot-remove'])
            ->with(['person:id,callsign'])
            ->get();

        $slots = [];
        foreach ($rows as $row) {
            $person = [
                'id' => $row->person_id,
                'callsign' => $row->person->callsign ?? 'Deleted #' . $row->person_id
            ];
            $slotId = $row->data['slot_id'];

            if (!isset($slots[$slotId])) {
                $slot = Slot::find($slotId);
                if ($slot) {
                    $slot->load(['position:id,title']);
                    $logInfo = [
                        'slot_id' => $slotId,
                        'slot_description' => $slot->description,
                        'slot_begins' => (string)$slot->begins,
                        'position_id' => $slot->position_id,
                        'position_title' => $slot->position->title ?? 'Deleted #' . $slot->position_id,
                    ];
                } else {
                    $logInfo = [
                        'slot_id' => $slotId,
                        'slot_description' => 'Deleted #' . $slotId,
                        'slot_begins' => (string)$row->created_at,
                        'position_id' => 0,
                        'position_title' => 'unknown'
                    ];
                }
                $logInfo['added'] = [];
                $logInfo['removed'] = [];

                if (!PersonSlot::where(['person_id' => $personId, 'slot_id' => $slotId])->exists()) {
                    // Indicate the person is no longer signed up
                    $logInfo['no_signup'] = true;
                }

                $slots[$slotId] = &$logInfo;
            } else {
                $logInfo = &$slots[$slotId];
            }

            if ($row->event == 'person-slot-add') {
                $logInfo['added'][] = [
                    'date' => (string)$row->created_at,
                    'person' => $person
                ];
            } else {
                $logInfo['removed'][] = [
                    'date' => (string)$row->created_at,
                    'person' => $person
                ];
            }

            unset($logInfo);
        }

        $slots = array_values($slots);
        usort($slots, function ($a, $b) {
            return strcmp($a['slot_begins'], $b['slot_begins']);
        });
        return $slots;
    }

    /**
     * Calculate how long the shift is in seconds.
     *
     * @return int
     */
    public function getSlotDurationAttribute(): int
    {
        return $this->slot_ends_time - $this->slot_begins_time;
    }

    /**
     * Return the year the slot is for
     *
     * @return int
     */

    public function getYearAttribute(): int
    {
        return $this->slot_begins->year;
    }

    /**
     * Return how many credits this slot is worth
     *
     * @return float
     */

    public function getCreditsAttribute(): float
    {
        return PositionCredit::computeCredits($this->position_id, $this->slot_begins_time, $this->slot_ends_time, $this->year);
    }

    /**
     * Figure out how many signups are allowed.
     *
     * @return int
     */

    public function getSlotMaxAttribute(): int
    {
        return ($this->trainer_slot_id ? $this->trainer_count * $this->slot_max_potential : $this->slot_max_potential);
    }
}
