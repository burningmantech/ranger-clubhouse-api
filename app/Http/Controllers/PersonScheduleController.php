<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Controllers\ApiController;

use App\Helpers\SqlHelper;

use App\Models\Person;
use App\Models\PersonOnlineTraining;
use App\Models\PersonPhoto;
use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\PositionCredit;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Slot;

use App\Mail\TrainingSignup;
use App\Mail\TrainingSessionFullMail;
use Illuminate\Http\Response;

class PersonScheduleController extends ApiController
{
    /**
     * Find the possible schedule and signups for a person & year
     */

    public function index(Person $person)
    {
        $this->authorize('view', [Schedule::class, $person]);

        $query = request()->validate([
            'year' => 'required|digits:4',
            'shifts_available' => 'sometimes|boolean',
        ]);

        $query['person_id'] = $person->id;

        $rows = Schedule::findForQuery($query);

        if (!$rows->isEmpty()) {
            // Warm the position credit cache.
            PositionCredit::warmYearCache($query['year'], array_unique($rows->pluck('position_id')->toArray()));
        }

        return $this->success($rows, null, 'schedules');
    }

    /*
     * Add a person to a slot schedule
     */

    public function store(Person $person)
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
                'status' => 'not-active',
                'signed_up' => $slot->signed_up,
            ]);
        }

        // You must hold the position
        if (!PersonPosition::havePosition($person->id, $slot->position_id)) {
            return response()->json([
                'status' => 'no-position',
                'signed_up' => $slot->signed_up,
                'position_title' => Position::retrieveTitle($slot->position_id)
            ]);
        }

        $confirmForce = $params['force'] ?? false;

        $trainerForced = false;

        $enrollments = null;
        $multipleEnrollmentForced = false;

        $logData = ['slot_id' => $slotId];

        list($canForce, $isTrainer) = $this->canForceScheduleChange($slot, true);

        $preventMultipleEnrollments = $slot->position->prevent_multiple_enrollments;
        if ($slot->isTraining()
            && $preventMultipleEnrollments
            && !Schedule::canJoinTrainingSlot($person->id, $slot, $enrollments)) {
            if (!$canForce) {
                $logData['training_multiple_enrollment'] = true;
                $logData['enrolled_slot_ids'] = $enrollments->pluck('id');
                // Not a trainer, nor has sufficent roles.. your jedi mind tricks will not work here.
                $this->log(
                    'person-slot-add-fail',
                    "training multiple enrollment attempt",
                    $logData,
                    $person->id
                );

                return response()->json([
                    'status' => 'multiple-enrollment',
                    'slots' => $enrollments,
                    'signed_up' => $slot->signed_up,
                ]);
            }
            $multipleEnrollmentForced = true;
            $trainerForced = $isTrainer;
        } elseif ($slot->position_id == Position::ALPHA
            && $preventMultipleEnrollments
            && Schedule::haveMultipleEnrollments($person->id, Position::ALPHA, $slot->begins->year, $enrollments)) {
            // Alpha is enrolled multiple times.
            if (!$canForce) {
                $logData['alpha_multiple_enrollment'] = true;
                $logData['enrolled_slot_ids'] = $enrollments->pluck('id');
                $this->log(
                    'person-slot-add-fail',
                    "alpha multiple enrollment attempt",
                    $logData,
                    $person->id
                );

                return response()->json([
                    'status' => 'multiple-enrollment',
                    'slots' => $enrollments,
                    'signed_up' => $slot->signed_up,
                ]);
            }
            $multipleEnrollmentForced = true;
        }

        // Go try to add the person to the slot/session
        $result = Schedule::addToSchedule($person->id, $slot, $confirmForce ? $canForce : false);
        $status = $result['status'];
        if ($status != 'success') {
            $response = ['status' => $status, 'signed_up' => $result['signed_up']];

            if (($status == 'has-started' || $status == 'full') && $canForce) {
                // Let the user know the sign up can be forced.
                $response['may_force'] = true;
            }

            return response()->json($response);
        }

        // Person might be active next (this) event.
        $person->update(['active_next_event' => 1]);

        $forcedReasons = [];

        if ($trainerForced) {
            $forcedReasons[] = 'trainer forced';
            $logData['trainer_forced'] = true;
        }

        if ($multipleEnrollmentForced) {
            $forcedReasons[] = 'multiple enrollment';
            $logData['multiple_enrollment'] = true;
        }

        if ($result['forced']) {
            $forcedReasons[] = 'overcapacity';
            $logData['overcapacity'] = true;
        }

        if ($slot->has_started) {
            $forcedReasons[] = 'started';
            $logData['started'] = true;
        }

        $action = "added";
        if (!empty($forcedReasons)) {
            $action .= ' (' . implode(',', $forcedReasons) . ')';
        }

        $this->log('person-slot-add', $action, $logData, $person->id);

        // Notify the person about the Training signing up
        if ($slot->isTraining() && !$slot->has_started) {
            $message = new TrainingSignup($slot, setting('TrainingSignupFromEmail'));
            mail_to($person->email, $message);
        }

        $signedUp = $result['signed_up'];

        // Is the training slot at capacity?
        if ($slot->isTraining()
            && $signedUp >= $slot->max
            && !$slot->has_started
            && !empty($slot->position->slot_full_email)) {
            // fire off an email letting the TA or ART team know a session has become full.
            mail_to($slot->position->slot_full_email, new TrainingSessionFullMail($slot, $signedUp));
        }

        $response = [
            'recommend_burn_weekend_shift' => Schedule::recommendBurnWeekendShift($person),
            'status' => 'success',
            'signed_up' => $signedUp,
        ];

        if ($result['forced']) {
            $response['full_forced'] = true;
        }

        if ($slot->has_started) {
            $response['started_forced'] = true;
        }

        if ($trainerForced || $multipleEnrollmentForced) {
            $response['slots'] = $enrollments;

            if ($trainerForced) {
                $response['trainer_forced'] = true;
            } elseif ($multipleEnrollmentForced) {
                $response['multiple_forced'] = true;
            }
        }

        return response()->json($response);

    }

    /**
     * Remove the slot from the person's schedule
     *
     * @param int $personId slot to delete for person
     * @param int $slotId to delete
     * @return Response
     */

    public function destroy(Person $person, $slotId)
    {
        $this->authorize('delete', [Schedule::class, $person]);

        $slot = Slot::findOrFail($slotId);
        $now = SqlHelper::now();

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

        $result = Schedule::deleteFromSchedule($person->id, $slotId);
        if ($result['status'] == 'success') {
            $result['recommend_burn_weekend_shift'] = Schedule::recommendBurnWeekendShift($person);

            $data = ['slot_id' => $slotId];
            if ($forced) {
                $data['forced'] = true;
            }

            $this->log('person-slot-remove', 'removed', $data, $person->id);
        }
        return response()->json($result);
    }

    /*
     * Check to see if sign ups are allowed
     */

    public function permission(Person $person)
    {
        $params = request()->validate(['year' => 'required|integer']);

        $this->authorize('view', [Schedule::class, $person]);

        $year = $params['year'];
        $personId = $person->id;
        $status = $person->status;
        $callsignApproved = $person->callsign_approved;

        if ($status == Person::PAST_PROSPECTIVE) {
            return response()->json(['permission' => [ 'signup_allowed' => false ] ]);
        }

        $canSignUpForShifts = false;
        $isAuditor = ($status == Person::AUDITOR);

        $missingBpguid = false;

        if ($isAuditor || setting('AllowSignupsWithoutPhoto')) {
            $photoStatus = PersonPhoto::NOT_REQUIRED;
        } else {
            $photoStatus = PersonPhoto::retrieveStatus($person);
        }

        $otDisabledAllowSignups = setting('OnlineTrainingDisabledAllowSignups');

        if ($otDisabledAllowSignups || $status == Person::NON_RANGER) {
            // Online training is disabled, or the person is a non ranger
            $otPassed = true;
        } else {
            $otPassed = PersonOnlineTraining::existsForPersonYear($personId, $year);
        }

        if ($isAuditor) {
            // Auditors don't require BMID photo
            if ($otPassed) {
                $canSignUpForShifts = true;
            }
            $callsignApproved = true;
        } else {
            if ($callsignApproved && ($photoStatus == PersonPhoto::APPROVED) && $otPassed) {
                $canSignUpForShifts = true;
            }

            // Everyone except Auditors and non rangers need to have BPGUID on file.
            if ($status != Person::NON_RANGER && empty($person->bpguid)) {
                $missingBpguid = true;
                $canSignUpForShifts = false;
            }
        }

        $showOtLink = false;
        // Per Roslyn and Threepio 2/23/2017, we require people to have
        // a BMID photo before they can take Online Training
        if (!$canSignUpForShifts
            && ($photoStatus == PersonPhoto::NOT_REQUIRED || $photoStatus == PersonPhoto::APPROVED) && !$otPassed) {
            $showOtLink = true;
        }


        if (setting('OnlineTrainingEnabled')) {
            $otUrl = setting('OnlineTrainingUrl');
        } else {
            $otUrl = '';
        }

        // New for 2019, everyone has to agree to the org's behavioral standards agreement.
        $missingBehaviorAgreement = !$person->behavioral_agreement;
        /*
                 July 5th, 2019 - agreement language is slightly broken. Agreement is optional.
                if ($missingBehaviorAgreement) {
                    $canSignUpForShifts = false;
                }
        */


        if (($isAuditor || $status == Person::PROSPECTIVE || $status == Person::ALPHA) && !$person->has_reviewed_pi) {
            // PNV & Auditors must review their personal info first
            $canSignUpForShifts = false;
        }
        // 2019 Council request - encourage weekend sign ups
        $recommendWeekendShift = Schedule::recommendBurnWeekendShift($person);

        $results = [
            'signup_allowed' => $canSignUpForShifts,
            'callsign_approved' => $callsignApproved,
            'photo_status' => $photoStatus,
            // is the online training link allowed to be shown (if link is enabled)
            'online_training_allowed' => $showOtLink,
            // was online training taken/passed?
            'online_training_passed' => $otPassed,

            // Online training page link - if enabled
            'online_training_url' => $otUrl,

            // Everyone except Auditors & Non Rangers should have a BPGUID (aka Burner Profile ID)
            'missing_bpguid' => $missingBpguid,

            'missing_behavioral_agreement' => $missingBehaviorAgreement,

            // Not a hard requirement, just a suggestion
            'recommend_burn_weekend_shift' => $recommendWeekendShift,

            'has_reviewed_pi' => $person->has_reviewed_pi,
        ];

        return response()->json(['permission' => $results]);
    }

    /*
     * Shift recommendations for a person (currently recommend Burn Weekend shift only)
     *
     * (use primarily by the HQ interface)
     */

    public function recommendations(Person $person)
    {
        $this->authorize('view', [Schedule::class, $person]);

        return response()->json([
            'burn_weekend_shift' => Schedule::recommendBurnWeekendShift($person)
        ]);
    }

    /*
     * Find one or more about to start shifts - used to suggest starting position.
     */

    public function imminent(Person $person)
    {
        $this->authorize('view', [Schedule::class, $person]);

        return response()->json([
            'slots' => Schedule::retrieveStartingSlotsForPerson($person->id)
        ]);
    }

    /*
     * Provide answers for folks wanting to know how many remaining hours
     * and credits will be earned based on the schedule.
     */

    public function expected(Person $person)
    {
        $this->authorize('view', [Schedule::class, $person]);

        $now = SqlHelper::now();
        $year = current_year();

        $rows = Schedule::findForQuery([
            'person_id' => $person->id,
            'year' => $year,
            'remaining' => true
        ]);

        if (!$rows->isEmpty()) {
            // Warm the position credit cache.
            PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));
        }

        $time = 0;
        $credits = 0.0;

        foreach ($rows as $row) {
            if ($row->position_count_hours) {
                // Truncate any shifts which have started
                if ($row->slot_begins->lt($now)) {
                    $row->slot_begins = $now;
                    $row->slot_begins_time = $now->timestamp;
                }

                $time += $row->slot_duration;
            }
            $credits += $row->credits;
        }

        return response()->json([
            'duration' => $time,
            'credits' => $credits,
            'slot_count' => count($rows)
        ]);
    }

    /*
     * Retrieve the schedule summary for a given year.
     *
     * Hours and expected credits are broken down into pre-event, event, and post-event
     * periods along with "other" (usually training)
     */

    public function scheduleSummary(Person $person)
    {
        $this->authorize('view', [Schedule::class, $person]);

        $year = $this->getYear();

        return response()->json(['summary' => Schedule::scheduleSummaryForPersonYear($person->id, $year)]);
    }

    /**
     * Is the user allowed to force a schedule change?
     *
     * - Admins are allowed
     * - ART Trainers (role) are allowed for any ART training
     * - Trainers (role) are only allowed to remove people, not sign up (per 2020 TA request)
     *
     * @param $slot
     * @param bool $isSignup true if check for a sign up, false for a removal
     * @return array [ canForce
     */

    private function canForceScheduleChange($slot, $isSignup = true)
    {
        $isTrainer = false;
        $roleCanForce = null;

        if ($slot->isTraining()) {
            if ($slot->isArt()) {
                $roleCanForce = Role::ART_TRAINER;
                $isTrainer = true;
            } else if (!$isSignup) {
                /*
                 * Per request from TA 2020 - Dirt Trainers may NOT manually add students BUT
                 * are allowed to remove students.
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
