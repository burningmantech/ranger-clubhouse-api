<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Controllers\ApiController;

use App\Helpers\SqlHelper;

use App\Models\ManualReview;
use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\Photo;
use App\Models\Position;
use App\Models\PositionCredit;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Slot;

use Illuminate\Support\Facades\Mail;
use App\Mail\TrainingSignup;
use App\Mail\SlotSignup;
use App\Mail\TrainingSessionFullMail;

class PersonScheduleController extends ApiController
{
    /**
     * Find the possible schedule and signups for a person & year
     */

    public function index(Person $person)
    {
        $this->authorize('view', [ Schedule::class, $person]);

        $query = request()->validate(
            [
            'year'    => 'required|digits:4',
            'shifts_available' => 'sometimes|boolean',
            ]
        );

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
            'slot_id'  => 'required|integer',
        ]);

        $this->authorize('create', [ Schedule::class, $person ]);

        $slotId = $params['slot_id'];

        $slot = Slot::findOrFail($slotId);

        /*
         * Enrollment in multiple training sessions is not allowed unless:
         *
         * - The person is a Trainer of the appropriate type.
         *   (e.g., Traing -> Trainer/Assoc. Trainer/Uber, Green Dot Training -> G.D. Trainer, etc)
         * - The logged in user holds the Trainer, Mentor or VC role, or ART Trainer is the slot is a ART module
         */

        $rolesCanForce = null;
        $trainerForced = false;
        $enrollments = null;
        $multipleEnrollmentForced = false;
        $mentorForced = false;
        $hasStartedForced = false;
        $canForce = false;
        $forced = false;

        $logData = [ 'slot_id' => $slotId ];
        $isTrainer = false;

        // Check for people who might be able force the add
        if ($slot->isTraining()) {
            $rolesCanForce = [ Role::ADMIN, Role::TRAINER, Role::MENTOR, Role::VC ];
            if ($slot->isArt()) {
                $rolesCanForce[] = Role::ART_TRAINER;
            }
            $trainers = Position::TRAINERS[$slot->position_id] ?? null;
            if ($trainers && PersonPosition::havePosition($person->id, $trainers)) {
                // Person is a trainer.. allowed to force sign up to trainings
                $isTrainer = true;
                $canForce = true;
            }
        } else if ($slot->position_id == Position::ALPHA) {
            $rolesCanForce = [ Role::ADMIN, Role::MENTOR ];
        } else {
            $rolesCanForce = [ Role::ADMIN ];
        }

        if (!$canForce) {
            $canForce = $rolesCanForce ? $this->userHasRole($rolesCanForce) : false;
        }

        if ($slot->isTraining()
        && !Schedule::canJoinTrainingSlot($person->id, $slot, $enrollments)) {
            if ($isTrainer) {
                $trainerForced = true;
                $canForce = true;
            } else if (!$canForce) {
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
                    'slots'  => $enrollments,
                    'signed_up' => $slot->signed_up,
                ]);
            }
            $multipleEnrollmentForced = true;
        } else if ($slot->position_id == Position::ALPHA
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
                    'slots'  => $enrollments,
                    'signed_up' => $slot->signed_up,
                ]);
            }
            $multipleEnrollmentForced = true;
        }

        // Go try to add the person to the slot/session
        $result = Schedule::addToSchedule($person->id, $slot, $canForce);

        $status = $result['status'];
        if ($status == 'success') {
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
                $action .= ' ('.implode(',', $forcedReasons).')';
            }

            $this->log(
                'person-slot-add',
                $action,
                $logData,
                $person->id
            );

            // Notify the person about signing up
            if ($slot->isTraining() && !$slot->has_started) {
                $message = new TrainingSignup($slot, setting('TrainingSignupFromEmail'));
                Mail::to($person->email)->send($message);
            }
            /*else {
                $message = new SlotSignup($slot, setting('ShiftSignupFromEmail'));
            }*/


            $signedUp = $result['signed_up'];

            // Is the training slot at capacity?
            if ($slot->isTraining() && $signedUp >= $slot->max && !$slot->has_started) {
                // fire off an email letting the Training Acamedy know
                Mail::to(setting('TrainingFullEmail'))->send(new TrainingSessionFullMail($slot, $signedUp));
            }

            $response = [
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
                } else if ($multipleEnrollmentForced) {
                    $response['multiple_forced'] = true;
                }
            }

            return response()->json($response);
        }

        return response()->json([ 'status' => $status, 'signed_up' => $result['signed_up'] ]);
    }

    /**
     * Remove the slot from the person's schedule
     *
     * @param  int $personId slot to delete for person
     * @param  int $slotId   to delete
     * @return \Illuminate\Http\Response
     */

    public function destroy(Person $person, $slotId)
    {
        $this->authorize('delete', [ Schedule::class, $person ]);

        $slot = Slot::findOrFail($slotId);
        $now = SqlHelper::now();

        if ($now->gt($slot->begins) && !$this->userHasRole(Role::ADMIN)) {
            return response()->json([
                'status' => 'has-started',
                'signed_up' => $slot->signed_up
            ]);
        }

        $result = Schedule::deleteFromSchedule($person->id, $slotId);
        if ($result['status'] == 'success') {
            $this->log(
                'person-slot-remove',
                'removed',
                [ 'slot_id' => $slotId ],
                $person->id
            );
        }
        return response()->json($result);
    }

    /*
     * Check to see if sign ups are allowed
     */

    public function permission(Person $person)
    {
        $params = request()->validate([ 'year' => 'required|integer' ]);

        $this->authorize('view', [ Schedule::class, $person ]);

        $year = $params['year'];
        $personId = $person->id;
        $status = $person->status;
        $callsignApproved = $person->callsign_approved;

        $canSignUpForShifts = false;
        $isPotentialRanger = ($status == "prospective" || $status == "alpha");

        $manualReviewCap = setting('ManualReviewProspectiveAlphaLimit');
        $manualReviewMyRank = 1;
        $manualReviewCount = 1;
        $missedManualReviewWindow = false;

        $missingBpguid = false;

        if ($status == "auditor" || setting('AllowSignupsWithoutPhoto')) {
            $photoStatus = 'not-required';
        } else {
            $result = Photo::retrieveInfo($person);
            $photoStatus = $result['photo_status'];
        }

        $mrDisabledAllowSignups = setting('ManualReviewDisabledAllowSignups');

        if ($mrDisabledAllowSignups) {
            // Manual review is not need at the moment
            $manualReviewPassed = true;
        } else {
            $manualReviewPassed = ManualReview::personPassedForYear($personId, $year);
        }

        if ($status == "auditor") {
            // Auditors don't require BMID photo
            if ($manualReviewPassed) {
                $canSignUpForShifts = true;
            }
            $callsignApproved = true;
        } else if ($status != "past prospective") {
            if ($callsignApproved && ($photoStatus == 'approved') && $manualReviewPassed) {
                $canSignUpForShifts = true;
            }

            // Everyone except Auditors and non rangers need to have BPGUID on file.
            if ($status != "non ranger") {
                if (empty($person->bpguid)) {
                    $missingBpguid = true;
                    $canSignUpForShifts = false;
                }
            }
        }

        if (!$mrDisabledAllowSignups && $manualReviewCap > 0 && $isPotentialRanger) {
            $manualReviewMyRank = ManualReview::prospectiveOrAlphaRankForYear($personId, $year);
            if ($manualReviewMyRank == -1) {
                $manualReviewMyRank = 100000;       // Hack to make life easier below
            }
            $manualReviewCount = ManualReview::countPassedProspectivesAndAlphasForYear($year);

            if ($manualReviewPassed && $manualReviewMyRank > $manualReviewCap) {
                // Don't mark the person has missed the manual review window if
                // manual review is disabled AND signups are allowed
                if (!$mrDisabledAllowSignups) {
                    $missedManualReviewWindow = true;
                }
                $canSignUpForShifts = false;
            }
        }


        $showManualReviewLink = false;
        if (!$canSignUpForShifts) {
            // Per Roslyn and Threepio 2/23/2017, we require people to have
            // a lam photo before they can take the Manual Review
            if ($isPotentialRanger || $status == "prospective waitlist") {
                if (($photoStatus == 'not-required' || $photoStatus == 'approved') && !$manualReviewPassed
                        && ($manualReviewCap == 0 ||
                            $manualReviewCount < $manualReviewCap)) {
                    $showManualReviewLink = true;
                }
            } elseif ($status != "past prospective" && ($photoStatus == 'approved' || $photoStatus == 'not-required') && !$manualReviewPassed) {
                $showManualReviewLink = true;
            }
        }

        if (setting('ManualReviewLinkEnable')) {
            $manualReviewUrl = setting('ManualReviewGoogleFormBaseUrl').urlencode($person->callsign);
        } else {
            $manualReviewUrl = '';
        }

        // New for 2019, everyone has to agree to the org's behavioral standards agreement.
        $missingBehaviorAgreement = !$person->behavioral_agreement;
        if ($missingBehaviorAgreement) {
            $canSignUpForShifts = false;
        }

        $results = [
            'signup_allowed'              => $canSignUpForShifts,
            'callsign_approved'           => $callsignApproved,
            'photo_status'                => $photoStatus,
            // is the manual review link allowed to be shown (if link is enabled)
            'manual_review_allowed'       => $showManualReviewLink,
            // was manual review taken/passed?
            'manual_review_passed'        => $manualReviewPassed,
            // did the prospective/alpha late in taking the review?
            'manual_review_window_missed' => $missedManualReviewWindow,
            // cap on how many prospective/alpha can take the manual review
            'manual_review_cap'           => $manualReviewCap,
            // Manual Review page link - if enabled
            'manual_review_url'           => $manualReviewUrl,

            // Everyone except Auditors & Non Rangers should have a BPGUID (aka Burner Profile ID)
            'missing_bpguid'              => $missingBpguid,

            'missing_behavioral_agreement'  => $missingBehaviorAgreement,
        ];

        return response()->json([ 'permission' => $results ]);
    }

    /*
     * Find one or more about to start shifts - used to suggest starting position.
     */

    public function imminent(Person $person)
    {
        $this->authorize('view', [ Schedule::class, $person ]);

        return response()->json([
            'slots'    => Schedule::retrieveStartingSlotsForPerson($person->id)
        ]);
    }

    /*
     * Provide answers for folks wanting to know how many remaining hours
     * and credits will be earned based on the schedule.
     */

    public function expected(Person $person)
    {
        $this->authorize('view', [ Schedule::class, $person ]);

        $now = SqlHelper::now();
        $year = date('Y');

        $rows = Schedule::findForQuery([
            'person_id' => $person->id,
            'year'      => $year,
            'remaining' => true
        ]);

        if (!$rows->isEmpty()) {
            // Warm the position credit cache.
            PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));
        }

        $time = 0;
        $credits = 0.0;

        foreach ($rows as $row) {
            // Truncate any shifts which have started
            if ($row->slot_begins->lt($now)) {
                $row->slot_begins = $now;
                $row->slot_begins_time = $now->timestamp;
            }

            $time += $row->slot_duration;
            $credits += $row->credits;
        }

        return response()->json([
            'duration'  => $time,
            'credits'   => $credits,
            'slot_count'=> count($rows)
        ]);
    }
}
