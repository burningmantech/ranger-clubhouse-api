<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Controllers\ApiController;

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

        $trainerForced = false;
        $enrollments = null;
        $multipleEnrollmentForced = false;
        $mentorForced = false;

        $logData = [ 'slot_id' => $slotId ];

        if ($slot->isTraining()
        && !Schedule::canJoinTrainingSlot($person->id, $slot, $enrollments)) {
            $trainers = Position::TRAINERS[$slot->position_id] ?? null;

            $rolesCanForce = [ Role::ADMIN, Role::TRAINER, Role::MENTOR, Role::VC ];
            if ($slot->isArt()) {
                $rolesCanForce[] = Role::ART_TRAINER;
            }
            $force = $this->userHasRole($rolesCanForce);

            if ($trainers && PersonPosition::havePosition($person->id, $trainers)) {
                // Person is a trainer.. allow mulitple training signups.
                $trainerForced = true;
                $force = true;
            } else if (!$force) {
                // Not a trainer, nor has sufficent roles.. your jedi mind tricks will not work here.
                $this->log(
                    'person-slot-add-fail',
                    "training multiple enrollment attempt {$slot->position->title} - {$slot->description} {$slot->begins}",
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
        } else if ($person->status == "alpha"
            && $slot->position_id == Position::ALPHA
            && Schedule::haveMultipleEnrollments($person->id, Position::ALPHA, $slot->begins->year, $enrollments)) {
            // Alpha is enrolled multiple times.
            $force = $this->userHasRole([ Role::ADMIN, Role::MENTOR ]);

            if (!$force) {
                $this->log(
                    'person-slot-add-fail',
                    "alpha multiple enrollment attempt {$slot->position->title} - {$slot->description} {$slot->begins}",
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
        } else {
            $force = $this->userHasRole(Role::ADMIN);
        }

        // Go try to add the person to the slot/session
        $result = Schedule::addToSchedule($person->id, $slot, $force);

        $status = $result['status'];
        if ($status == 'success') {
            $person->update(['active_next_event' => 1]);

            $forcedReasons = [];
            if ($trainerForced) {
                $forcedReasons[] = "trainer forced";
                $logData['trainer_forced'] = true;
            }

            if ($multipleEnrollmentForced) {
                $forcedReasons[] = "multiple enrollment";
                $logData['multiple_enrollment'] = true;
            }

            if ($result['forced']) {
                $forcedReasons[] = "overcapacity";
                $logData['overcapacity'] = true;
            }

            $action = "added";
            if (!empty($forcedReasons)) {
                $action .= '('.implode(',', $forcedReasons).')';
            }

            $this->log(
                'person-slot-add',
                $action,
                $logData,
                $person->id
            );

            // Notify the person about signing up
            if ($slot->isTraining()) {
                $message = new TrainingSignup($slot, setting('TrainingSignupFromEmail'));
            } else {
                $message = new SlotSignup($slot, setting('ShiftSignupFromEmail'));
            }

            Mail::to($person->email)->send($message);

            $signedUp = $result['signed_up'];

            // Is the training slot at capacity?
            if ($slot->isTraining() && $signedUp >= $slot->max) {
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

        $result = Schedule::deleteFromSchedule($person->id, $slotId);
        if ($result['status'] == 'success') {
            $slot = Slot::findOrFail($slotId);
            $this->log(
                'person-slot-remove',
                "removed {$slot->position->title} - {$slot->description} {$slot->begins}",
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
            $photoStatus = Photo::retrieveStatus($person);
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
}
