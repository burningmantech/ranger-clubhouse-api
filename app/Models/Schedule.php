<?php

namespace App\Models;

use App\Exceptions\ScheduleSignUpException;
use App\Jobs\AlertWhenSignUpsEmptyJob;
use App\Lib\WorkSummary;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Psr\SimpleCache\InvalidArgumentException;

class Schedule
{
    public $id;
    public $credits;
    public $has_ended;
    public $has_started;
    public $person_assigned;
    public $position_id;
    public $slot_active;
    public $slot_begins;
    public $slot_begins_time;
    public $slot_description;
    public $slot_duration;
    public $slot_ends;
    public $slot_ends_time;
    public $slot_id;
    public $slot_max;
    public $slot_max_potential;
    public $slot_signed_up;
    public $slot_tz;
    public $slot_tz_abbr;
    public $slot_url;
    public $timezone;
    public $trainer_count;
    public $trainer_slot_id;
    public $year;

    private $position;

    const int LOCATE_SHIFT_START_WITHIN = 60; // Find a shift starting within +/- X minutes

    const int MAY_START_SHIFT_WITHIN = 25; // For HQ Window Interface: most shifts are only to be signed in to within X minutes of the start time.

    /*
     * Signup statuses
     */

    const string SUCCESS = 'success'; // person was successfully signed up for a slot
    const string FULL = 'full'; // slot is at capacity
    const string MULTIPLE_ENROLLMENT = 'multiple-enrollment'; // person is already enrolled in another slot with same position (i.e. training)
    const string NOT_ACTIVE = 'not-active'; // shift has not been activated
    const string NO_POSITION = 'no-position'; // person does not hold the position the slot is associated with
    const string HAS_ENDED = 'has-ended'; // shift has ended -- go home!
    const string HAS_STARTED = 'has-started'; // shift has already started
    const string EXISTS = 'exists'; // person is already signed up for the slot.
    const string MISSING_SIGNUP = 'missing-signup'; // person is not signed up for the shift
    const string MISSING_REQUIREMENTS = 'missing-requirements'; // person has not met all the requirements to sign up
    const string NO_EMPLOYEE_ID = 'no-employee-id'; // Paid position, must have an employee id on file.
    const string SLOT_NOT_FOUND = 'slot-not-found';

    // Operations for computeSignups()
    const string OP_QUERY = 'query'; // Query what the effective signup counts are
    const string OP_ADD = 'add';    // Adjust the effective signup counts when attempting to add a person
    const string OP_REMOVE = 'remove'; // Adjust the effective signup counts when attempting to remove a person

    /*
     * Failed sign up statuses that are allowed to be forced if the user has the appropriate
     * privileges/positions/rolls
     */

    const array MAY_FORCE_STATUSES = [
        self::FULL, self::HAS_STARTED, self::MULTIPLE_ENROLLMENT
    ];

    /**
     * Find slots in the given year the person may potentially sign up for and/or what slots
     * the person is already signed up for.
     *
     * NOTE: A slot may be returned where the person no longer holds the position yet still has the shift signup.
     * E.g., Alpha, ART mentees, or is no longer part of a special team.
     * Signups are retained for historical purposes, so past schedules can be viewed, work histories built up,
     * and the ability to submit Alpha & mentee timesheet corrections after the person has graduated.
     *
     * @param int $personId
     * @param int $year
     * @param array $query
     * @return array
     * @throws InvalidArgumentException
     */

    public static function findForQuery(int $personId, int $year, array $query = []): array
    {
        $onlySignups = $query['only_signups'] ?? false;
        $remaining = $query['remaining'] ?? false;
        $keepPositionsById = $query['positions_by_id'] ?? false;

        // Find all slots the person is eligible for AND all slots the person is already signed up for
        // Note: a person may be signed up for a position they no longer hold. E.g., Alpha, Green Dot Mentee, etc.

        $sql = Slot::select('slot.*', 'trainer_slot.signed_up as trainer_count')
            ->leftJoin('slot as trainer_slot', 'trainer_slot.id', '=', 'slot.trainer_slot_id')
            ->where('slot.begins_year', $year)
            ->orderBy('slot.begins')
            ->with([
                'position:id,title,type,contact_email,count_hours',
                'parent_signup_slot',
                'parent_signup_slot.position:id,title',
                'child_signup_slot',
                'child_signup_slot.position:id,title'
            ]);

        if ($onlySignups) {
            $sql->join('person_slot', function ($join) use ($personId) {
                $join->where('person_slot.person_id', $personId)
                    ->on('slot.id', 'person_slot.slot_id');
            });
        } else {
            $personPositions = DB::table('person_position')->where('person_id', $personId)->pluck('position_id');
            $sql->leftJoin('person_slot', function ($join) use ($personId) {
                $join->where('person_slot.person_id', $personId)
                    ->on('slot.id', 'person_slot.slot_id');
            });
            $sql->addSelect(DB::raw("IF(person_slot.person_id IS NULL, FALSE, TRUE) as person_assigned"));
            $sql->where(function ($q) use ($personPositions) {
                $q->whereNotNull('person_slot.person_id');
                if ($personPositions->isNotEmpty()) {
                    $q->orWhereIn('slot.position_id', $personPositions);
                }
            });
        }

        $rows = $sql->get();

        $slots = [];
        $positionsById = [];

        foreach ($rows as $row) {
            $positionsById[$row->position_id] = $row->position;
        }

        if (!empty($positionsById)) {
            PositionCredit::warmYearCache($year, array_keys($positionsById));
        }

        // Adjust the signup counts for parent/child slots
        foreach ($rows as $row) {
            $child = $row->child_signup_slot;
            if ($child) {
                $found = $rows->find($child->id);
                if (!$found) {
                    continue;
                }
                if (($row->signed_up + $child->signed_up) >= $row->max || $row->signed_up >= $row->max) {
                    $found->signed_up = $found->max;
                    $row->signed_up = $row->max;
                } else {
                    $row->signed_up = $row->signed_up + $found->signed_up;
                }
            }
        }

        foreach ($rows as $row) {
            if ($remaining && $row->has_ended) {
                continue;
            }

            $entry = new Schedule;
            $slots[] = $entry;

            // Copy over the slot
            $entry->id = $row->id;
            $entry->has_ended = $row->has_ended;
            $entry->has_started = $row->has_started;
            $entry->person_assigned = $onlySignups ? true : $row->person_assigned;
            $entry->position_id = $row->position_id;
            $entry->slot_active = $row->active;
            $entry->slot_begins = $row->begins;
            $entry->slot_begins_time = $row->begins_time;
            $entry->slot_description = $row->description;
            $entry->slot_ends = $row->ends;
            $entry->slot_ends_time = $row->ends_time;
            $entry->slot_duration = $row->duration;

            $entry->slot_max_potential = $row->max;
            $entry->slot_tz = $row->timezone;
            $entry->slot_tz_abbr = $row->timezone_abbr;
            $entry->slot_url = $row->url;
            $entry->trainer_count = $row->trainer_count;
            $entry->trainer_slot_id = $row->trainer_slot_id;
            $entry->year = $year;

            // Compute some values
            $entry->slot_max = ($entry->trainer_slot_id ? $entry->trainer_count * $entry->slot_max_potential : $entry->slot_max_potential);

            $entry->slot_signed_up = $row->signed_up;
        }

        PositionCredit::bulkComputeCredits($slots, $year, 'slot_begins_time', 'slot_ends_time');

        // return an array of Schedule loaded from the rows
        return [$slots, $keepPositionsById ? $positionsById : array_values($positionsById)];
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
     *   - successful sign up
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
     * @throws Exception
     */

    public static function addToSchedule(int $personId, Slot $slot, bool $force = false): array
    {
        // Query the current signups in case the existing and shift started checks are triggered.
        list ($signUps, $isFull, $becameFull, $linkedSlots, $combinedMax) = self::computeSignups($slot, self::OP_QUERY);

        if (PersonSlot::haveSlot($personId, $slot->id)) {
            return [
                'status' => self::EXISTS,
                'signed_up' => $signUps,
                'linked_slots' => $linkedSlots,
                'combined_max' => $combinedMax,
            ];
        }

        if ($slot->has_started && !$force) {
            // Shift has already started, don't allow signups
            return [
                'status' => $slot->has_ended ? self::HAS_ENDED : self::HAS_STARTED,
                'signed_up' => $signUps,
                'linked_slots' => $linkedSlots,
                'combined_max' => $combinedMax,
            ];
        }

        try {
            DB::beginTransaction();
            // Recompute the signups this time with the intention of adding a signup.
            list ($signUps, $isFull, $becameFull, $linkedSlots, $combinedMax) = self::computeSignups($slot, self::OP_ADD, $force);
            // Re-read the slot for update.
            $updateSlot = Slot::where('id', $slot->id)->lockForUpdate()->first();

            // Sign up the person
            PersonSlot::create(['person_id' => $personId, 'slot_id' => $updateSlot->id]);
            $updateSlot->signed_up += 1;
            $updateSlot->save();

            DB::commit();

            return [
                'status' => self::SUCCESS,
                'signed_up' => $signUps,
                'is_full' => $isFull,
                'overcapacity' => $isFull,  // TODO: remove this when all clients are up to date.
                'became_full' => $becameFull,
                'linked_slots' => $linkedSlots,
                'combined_max' => $combinedMax,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            if ($e instanceof ScheduleSignUpException) {
                return [
                    'status' => $e->getMessage(),
                    'signed_up' => $e->signUps,
                    'linked_slots' => $e->linkedSlots,
                    'combined_max' => $e->combinedMax,
                ];
            }
            // Most likely the database crapped itself
            throw $e;
        }
    }

    /**
     * Remove a signup for a person
     *
     * @throws Exception
     */

    public static function deleteFromSchedule(int $personId, Slot $slot, ?string $reason = null): array
    {
        try {
            list ($signUps, $isFull, $becameFull, $linkedSlots) = self::computeSignups($slot, self::OP_REMOVE);
            DB::beginTransaction();
            $personSlot = PersonSlot::where([
                ['person_id', $personId],
                ['slot_id', $slot->id]
            ])->lockForUpdate()->first();

            if (!$personSlot) {
                DB::rollback();
                return [
                    'status' => self::MISSING_SIGNUP,
                    'signed_up' => $signUps,
                    'linked_slots' => $linkedSlots,
                ];
            }

            if ($reason) {
                $personSlot->auditReason = $reason;
            }
            $personSlot->delete();
            $slot->signed_up = DB::table('person_slot')->where('slot_id', $slot->id)->count();
            $slot->saveWithoutValidation();
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            throw $e;
        }

        if (!$signUps) {
            if ($slot->position->alert_when_no_trainers) {
                $traineeSlot = Slot::where('trainer_slot_id', $slot->id)->first();
                if ($traineeSlot && $traineeSlot->signed_up > 0) {
                    AlertWhenSignUpsEmptyJob::dispatch($slot->position, $slot, $traineeSlot)->delay(now()->addMinutes(5));
                }
            }

            if ($slot->position->alert_when_becomes_empty && setting('EventManagementOnPlayaEnabled')) {
                AlertWhenSignUpsEmptyJob::dispatch($slot->position, $slot)->delay(now()->addMinutes(5));
            }
        }

        return [
            'status' => self::SUCCESS,
            'signed_up' => $signUps,
            'is_full' => $isFull,
            'overcapacity' => $isFull,  // TODO: remove this when all clients are up to date.
            'linked_slots' => $linkedSlots
        ];
    }

    public static function computeSignups(Slot $slot, string $op, bool $force = false): array
    {
        /*
         * Sign up limitations
         *
         * - a child slot cannot exceed its own max count in addition to
         *   not exceeding parent's max count for the combined parent and child signups
         * - a parent slot cannot have the combined parent and child signups exceed the parent's max count
         */

        $signUps = $slot->signed_up;
        $finalSignUps = $signUps;
        $linkedSlots = [];
        $isFull = false;
        $becameFull = false; // Only for parent/child shifts. So the frontend can alert the user to what's going on.
        $max = $slot->max;

        $countAdjustment = match ($op) {
            self::OP_QUERY => 0,
            self::OP_ADD => 1,
            self::OP_REMOVE => -1,
            default => throw new Exception('Unknown op: ' . $op),
        };

        $parentSlot = $slot->parent_signup_slot;
        if ($parentSlot) {
            $parentSignups = $parentSlot->signed_up;
            $combined = $signUps + $parentSignups;
            $adjustedCombined = $combined + $countAdjustment;
            $finalSignUps = $signUps + $countAdjustment;

            if ($op == self::OP_ADD && !$force && ($signUps >= $max || $combined >= $parentSlot->max)) {
                throw new ScheduleSignUpException(self::FULL, $max, [
                    ['slot_id' => $parentSlot->id, 'signed_up' => $combined],
                ], $parentSlot->max);
            }

            if ($finalSignUps >= $max || $adjustedCombined >= $parentSlot->max) {
                $isFull = true;
                $finalSignUps = $max;
                $adjustedCombined = $parentSlot->max;
            }

            $linkedSlots[] = [
                'slot_id' => $parentSlot->id,
                'signed_up' => $adjustedCombined,
                'max' => $parentSlot->max,
                'position_title' => $parentSlot->position->title,
            ];

            if (self::OP_ADD && ($combined + 1) == $parentSlot->max) {
                $becameFull = true;
            }
        } else {
            $childSlot = $slot->child_signup_slot;
            if ($childSlot) {
                $childSignups = $childSlot->signed_up;
                $combined = $signUps + $childSignups;
                $adjustedCombined = $combined + $countAdjustment;
                $finalSignUps = $adjustedCombined;

                if ($op == self::OP_ADD && !$force && ($signUps >= $max || $combined >= $max)) {
                    throw new ScheduleSignUpException(self::FULL, $max, [
                        ['slot_id' => $childSlot->id, 'signed_up' => $childSlot->max]
                    ],
                    $slot->max);
                }

                if ($finalSignUps >= $max || ($signUps + $countAdjustment) >= $max) {
                    $isFull = true;
                    $finalSignUps = $max;
                    $childSignups = $childSlot->max;
                }

                $linkedSlots[] = [
                    'slot_id' => $childSlot->id,
                    'signed_up' => $childSignups,
                    'max' => $childSlot->max,
                    'position_title' => $childSlot->position->title,
                ];

                if (self::OP_ADD && ($combined + 1) == $slot->max) {
                    $becameFull = true;
                }
            } else {
                if ($slot->trainer_slot_id) {
                    $max = $max * $slot->trainer_slot->signed_up;
                }

                // Cannot exceed the signup limit unless it is forced.
                if ($op == self::OP_ADD && $signUps >= $max) {
                    if (!$force) {
                        throw new ScheduleSignUpException(self::FULL, $signUps, [], $slot->max);
                    }

                    $isFull = true;
                }
                $finalSignUps += $countAdjustment;
            }
        }

        return [$finalSignUps, $isFull, $becameFull, $linkedSlots, $slot->parent_signup_slot?->max ?? $slot->max];
    }


    /**
     * Remove all future signups for a person.
     */

    public static function removeFutureSignUps(int $personId, string $reason): void
    {
        // Find all upcoming shifts and remove 'em.
        $now = now();
        $rows = PersonSlot::select('person_slot.*')
            ->join('slot', 'slot.id', 'person_slot.slot_id')
            ->where('slot.begins_year', $now->year)
            ->where('slot.begins', '>', $now)
            ->where('person_id', $personId)
            ->with('slot')
            ->get();

        foreach ($rows as $row) {
            self::deleteFromSchedule($personId, $row->slot, $reason);
            ActionLog::record(
                Auth::user(),
                'person-slot-remove',
                $reason,
                ['slot_id' => $row->slot_id],
                $personId
            );
        }
    }


    /**
     * Remove all future signups for a person and a given position.
     */

    public static function removeFutureSignUpsForPosition(int $personId, int $positionId, string $reason): void
    {
        // Find all upcoming shifts and remove 'em.
        $now = now();
        $rows = PersonSlot::select('person_slot.*')
            ->join('slot', 'slot.id', 'person_slot.slot_id')
            ->where('slot.begins_year', $now->year)
            ->where('slot.begins', '>=', $now)
            ->where('slot.position_id', $positionId)
            ->where('person_id', $personId)
            ->with('slot')
            ->get();

        foreach ($rows as $row) {
            self::deleteFromSchedule($personId, $row->slot, $reason);
            ActionLog::record(
                Auth::user(),
                'person-slot-remove',
                $reason,
                ['slot_id' => $row->slot_id],
                $personId
            );
        }
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

        $year = $slot->begins_year;
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
     * @return \Illuminate\Database\Eloquent\Collection
     */

    public static function findEnrolledSlots($personId, $year, $positionId): \Illuminate\Database\Eloquent\Collection
    {
        return PersonSlot::where('person_slot.person_id', $personId)
            ->join('slot', function ($query) use ($positionId, $year) {
                $query->whereColumn('slot.id', 'person_slot.slot_id');
                $query->where('slot.position_id', $positionId);
                $query->where('slot.begins_year', $year);
            })->orderBy('slot.begins')
            ->get();
    }

    /**
     * Find the slots where the person signed up beginning within +/- LOCATE_SHIFT_START_WITHIN minutes of now.
     *
     * NOTE: This assumes the slots retrieved are in the same timezone as the event.
     *
     * @param int $personId
     * @return array
     */

    public static function retrieveStartingSlotsForPerson(int $personId): array
    {
        $now = now();
        $rows = PersonSlot::join('slot', 'slot.id', 'person_slot.slot_id')
            ->where('slot.begins_year', $now->year)
            ->where('slot.begins', '>=', now()->subMinutes(self::LOCATE_SHIFT_START_WITHIN))
            ->where('person_id', $personId)
            ->with('slot.position:id,title')
            ->orderBy('slot.begins')
            ->get();


        $upcoming = [];
        $imminent = [];

        $earlyTime = (int)setting('ShiftCheckInEarlyPeriod');
        foreach ($rows as $row) {
            $slot = $row->slot;
            $start = $slot->begins->clone()->subMinutes($earlyTime);
            $withinStart = $start->lte($now);
            $shift = [
                'slot_id' => $row->slot_id,
                'slot_description' => $slot->description,
                'position_id' => $slot->position_id,
                'position_title' => $slot->position->title,
                'slot_begins' => (string)$slot->begins,
                'slot_ends' => (string)$slot->ends,
                'slot_url' => $slot->url,
                'is_within_start_time' => $earlyTime ? $withinStart : true,
            ];

            if (!$withinStart) {
                $shift['can_start_in'] = (int)$now->diffInMinutes($start);
                if ($shift['can_start_in'] == 0) {
                    $shift['can_start_in'] = 1;
                }
            }

            if ($slot->begins->clone()->subMinutes(self::LOCATE_SHIFT_START_WITHIN)->gte($now)) {
                $upcoming[] = $shift;
            } else {
                $imminent[] = $shift;
            }
        }

        return [
            'upcoming' => $upcoming,
            'imminent' => $imminent,
            'locate_start_minutes' => self::LOCATE_SHIFT_START_WITHIN,
            'may_start_minutes' => self::MAY_START_SHIFT_WITHIN
        ];
    }

    /**
     * Find the (probable) slot  sign up for a person based on the position and time.
     *
     * NOTE: This assumes the slots inspected are in the same time zone as the event.
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
     * Does the person have a sign-up occurring in the Burn Weekend?
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
            ->where('slot.begins_year', current_year())
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
            ->where('slot.begins_year', current_year())
            ->where('slot.position_id', Position::DIRT)
            ->exists();
    }


    /**
     * Count the number of signups which earns credits or is not a training shift.
     *
     * @param Person $person
     * @return array
     * @throws InvalidArgumentException
     */

    public static function summarizeShiftSignups(Person $person): array
    {
        $year = current_year();
        list ($rows, $positions) = self::findForQuery($person->id, $year, ['only_signups' => true, 'positions_by_id' => true]);

        $positionsById = [];
        foreach ($positions as $position) {
            $positionsById[(int)$position->id] = $position;
        }

        // Skip any training shifts which don't earn credits.
        $rows = array_filter($rows, fn($r) => $r->credits > 0 || $positionsById[(int)$r->position_id]->type != Position::TYPE_TRAINING);

        $totalDuration = 0.0;
        $countedDuration = 0.0;
        $credits = 0.0;

        foreach ($rows as $row) {
            $credits += $row->credits;
            $totalDuration += $row->slot_duration;
            if ($positionsById[$row->position_id]->count_hours) {
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

    public static function hasSignupInPeriod(int $personId, $start, $end): bool
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
     * @param bool $remaining
     * @return object
     * @throws InvalidArgumentException
     */

    public static function scheduleSummaryForPersonYear(int $personId, int $year, bool $remaining = false): object
    {
        $query = [
            'only_signups' => true
        ];

        if ($remaining) {
            $query['remaining'] = true;
        }

        $now = now();

        list ($rows, $positions) = self::findForQuery($personId, $year, $query);

        $eventDates = EventDate::findForYear($year);

        if (!$eventDates) {
            // No event dates - return everything as happening during the event
            $time = array_sum(array_column($rows, 'slot_duration'));
            $credits = array_sum(array_column($rows, 'credits'));

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
                'credits_earned' => 0.0,
            ];
        }

        $summary = new WorkSummary($eventDates->event_start->timestamp, $eventDates->event_end->timestamp, $year);

        $positionsById = [];
        foreach ($positions as $position) {
            $positionsById[$position->id] = $position;
        }

        foreach ($rows as $row) {
            if ($remaining && $row->slot_begins->lt($now)) {
                // Truncate any shifts that have started
                $row->slot_begins = $now;
                $row->slot_begins_time = $now->timestamp;
            }
            $position = $positionsById[$row->position_id];
            $summary->computeTotals($row->position_id, $row->slot_begins_time, $row->slot_ends_time, $position->count_hours);
        }

        return (object)[
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
            'credits_earned' => Timesheet::earnedCreditsForYear($personId, $year)
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
        usort($slots, fn($a, $b) => strcmp($a['slot_begins'], $b['slot_begins']));
        return $slots;
    }

    /**
     * Render the object to an array - used by Laravel response encoder.
     * @return array
     */

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'credits' => $this->credits,
            'has_ended' => $this->has_ended,
            'has_started' => $this->has_started,
            'person_assigned' => $this->person_assigned,
            'position_id' => $this->position_id,
            'slot_active' => $this->slot_active,
            'slot_begins' => (string)$this->slot_begins,
            'slot_begins_time' => $this->slot_begins_time,
            'slot_description' => $this->slot_description,
            'slot_duration' => $this->slot_duration,
            'slot_ends' => (string)$this->slot_ends,
            'slot_ends_time' => $this->slot_ends_time,
            'slot_id' => $this->slot_id,
            'slot_max' => $this->slot_max,
            'slot_max_potential' => $this->slot_max_potential,
            'slot_signed_up' => $this->slot_signed_up,
            'slot_tz' => $this->slot_tz,
            'slot_tz_abbr' => $this->slot_tz_abbr,
            'slot_url' => $this->slot_url,
            'timezone' => $this->timezone,
            'trainer_count' => $this->trainer_count,
            'trainer_slot_id' => $this->trainer_slot_id,
            'year' => $this->year,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
