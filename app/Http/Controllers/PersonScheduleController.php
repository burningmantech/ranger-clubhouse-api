<?php

namespace App\Http\Controllers;

use App\Jobs\TrainingSignupEmailJob;
use App\Lib\Scheduling;
use App\Lib\WorkSummary;
use App\Mail\TrainingSessionFullMail;
use App\Models\EventDate;
use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\PositionCredit;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Slot;
use App\Models\Timesheet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Psr\SimpleCache\InvalidArgumentException;

class PersonScheduleController extends ApiController
{
    /**
     * Find the possible schedule and signups for a person & year
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException|InvalidArgumentException
     */

    public function index(Person $person): JsonResponse
    {
        $this->authorize('view', [Schedule::class, $person]);

        $query = request()->validate([
            'year' => 'required|digits:4',
            'credits_earned' => 'sometimes|boolean',
            'schedule_summary' => 'sometimes|boolean',
            'signup_permission' => 'sometimes|boolean',
        ]);

        $year = $query['year'];


        list ($rows, $positions) = Schedule::findForQuery($person->id, $year, $query);

        $results = [
            'slots' => array_map(fn($r) => $r->toArray(), $rows),
            'positions' => $positions
        ];

        // Try to reduce the round trips to the backend by including common associated scheduling info
        if ($query['credits_earned'] ?? false) {
            $results['credits_earned'] = Timesheet::earnedCreditsForYear($person->id, $year);
        }

        if ($query['schedule_summary'] ?? false) {
            $results['schedule_summary'] = Schedule::scheduleSummaryForPersonYear($person->id, $year);
        }

        if ($query['signup_permission'] ?? false) {
            $results['signup_permission'] = Scheduling::retrieveSignUpPermission($person, $year);
        }

        return response()->json($results);
    }

    /**
     * Add a person to a slot schedule
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store(Person $person): JsonResponse
    {
        $params = request()->validate([
            'slot_id' => 'required|integer',
            'force' => 'sometimes|boolean'
        ]);

        $this->authorize('create', [Schedule::class, $person]);

        $slotId = $params['slot_id'];

        $slot = Slot::findOrFail($slotId);

        // Slot must be activated in order to allow signups
        if (!$slot->active) {
            return response()->json([
                'status' => Schedule::NOT_ACTIVE,
                'signed_up' => $slot->signed_up,
            ]);
        }

        // You must hold the position
        if (!PersonPosition::havePosition($person->id, $slot->position_id)) {
            return response()->json([
                'status' => Schedule::NO_POSITION,
                'signed_up' => $slot->signed_up,
                'position_title' => Position::retrieveTitle($slot->position_id)
            ]);
        }

        $trainerForced = false;
        $enrollments = null;
        $isMultipleEnrolled = false;

        $logData = ['slot_id' => $slotId];

        list($canForce, $isTrainer) = $this->canForceScheduleChange($slot, true);

        if ($canForce) {
            $confirmForce = $params['force'] ?? false;
        } else {
            $confirmForce = false;
        }

        $permission = Scheduling::retrieveSignUpPermission($person, current_year());

        if (!$confirmForce) {
            /*
             * Person must meet various requirements before being allowed to sign up
             */
            $isTraining = ($slot->position_id == Position::TRAINING);
            if (!$permission[$isTraining ? 'training_signups_allowed' : 'all_signups_allowed'] ?? false) {
                return response()->json([
                    'status' => Schedule::MISSING_REQUIREMENTS,
                    'signed_up' => $slot->signed_up,
                    'may_force' => $canForce,
                    'requirements' => $permission['requirements'],
                    'training_signups_allowed' => $permission['training_signups_allowed'] ?? false,
                ]);
            }
        }

        $preventMultipleEnrollments = $slot->position->prevent_multiple_enrollments;
        if ($slot->isTraining()
            && $preventMultipleEnrollments
            && !Schedule::canJoinTrainingSlot($person->id, $slot, $enrollments)) {
            $isMultipleEnrolled = true;
            $trainerForced = $isTrainer;
        } elseif ($slot->position_id == Position::ALPHA
            && $preventMultipleEnrollments
            && Schedule::haveMultipleEnrollments($person->id, Position::ALPHA, $slot->begins->year, $enrollments)) {
            $isMultipleEnrolled = true;
        }

        if ($isMultipleEnrolled && !$confirmForce) {
            $logData['multiple_enrollment'] = true;
            $logData['enrolled_slot_ids'] = $enrollments->pluck('id');
            $this->log(
                'person-slot-add-fail',
                'multiple enrollment attempt',
                $logData,
                $person->id
            );
            $result = [
                'status' => Schedule::MULTIPLE_ENROLLMENT,
                'slots' => $enrollments,
                'signed_up' => $slot->signed_up,
            ];

            if ($canForce) {
                $result['may_force'] = true;
            }
            return response()->json($result);
        }

        /*
        Per July 18th, 2024 timecard conversion - allow people to signup.
        if ($slot->position->paycode && is_null($person->employee_id)) {
            return response()->json([
                'status' => Schedule::NO_EMPLOYEE_ID,
                'signed_up' => $slot->signed_up,
            ]);
        }
        */

        // Go try to add the person to the slot/session
        $result = Schedule::addToSchedule($person->id, $slot, $confirmForce ? $canForce : false);
        $status = $result['status'];

        if ($status != Schedule::SUCCESS) {
            $response = ['status' => $status, 'signed_up' => $result['signed_up']];

            if (in_array($status, Schedule::MAY_FORCE_STATUSES) && $canForce) {
                // Let the user know the sign up can be forced.
                $response['may_force'] = true;
            }

            return response()->json($response);
        }

        $forcedReasons = [];

        if ($trainerForced) {
            $forcedReasons[] = 'trainer forced';
            $logData['trainer_forced'] = true;
        }

        if ($isMultipleEnrolled) {
            $forcedReasons[] = 'multiple enrollment';
            $logData['multiple_enrollment'] = true;
        }

        if ($result['overcapacity']) {
            $forcedReasons[] = 'overcapacity';
            $logData['overcapacity'] = true;
        }

        if ($slot->has_started) {
            $forcedReasons[] = 'started';
            $logData['started'] = true;
        }

        $action = 'added';
        if (!empty($forcedReasons)) {
            $action .= ' (' . implode(',', $forcedReasons) . ')';
        }

        $this->log('person-slot-add', $action, $logData, $person->id);

        // Notify the person about the Training signing up
        if ($slot->isTraining() && !$slot->has_started) {
            TrainingSignupEmailJob::dispatch($person, $slot)->delay(now()->addMinutes(5));
        }

        $signedUp = $result['signed_up'];

        // Is the training slot at capacity?
        if ($slot->isTraining()
            && $signedUp >= $slot->max
            && !$slot->has_started
            && !empty($slot->position->contact_email)) {
            // fire off an email letting the TA or ART team know a session has become full.
            mail_to($slot->position->contact_email, new TrainingSessionFullMail($slot, $signedUp), true);
        }

        $response = [
            'status' => 'success',
            'signed_up' => $signedUp,
            'recommend_burn_weekend_shift' => Scheduling::recommendBurnWeekendShift($person),
        ];

        if ($result['overcapacity']) {
            $response['overcapacity'] = true;
        }

        if ($slot->has_started) {
            $response['has_started'] = true;
        }

        if ($isMultipleEnrolled) {
            $response['multiple_enrollment'] = true;
        }

        return response()->json($response);

    }

    /**
     * Remove the slot from the person's schedule
     *
     * @param Person $person
     * @param int $slotId to delete
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(Person $person, int $slotId): JsonResponse
    {
        $this->authorize('delete', [Schedule::class, $person]);

        $slot = Slot::findOrFail($slotId);
        $now = now();

        list($canForce, $isTrainer) = $this->canForceScheduleChange($slot, false);

        $forced = false;
        if ($now->gt($slot->begins)) {
            // Not allowed to delete anything from the schedule unless you have permission to do so.
            if (!$canForce) {
                return response()->json([
                    'status' => 'has-started',
                    'signed_up' => $slot->signed_up
                ]);
            } else {
                $forced = true;
            }
        }

        $result = Schedule::deleteFromSchedule($person->id, $slot);
        if ($result['status'] == 'success') {
            $data = ['slot_id' => $slotId];
            if ($forced) {
                $data['forced'] = true;
            }

            $this->log('person-slot-remove', 'removed', $data, $person->id);
        }

        $result['recommend_burn_weekend_shift'] = Scheduling::recommendBurnWeekendShift($person);

        return response()->json($result);
    }

    /**
     * Check to see if sign ups are allowed
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function permission(Person $person): JsonResponse
    {
        $this->authorize('view', [Schedule::class, $person]);

        return response()->json(['permission' => Scheduling::retrieveSignUpPermission($person, $this->getYear())]);
    }

    /**
     * Shift recommendations for a person (currently recommend Burn Weekend shift only)
     *
     * (use primarily by the HQ interface)
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function recommendations(Person $person): JsonResponse
    {
        $this->authorize('view', [Schedule::class, $person]);

        return response()->json([
            'burn_weekend_shift' => Scheduling::recommendBurnWeekendShift($person)
        ]);
    }

    /**
     * Find one or more about to start shifts and future shifts - used to suggest starting position and
     * suggest marking a person off site.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function upcoming(Person $person): JsonResponse
    {
        $this->authorize('view', [Schedule::class, $person]);

        return response()->json(Schedule::retrieveStartingSlotsForPerson($person->id));
    }

    /**
     * Provide answers for folks wanting to know how many remaining hours
     * and credits will be earned based on the schedule.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException|InvalidArgumentException
     */

    public function expected(Person $person): JsonResponse
    {
        $this->authorize('view', [Schedule::class, $person]);

        $now = now();
        $year = $now->year;

        list($rows, $positionsById) = Schedule::findForQuery($person->id, $year, ['remaining' => true, 'only_signups' => true, 'positions_by_id' => true]);

        if (!$rows->isEmpty()) {
            // Warm the position credit cache.
            PositionCredit::warmYearCache($year, array_keys($positionsById));
        }

        $eventDates = EventDate::findForYear($year);

        $summary = new WorkSummary($eventDates->event_start->timestamp, $eventDates->event_end->timestamp, $year);
        foreach ($rows as $slot) {
            if ($slot->slot_begins->lt($now)) {
                // Truncate any shifts which have started
                $slot->slot_begins = $now;
                $slot->slot_begins_time = $now->timestamp;
            }
            $position = $positionsById[$slot->position_id];
            $summary->computeTotals($slot->position_id, $slot->slot_begins_time, $slot->slot_ends_time, $position->count_hours);
        }

        return response()->json([
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
        ]);
    }

    /**
     * Retrieve the schedule summary for a given year.
     *
     * Hours and expected credits are broken down into pre-event, event, and post-event
     * periods along with "other" (usually training)
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function scheduleSummary(Person $person): JsonResponse
    {
        $this->authorize('view', [Schedule::class, $person]);

        $year = $this->getYear();

        return response()->json(['summary' => Schedule::scheduleSummaryForPersonYear($person->id, $year)]);
    }

    /**
     * Retrieve the scheduling log for a person & year.
     * Allows a login management user to see the shift sign up and removals
     * for another person without having to have full access to the action log.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function scheduleLog(Person $person): JsonResponse
    {
        $this->authorize('view', [Schedule::class, $person]);
        $year = $this->getYear();

        return response()->json(['logs' => Schedule::retrieveScheduleLog($person->id, $year)]);
    }

    /**
     * Is the user allowed to force a schedule change?
     *
     * - Admins are allowed
     * - ART Trainers (role) are allowed for any ART training
     * - Trainers (effective role) are only allowed to remove people, not sign up (per 2020 TA request)
     * - Trainers (true role) are allowed to force sign up trainees to dirt trainings.
     *
     * @param Slot $slot
     * @param bool $isSignup true if is a sign-up, otherwise a removal.
     * @return array<bool,bool>
     */

    private function canForceScheduleChange(Slot $slot, bool $isSignup = true): array
    {
        $isTrainer = false;
        $roleCanForce = null;

        if ($slot->isTraining()) {
            if ($slot->isArt()) {
                $roleCanForce = [Role::ART_TRAINER, Role::ART_TRAINER_BASE | $slot->position_id];
                $isTrainer = true;
            } else if ($isSignup) {
                /*
                 * Per TA 2023 - T.A. cadre members now only have the Trainer role, all other trainers have Training Seasonal.
                 * Allow the real Training role to force add trainees.
                 */
                if ($this->userHasTrueRole(Role::TRAINER)) {
                    return [true, true];
                }
            } else {
                /*
                 * Per request from TA 2020 - Dirt Trainers may NOT manually add trainees BUT
                 * are allowed to remove trainees.
                 */
                $roleCanForce = Role::TRAINER;
                $isTrainer = true;
            }
        } elseif ($slot->position_id == Position::ALPHA) {
            $roleCanForce = Role::MENTOR;
        }

        if ($roleCanForce && $this->userHasRole($roleCanForce)) {
            return [true, $isTrainer];
        }

        return [$this->userHasRole(Role::ADMIN), false];
    }
}
