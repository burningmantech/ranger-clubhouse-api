<?php

namespace App\Models;

use App\Helpers\SqlHelper;

use App\Lib\WorkSummary;

use App\Models\ApiModel;
use App\Models\Slot;

use Carbon\Carbon;

use Exception;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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

    const SHIFT_STARTS_WITHIN = 30; // A shift starts within X minutes

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

    /*
     * Failed sign up statuses that are allowed to be forced if the user has the appropriate
     * privileges/positions/rolls
     */

    const MAY_FORCE_STATUSES = [
        self::FULL, self::HAS_STARTED, self::MULTIPLE_ENROLLMENT
    ];

    public static function findForQuery($query)
    {
        if (empty($query['year'])) {
            throw new InvalidArgumentException('Missing year parameter');
        }

        $year = $query['year'];

        $personId = $query['person_id'] ?? null;
        $shiftsAvailable = $query['shifts_available'] ?? false;
        $remaining = $query['remaining'] ?? false;
        $now = (string) now();

        $selectColumns = [
            'slot.id as id',
            'position.id as position_id',
            'position.title AS position_title',
            'position.type AS position_type',
            'position.contact_email',
            'position.count_hours as position_count_hours',
            'slot.begins AS slot_begins',
            'slot.ends AS slot_ends',
            'slot.description AS slot_description',
            'slot.signed_up AS slot_signed_up',
            'slot.max AS slot_max_potential',    // use to compute sign up limit.
            'slot.url AS slot_url',
            'slot.active as slot_active',
            'slot.trainer_slot_id',
            'trainer_slot.signed_up AS trainer_count',
            DB::raw('UNIX_TIMESTAMP(slot.begins) as slot_begins_time'),
            DB::raw('UNIX_TIMESTAMP(slot.ends) as slot_ends_time'),
            DB::raw("IF(slot.begins < '$now', TRUE, FALSE) as has_started"),
            DB::raw("IF(slot.ends < '$now', TRUE, FALSE) as has_ended")
        ];

        // Is this a simple schedule find for a person?
        if ($personId && !$shiftsAvailable) {
            $sql = DB::table('person_slot')
                ->where('person_slot.person_id', $personId)
                ->join('slot', 'slot.id', '=', 'person_slot.slot_id');
            if ($remaining) {
                $sql->where('slot.ends', '>', $now);
            }
        } else {
            // Retrieve all slots
            $sql = DB::table('slot');

            // .. and find out which slots a person has signed up for
            if ($shiftsAvailable) {
                $sql->join('person_position', function ($join) use ($personId) {
                    $join->where('person_position.person_id', $personId)
                        ->on('person_position.position_id', 'slot.position_id');
                });
            }
        }

        if (isset($query['type'])) {
            $sql = $sql->where('position.type', $query['type']);
        }

        $rows = $sql->select($selectColumns)
            ->whereYear('slot.begins', $year)
            ->join('position', 'position.id', '=', 'slot.position_id')
            ->leftJoin('slot as trainer_slot', 'trainer_slot.id', '=', 'slot.trainer_slot_id')
            ->orderBy('slot.begins', 'asc')
            ->orderBy('position.title', 'asc')
            ->orderBy('slot.description', 'asc')
            ->get()
            ->toArray();

        // return an array of Schedule loaded from the rows
        return Schedule::hydrate($rows);
    }

    /**
     * Find the next shift for a person
     *
     * @param int $personId person to find the next shift for
     * @return mixed
     */

    public static function findNextShift(int $personId)
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

            // looks up! sign up the person
            $ps = new PersonSlot([
                'person_id' => $personId,
                'slot_id' => $updateSlot->id
            ]);
            $ps->save();

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
     * Remove a sign up for a person
     *
     * @param int $personId
     * @param int $personSlotId
     * @return array
     * @throws Exception
     */
    public static function deleteFromSchedule(int $personId, int $personSlotId): array
    {
        $personSlot = PersonSlot::where([
            ['person_id', $personId],
            ['slot_id', $personSlotId]
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

        return ['status' => 'success', 'signed_up' => $slot->signed_up];
    }

    /*
     * Does a person have multiple enrollments for the same position (aka Training or Alpha shift)
     * and if so what are the enrollments?
     */

    public static function haveMultipleEnrollments($personId, $positionId, $year, &$enrollments)
    {
        $slotIds = self::findEnrolledSlotIds($personId, $year, $positionId);

        if ($slotIds->isEmpty()) {
            $enrollments = null;
            return false;
        }

        $enrollments = Slot::whereIn('id', $slotIds)->with('position:id,title')->get();
        return true;
    }

    /*
     * Can the person join the training session?
     *
     * When the slot has a session part (a "Part X" in the description) hunt down the other
     * enrollments and see if the parts match.
     */

    public static function canJoinTrainingSlot($personId, $slot, &$enrollments)
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

    public static function findEnrolledSlotIds($personId, $year, $positionId)
    {
        return self::findEnrolledSlots($personId, $year, $positionId)->pluck('slot_id');
    }

    public static function findEnrolledSlots($personId, $year, $positionId)
    {
        return PersonSlot::where('person_slot.person_id', $personId)
            ->join('slot', function ($query) use ($positionId, $year) {
                $query->whereRaw('slot.id=person_slot.slot_id');
                $query->where('slot.position_id', $positionId);
                $query->whereYear('slot.begins', $year);
            })->orderBy('slot.begins')
            ->get();
    }

    public static function retrieveStartingSlotsForPerson($personId)
    {
        $rows = PersonSlot::join('slot', function ($query) {
            $now = (string) now();
            $query->whereRaw('slot.id=person_slot.slot_id');
            $query->whereRaw(
                'slot.begins BETWEEN DATE_SUB(?, INTERVAL ? MINUTE) AND DATE_ADD(?, INTERVAL ? MINUTE)',
                [$now, self::SHIFT_STARTS_WITHIN, $now, self::SHIFT_STARTS_WITHIN]
            );
        })
            ->where('person_id', $personId)
            ->with('slot.position:id,title')->get();

        return $rows->map(function ($row) {
            return [
                'slot_id' => $row->slot_id,
                'slot_description' => $row->slot->description,
                'position_id' => $row->slot->position_id,
                'position_title' => $row->slot->position->title,
                'slot_begins' => (string)$row->slot->begins,
                'slot_ends' => (string)$row->slot->ends,
            ];
        });
    }

    /**
     * Find the (probable) slot sign up for a person based on the position and time.
     *
     * @param integer $personId the person in question
     * @param integer $positionId the position to search for
     * @param string $begins the time to look for.
     * @return mixed return the id of the slot found, or null if nothing.
     */

    public static function findSlotSignUpByPositionTime($personId, $positionId, $begins)
    {
        $signUp = PersonSlot::join('slot', function ($q) use ($begins) {
            $q->on('slot.id', 'person_slot.slot_id');
            $q->whereRaw('slot.begins BETWEEN DATE_SUB(?, INTERVAL 45 MINUTE) AND DATE_ADD(?, INTERVAL 45 MINUTE)',
                [$begins, $begins]);
        })->where('person_id', $personId)
            ->where('slot.position_id', $positionId)
            ->first();

        return $signUp ? $signUp->slot_id : null;
    }

    /**
     * Should a burn weekend shift be recommended to the person?
     * @param Person $person
     * @return bool true if the person does not have any weekend shift signups.
     */
    public static function recommendBurnWeekendShift(Person $person)
    {
        $status = $person->status;
        if ($status == Person::ALPHA
            || $status == Person::AUDITOR
            || $status == Person::PROSPECTIVE
            || $status == Person::NON_RANGER) {
            // Do not recommend to PNVs, Auditors, and Non Rangers
            return false;
        }

        list ($start, $end) = EventDate::retrieveBurnWeekendPeriod();

        if (now()->gt($end)) {
            // The current time is past the burn weekend.
            return false;
        }

        return !Schedule::hasSignupInPeriod($person->id, $start, $end);
    }

    public static function haveBurnWeekendSignup(Person $person)
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
    public static function haveAvailableBurnWeekendShiftsForPerson(Person $person)
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
    public static function areDirtShiftsAvailable()
    {
        return DB::table('slot')
            ->where('slot.active', true)
            ->whereYear('slot.begins', current_year())
            ->where('slot.position_id', Position::DIRT)
            ->exists();
    }


    /**
     * Count the number of working (i.e. non-training) signups the person has in the current year.
     * @param Person $person
     * @return int
     */
    public static function countWorkingShiftSignups(Person $person)
    {
        return DB::table('slot')
            ->join('person_slot', 'person_slot.slot_id', 'slot.id')
            ->join('position', 'position.id', 'slot.position_id')
            ->where('person_slot.person_id', $person->id)
            ->where('position.type', '!=', 'Training')
            ->whereYear('slot.begins', current_year())
            ->count();
    }

    /*
     * Determine if a person is signed up for a shift occuring within a certain date range.
     */

    public static function hasSignupInPeriod($personId, $start, $end)
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

    /*
     * build a schedule summary (credit / hour break down into pre-event, event, post-event, other)
     */

    public static function scheduleSummaryForPersonYear($personId, $year)
    {
        $rows = self::findForQuery(['person_id' => $personId, 'year' => $year]);

        $eventDates = EventDate::findForYear($year);

        if (!$rows->isEmpty()) {
            PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));
        }

        if (!$eventDates) {
            // No event dates - return everything as happening during the event
            $time = $rows->pluck('slot_duration')->sum();
            $credits = $rows->pluck('credits')->sum();

            return [
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

        foreach ($rows as $row) {
            $summary->computeTotals($row->position_id, $row->slot_begins_time, $row->slot_ends_time, $row->position_count_hours);
        }

        return [
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

    public function getSlotDurationAttribute(): int
    {
        return $this->slot_ends_time - $this->slot_begins_time;
    }

    public function getYearAttribute(): int
    {
        return $this->slot_begins->year;
    }

    public function getCreditsAttribute(): float
    {
        return PositionCredit::computeCredits($this->position_id, $this->slot_begins_time, $this->slot_ends_time, $this->year);
    }

    /*
     * Figure out how many signups are allowed.
     */

    public function getSlotMaxAttribute()
    {
        return ($this->trainer_slot_id ? $this->trainer_count * $this->slot_max_potential : $this->slot_max_potential);
    }
}
