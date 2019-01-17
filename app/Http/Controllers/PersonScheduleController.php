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
     * Return an array of PesronSchedule for a given person & year
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Person $person)
    {
        $this->authorize('view', [ Schedule::class, $person]);

        $query = request()->validate(
            [
            'year'    => 'required|digits:4',
            'signups' => 'boolean',
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

        /* A signup may be forced if
         * - The user is an Admin
         * OR
         * - The slot is a training session, and the user is a trainer, mentor, or VC.
         *   ART Trainers can force force ART modules.
         */

        if ($slot->isTraining()) {
            $rolesCanForce = [ Role::ADMIN, Role::TRAINER, Role::MENTOR, Role::VC ];
            if ($slot->isArt()) {
                $rolesCanForce[] = Role::ART_TRAINER;
            }
        } else {
            $rolesCanForce = Role::ADMIN;
        }

        $force = $this->userHasRole($rolesCanForce);

        /*
         * Enrollment in multiple training sessions is not allowed unless the person
         * is a trainer.
         * e.g., Trainers/Assoc. Trainer/Ubers are allowed multiple Dirt Trainings,
         *       GD Trainers are allowe GD Training, etc.
         */

        $trainerForced = false;
        $enrollments = null;
        $multipleForced = false;
        if ($slot->isTraining() && Schedule::haveMultipleEnrollments($person->id, $slot->position_id, $slot->begins->year, $enrollments)) {
            $trainers = @Position::TRAINERS[$slot->position_id];

            if ($trainers && PersonPosition::havePosition($person->id, $trainers)) {
                // Person is a trainer.. allow mulitple training signups.
                $trainerForced = true;
            } else if ($force) {
                $multipleForced = true;
            } else {
                // Not a trainer, nor has sufficent roles.. your jedi mind tricks will not work here.
                return response()->json([
                    'status' => 'multiple-enrollment',
                    'slots'  => $enrollments
                ]);
            }
        }

        // Go try to add the person to the slot/session
        $result = Schedule::addToSchedule($person->id, $slot, $force);

        $status = $result['status'];
        if ($status == 'success') {
            $person->update(['active_next_event' => 1]);

            $this->log(
                'person-slot-add', "Slot added to schedule {$slot->begins}: {$slot->description}",
                [ 'slot_id' => $slotId],
                $person->id
            );

            // Notify the person about signing up
            if ($slot->isTraining()) {
                $message = new TrainingSignup($slot, config('clubhouse.TrainingSignupFromEmail'));
            } else {
                $message = new SlotSignup($slot, config('clubhouse.VCEmail'));
            }

            Mail::to($person->email)->send($message);

            $signedUp = $result['signed_up'];

            // Is the training slot at capacity?
            if ($slot->isTraining() && $signedUp >= $slot->max) {
                // fire off an email letting the Training Acamedy know
                Mail::to(config('clubhouse.TrainingFullEmail'))->send(new TrainingSessionFullMail($slot, $signedUp));
            }

            $response = [
                'status' => 'success',
                'signed_up' => $signedUp,
            ];

            if ($result['forced']) {
                $response['full_forced'] = true;
            }

            if ($trainerForced || $multipleForced) {
                $response['slots'] = $enrollments;

                if ($trainerForced) {
                    $response['trainer_forced'] = true;
                } else if ($multipleForced) {
                    $response['multiple_forced'] = true;
                }
            }

            return response()->json($response);
        }

        return response()->json([ 'status' => $status ]);
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
                "Slot removed from schedule {$slot->begins}: {$slot->description}",
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

        $manualReviewCap = config('clubhouse.ManualReviewProspectiveAlphaLimit');
        $manualReviewMyRank = 1;
        $manualReviewCount = 1;
        $missedManualReviewWindow = false;

        if ($status == "auditor") {
            $photoStatus = 'not-required';
        } else {
            $photoStatus = Photo::retrieveStatus($person);
        }

        $mrDisabledAllowSignups = config('clubhouse.ManualReviewDisabledAllowSignups');

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
        }

        if ($manualReviewCap > 0 && $isPotentialRanger) {
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
            } elseif ($status != "past prospective" && ($photoStatus != 'approved') && !$manualReviewPassed) {
                $showManualReviewLink = true;
            }
        }

        if (config('clubhouse.ManualReviewLinkEnable')) {
            $manualReviewUrl = config('clubhouse.ManualReviewGoogleFormBaseUrl').urlencode($person->callsign);
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
        ];

        return response()->json([ 'permission' => $results ]);
    }
}
