<?php

namespace App\Lib;

use App\Models\AccessDocument;
use App\Models\Bmid;
use App\Models\Document;
use App\Models\EventDate;
use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\PersonOnlineCourse;
use App\Models\PersonPhoto;
use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Slot;
use App\Models\Survey;
use App\Models\Timesheet;
use App\Models\TraineeStatus;
use App\Models\TrainerStatus;
use App\Models\Training;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Milestones
{
    /**
     * Build up the various milestones completed or pending for a given person.
     * Use heavily by the dashboards.
     *
     * @param Person $person
     * @return array
     */

    public static function buildForPerson(Person $person): array
    {
        $status = $person->status;
        $personId = $person->id;

        $now = now();
        $year = $now->year;

        $event = PersonEvent::firstOrNewForPersonYear($personId, $year);
        $period = EventDate::calculatePeriod();
        list ($fullTraining, $isBinary) = Training::doesRequireInPersonTrainingFullDay($person);

        $isNonRanger = ($status == Person::NON_RANGER);

        $settings = setting([
            'MotorpoolPolicyEnable',
            'OnboardAlphaShiftPrepLink',
            'OnlineCourseEnabled',
            'OnlineCourseSiteUrl',
            'RadioCheckoutAgreementEnabled',
        ]);

        $milestones = [
            'online_course_passed' => PersonOnlineCourse::didCompleteForYear($personId, $year, Position::TRAINING),
            'online_course_enabled' => $settings['OnlineCourseEnabled'],
            'online_course_url' => $settings['OnlineCourseSiteUrl'],
            'needs_full_training' => $fullTraining,
            'behavioral_agreement' => $person->behavioral_agreement,
            'has_reviewed_pi' => !empty($event->pii_finished_at) && $event->pii_finished_at->year == $year,
            'asset_authorized' => $event->asset_authorized,
            'radio_checkout_agreement_enabled' => $settings['RadioCheckoutAgreementEnabled'],
            'trainings_available' => Slot::haveActiveForPosition(Position::TRAINING),
            'surveys' => Survey::retrieveUnansweredForPersonYear($personId, $year, $status),
            'period' => $period,
            'photo' => PersonPhoto::retrieveInfo($person),
        ];

        if ($status != Person::AUDITOR && empty($person->bpguid)) {
            $milestones['missing_bpguid'] = true;
        }

        $trainings = PersonPosition::findTrainingPositions($personId, true);
        $artTrainings = [];
        foreach ($trainings as $training) {
            $education = Training::retrieveEducation($personId, $training, $year);
            if ($training->id == Position::TRAINING) {
                $milestones['training'] = $education;
            } else {
                $artTrainings[] = $education;
            }
        }

        if (!isset($milestones['training'])) {
            $milestones['training'] = ['status' => 'no-shift'];
        }

        usort($artTrainings, fn($a, $b) => strcasecmp($a->position_title, $b->position_title));

        $milestones['art_trainings'] = $artTrainings;

        if (in_array($status, Person::ACTIVE_STATUSES)) {
            // Only require Online Course to be passed in order to work? (2021 social distancing training)
            $milestones['online_course_only'] = setting($isBinary ? 'OnlineCourseOnlyForBinaries' : 'OnlineCourseOnlyForVets');
        }

        switch ($status) {
            case Person::AUDITOR:
                if (setting('OnlineCourseOnlyForAuditors')) {
                    $milestones['online_course_only'] = true;
                }
                break;

            case Person::ALPHA:
            case Person::PROSPECTIVE:
            case Person::BONKED:
                $milestones['alpha_shift_prep_link'] = $settings['OnboardAlphaShiftPrepLink'];
                $milestones['alpha_shifts_available'] = $haveAlphaShifts = Slot::haveActiveForPosition(Position::ALPHA);
                if ($haveAlphaShifts) {
                    $alphaShift = Schedule::findEnrolledSlots($personId, $year, Position::ALPHA)->last();
                    if ($alphaShift) {
                        $milestones['alpha_shift'] = [
                            'slot_id' => $alphaShift->id,
                            'begins' => (string)$alphaShift->begins,
                            'status' => Carbon::parse($alphaShift->begins)->addHours(24)->lte($now) ? Training::NO_SHOW : Training::PENDING,
                        ];
                    }
                }
                break;

            case Person::ACTIVE:
                if ($isBinary) {
                    $milestones['is_binary'] = true;
                }
                break;

            case Person::INACTIVE_EXTENSION:
            case Person::RETIRED:
            case Person::RESIGNED:
                // Full day's training required, and walk a cheetah shift.
                $milestones['is_cheetah_cub'] = true;
                $cheetah = Schedule::findEnrolledSlots($personId, $year, Position::CHEETAH_CUB)->last();
                if ($cheetah) {
                    $milestones['cheetah_cub_shift'] = [
                        'slot_id' => $cheetah->id,
                        'begins' => (string)$cheetah->begins,
                    ];
                }
                break;
        }

        $milestones['motorpool_agreement_available'] = $settings['MotorpoolPolicyEnable'];
        $milestones['motorpool_agreement_signed'] = $event->signed_motorpool_agreement;

        if (!in_array($status, Person::ACTIVE_STATUSES) && !$isNonRanger) {
            return $milestones;
        }

        if ($period == 'event'
            && Timesheet::didWorkPositionInYear($personId, Position::TROUBLESHOOTER_MENTOR, $year)) {
            $milestones['ts_mentor_worked'] = true;
            $milestones['ts_mentor_survey_url'] = setting('TroubleshooterMentorSurveyURL');
        }

        // Starting late 2022 - All (effective) login management roles require annual NDA signature.
        // MOAR PAPERWERKS! MOAR WINZ!
        // Don't require the NDA if the agreement does not exist.
        if ($person->hasRawRole(Role::MANAGE)
            && !$event->signed_nda
            && Document::haveTag(Document::DEPT_NDA_TAG)
        ) {
            $milestones['nda_required'] = true;
        }

        if (!$isNonRanger) {
            // note, some inactives are active trainers yet do not work on playa.
            $milestones['is_trainer'] = PersonPosition::havePosition($personId, Position::TRAINERS[Position::TRAINING]);
        }
        $ticketingPeriod = setting('TicketingPeriod');
        $milestones['ticketing_period'] = $ticketingPeriod;
        if ($ticketingPeriod == 'open' || $ticketingPeriod == 'closed') {
            $milestones['ticketing_package'] = TicketAndProvisionsPackage::buildPackageForPerson($personId);
        }

        // Timesheets!
        if (setting('TimesheetCorrectionEnable') || $person->hasRole(Role::TIMECARD_YEAR_ROUND)) {
            $didWork = $milestones['did_work'] = Timesheet::didPersonWork($personId, $year);

            if ($didWork) {
                $milestones['timesheets_unverified'] = Timesheet::countUnverifiedForPersonYear($personId, $year);
                $milestones['timesheet_confirmed'] = $event->timesheet_confirmed;
            }
        }

        if ($period == EventDate::AFTER_EVENT) {
            return $milestones;
        }

        if (!$isNonRanger) {
            $milestones['dirt_shifts_available'] = Schedule::areDirtShiftsAvailable();
        }

        $milestones['shift_signups'] = Schedule::summarizeShiftSignups($person);
        // Person is *not* signed up - figure out if weekend shirts are available
        $milestones ['burn_weekend_available'] = Schedule::haveAvailableBurnWeekendShiftsForPerson($person);
        // Burn weekend!
        $milestones['burn_weekend_signup'] = Schedule::haveBurnWeekendSignup($person);

        $milestones['org_vehicle_insurance'] = $event->org_vehicle_insurance;

        if (PVR::isEligible($personId, $event, $year)) {
            $milestones['pvr_eligible'] = true;
            $milestones['vehicle_requests'] = Vehicle::findForPersonYear($personId, $year);
            $milestones['ignore_pvr'] = $event->ignore_pvr;
        }

        // Person might not be eligible until an PVR eligible position is signed up for.
        $milestones['pvr_potential'] = Position::haveVehiclePotential('pvr', $personId);

        if (MVR::isEligible($personId, $event, $year)) {
            $milestones['mvr_eligible'] = true;
            $milestones['mvr_request_url'] = setting('MVRRequestFormURL');
            $milestones['ignore_mvr'] = $event->ignore_mvr;
        }
        // Person might not be eligible until an MVR eligible position is signed up for.
        $milestones['mvr_potential'] = Position::haveVehiclePotential('mvr', $personId);

        if (!$isNonRanger && PersonPosition::havePosition($personId, Position::SANDMAN)) {
            if ($event->sandman_affidavit) {
                $milestones['sandman_affidavit_signed'] = true;
            } else if (TraineeStatus::didPersonPassForYear($personId, Position::SANDMAN_TRAINING, $year)
                || TrainerStatus::didPersonTeachForYear($personId, Position::SANDMAN_TRAINING, $year)) {
                // Sandpeople! Put the affidavit in your walk, head-to-toe let your whole body talk.
                $milestones['sandman_affidavit_unsigned'] = true;
            }
        }

        // BMID inspection
        $haveSignup = DB::table('slot')
            ->join('person_slot', function ($j) use ($personId) {
                $j->on('person_slot.slot_id', 'slot.id')
                    ->where('person_slot.person_id', $personId);
            })
            ->where('slot.begins_year', $year)
            ->where('slot.position_id', Position::TRAINING)
            ->where('slot.active', true)
            ->exists();

        $haveTicket = DB::table('access_document')
            ->where('person_id', $personId)
            ->whereIn('type', [AccessDocument::SPT, AccessDocument::STAFF_CREDENTIAL])
            ->whereIn('status', [AccessDocument::CLAIMED, AccessDocument::SUBMITTED])
            ->exists();

        $haveBmid = DB::table('bmid')
            ->where('year', $year)
            ->where('person_id', $personId)
            ->whereIn('status', [Bmid::IN_PREP, Bmid::READY_TO_PRINT, Bmid::SUBMITTED])
            ->exists();

        $milestones['bmid_qualified'] = ($haveSignup || $haveTicket || $haveBmid);

        return $milestones;
    }
}
