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
use Carbon\CarbonInterface;

class Milestones
{
    private const array SETTING_KEYS = [
        'MotorPoolProtocolEnabled',
        'OnboardAlphaShiftPrepLink',
        'OnlineCourseEnabled',
        'OnlineCourseSiteUrl',
        'RadioCheckoutAgreementEnabled',
    ];

    private int $personId;
    private string $status;
    private int $year;
    private CarbonInterface $now;
    private string $period;
    private PersonEvent $event;
    private array $settings;
    private bool $fullTraining;
    private bool $isBinary;
    private bool $isEchelon;
    private bool $isActive;
    private array $milestones = [];

    public function __construct(private readonly Person $person)
    {
        $this->personId = $person->id;
        $this->status = $person->status;
        $this->now = now();
        $this->year = $this->now->year;
        $this->event = PersonEvent::firstOrNewForPersonYear($this->personId, $this->year);
        $this->period = EventDate::calculatePeriod();
        $this->settings = setting(self::SETTING_KEYS);
        $this->isEchelon = $this->status === Person::ECHELON;
        $this->isActive = in_array($this->status, Person::ACTIVE_STATUSES, true);

        [$this->fullTraining, $this->isBinary] = Training::doesRequireInPersonTrainingFullDay($person);
    }

    /**
     * Build up the various milestones completed or pending for a given person.
     * Used heavily by the dashboards.
     *
     * @param Person $person
     * @return array
     */

    public static function buildForPerson(Person $person): array
    {
        return (new self($person))->build();
    }

    /**
     * Assemble the milestone bag, short-circuiting on status/period gates.
     *
     * @return array
     */

    public function build(): array
    {
        $this->addBaseMilestones();
        $this->addTrainings();
        $this->addStatusSpecificMilestones();
        $this->addDocumentationLinks();
        $this->addMotorpoolAgreement();

        if (!$this->isActive && !$this->isEchelon) {
            return $this->milestones;
        }

        $this->addPostEventMilestones();

        if ($this->period === EventDate::AFTER_EVENT) {
            return $this->milestones;
        }

        $this->addShiftAndScheduleMilestones();
        $this->addVehicleMilestones();
        $this->addSandmanAffidavit();
        $this->addBmidQualification();

        return $this->milestones;
    }

    private function addBaseMilestones(): void
    {
        $this->milestones += [
            'online_course_passed' => PersonOnlineCourse::didCompleteForYear($this->personId, $this->year, Position::TRAINING),
            'online_course_enabled' => $this->settings['OnlineCourseEnabled'],
            'online_course_url' => $this->settings['OnlineCourseSiteUrl'],
            'needs_full_training' => $this->fullTraining,
            'behavioral_agreement' => $this->person->behavioral_agreement,
            'has_reviewed_pi' => !empty($this->event->pii_finished_at) && $this->event->pii_finished_at->year === $this->year,
            'asset_authorized' => $this->event->asset_authorized,
            'radio_checkout_agreement_enabled' => $this->settings['RadioCheckoutAgreementEnabled'],
            'trainings_available' => Slot::haveActiveForPosition(Position::TRAINING),
            'surveys' => Survey::retrieveUnansweredForPersonYear($this->personId, $this->year, $this->status),
            'period' => $this->period,
            'photo' => PersonPhoto::retrieveInfo($this->person),
        ];

        if ($this->status !== Person::AUDITOR && empty($this->person->bpguid)) {
            $this->milestones['missing_bpguid'] = true;
        }
    }

    private function addTrainings(): void
    {
        $this->milestones['training'] = ['status' => 'no-shift'];
        $artTrainings = [];

        foreach (PersonPosition::findTrainingPositions($this->personId, true) as $training) {
            $education = Training::retrieveEducation($this->personId, $training, $this->year);
            if ($training->id === Position::TRAINING) {
                $this->milestones['training'] = $education;
            } else {
                $artTrainings[] = $education;
            }
        }

        usort($artTrainings, fn($a, $b) => strcasecmp($a->position_title, $b->position_title));
        $this->milestones['art_trainings'] = $artTrainings;

        if ($this->isActive) {
            $key = $this->isBinary ? 'OnlineCourseOnlyForBinaries' : 'OnlineCourseOnlyForVets';
            $this->milestones['online_course_only'] = setting($key);
        }
    }

    private function addStatusSpecificMilestones(): void
    {
        switch ($this->status) {
            case Person::AUDITOR:
                if (setting('OnlineCourseOnlyForAuditors')) {
                    $this->milestones['online_course_only'] = true;
                }
                break;

            case Person::ALPHA:
            case Person::PROSPECTIVE:
            case Person::BONKED:
                $this->addAlphaMilestones();
                break;

            case Person::ACTIVE:
                if ($this->isBinary) {
                    $this->milestones['is_binary'] = true;
                }
                break;

            case Person::INACTIVE_EXTENSION:
            case Person::RETIRED:
            case Person::RESIGNED:
                $this->addCheetahCubMilestones();
                break;
        }
    }

    private function addAlphaMilestones(): void
    {
        $this->milestones['alpha_shift_prep_link'] = $this->settings['OnboardAlphaShiftPrepLink'];
        $this->milestones['alpha_shifts_available'] = $haveShifts = Slot::haveActiveForPosition(Position::ALPHA);
        $this->milestones['alpha_shift_publish_date'] = Carbon::parse('second Wednesday of July')->format('Y-m-d');

        if (!$haveShifts) {
            return;
        }

        $alphaShift = Schedule::findEnrolledSlots($this->personId, $this->year, Position::ALPHA)->last();
        if (!$alphaShift) {
            return;
        }

        $begins = Carbon::parse($alphaShift->begins);
        $this->milestones['alpha_shift'] = [
            'slot_id' => $alphaShift->id,
            'begins' => (string)$alphaShift->begins,
            'status' => $begins->copy()->addHours(24)->lte($this->now) ? Training::NO_SHOW : Training::PENDING,
        ];
    }

    private function addCheetahCubMilestones(): void
    {
        // Full day's training required, and walk a cheetah shift.
        $this->milestones['is_cheetah_cub'] = true;
        $cheetah = Schedule::findEnrolledSlots($this->personId, $this->year, Position::MENTOR_CHEETAH_CUB)->last();
        if ($cheetah) {
            $this->milestones['cheetah_cub_shift'] = [
                'slot_id' => $cheetah->id,
                'begins' => (string)$cheetah->begins,
            ];
        }
    }

    private function addDocumentationLinks(): void
    {
        $suffix = $this->isActive ? 'ranger' : 'pnv';
        $this->milestones['links'] = Document::contentsByTag("dashboard-links-{$suffix}");
        $this->milestones['contacts'] = Document::contentsByTag("contacts-{$suffix}");
    }

    private function addMotorpoolAgreement(): void
    {
        $this->milestones['motorpool_agreement_available'] = $this->settings['MotorPoolProtocolEnabled'];
        $this->milestones['motorpool_agreement_signed'] = $this->event->signed_motorpool_agreement;
    }

    private function addPostEventMilestones(): void
    {
        if ($this->period === 'event'
            && Timesheet::didWorkPositionInYear($this->personId, Position::TROUBLESHOOTER_MENTOR, $this->year)) {
            $this->milestones['ts_mentor_worked'] = true;
            $this->milestones['ts_mentor_survey_url'] = setting('TroubleshooterMentorSurveyURL');
        }

        // Starting late 2022 - all (effective) login management roles require annual NDA signature.
        // Skip the requirement if the agreement document does not exist.
        if ($this->person->hasRawRole(Role::EVENT_MANAGEMENT)
            && !$this->event->signed_nda
            && Document::haveTag(Document::DEPT_NDA_TAG)) {
            $this->milestones['nda_required'] = true;
        }

        if (!$this->isEchelon) {
            // Some inactives are active trainers yet do not work on playa.
            $this->milestones['is_trainer'] = PersonPosition::havePosition($this->personId, Position::TRAINERS[Position::TRAINING]);
        }

        $ticketingPeriod = setting('TicketingPeriod');
        $this->milestones['ticketing_period'] = $ticketingPeriod;
        if (in_array($ticketingPeriod, ['open', 'closed'], true)) {
            $this->milestones['ticketing_package'] = TicketAndProvisionsPackage::buildPackageForPerson($this->personId);
        }

        if (setting('TimesheetCorrectionEnable') || $this->person->hasRole(Role::SHIFT_MANAGEMENT_SELF)) {
            $didWork = $this->milestones['did_work'] = Timesheet::didPersonWork($this->personId, $this->year);
            if ($didWork) {
                $this->milestones['timesheets_unverified'] = Timesheet::countUnverifiedForPersonYear($this->personId, $this->year);
                $this->milestones['timesheet_confirmed'] = $this->event->timesheet_confirmed;
            }
        }
    }

    private function addShiftAndScheduleMilestones(): void
    {
        if (!$this->isEchelon) {
            $this->milestones['dirt_shifts_available'] = Schedule::areDirtShiftsAvailable();
        }

        $this->milestones['shift_signups'] = Schedule::summarizeShiftSignups($this->person);
        $this->milestones['burn_weekend_available'] = Schedule::haveAvailableBurnWeekendShiftsForPerson($this->person);
        $this->milestones['burn_weekend_signup'] = Schedule::haveBurnWeekendSignup($this->person);
        $this->milestones['org_vehicle_insurance'] = $this->event->org_vehicle_insurance;
    }

    private function addVehicleMilestones(): void
    {
        $this->milestones['ignore_pvr'] = $this->event->ignore_pvr;
        if (PVR::isEligible($this->personId, $this->event, $this->year)) {
            $this->milestones['pvr_eligible'] = true;
            $this->milestones['vehicle_requests'] = Vehicle::findForPersonYear($this->personId, $this->year);
        }
        // Person might not be eligible until a PVR-eligible position is signed up for.
        $this->milestones['pvr_potential'] = Position::haveVehiclePotential('pvr', $this->personId);

        $this->milestones['ignore_mvr'] = $this->event->ignore_mvr;
        if (MVR::isEligible($this->personId, $this->event, $this->year)) {
            $this->milestones['mvr_eligible'] = true;
        }
        // Person might not be eligible until an MVR-eligible position is signed up for.
        $this->milestones['mvr_potential'] = Position::haveVehiclePotential('mvr', $this->personId);
    }

    private function addSandmanAffidavit(): void
    {
        if ($this->isEchelon || !PersonPosition::havePosition($this->personId, Position::SANDMAN)) {
            return;
        }

        if ($this->event->sandman_affidavit) {
            $this->milestones['sandman_affidavit_signed'] = true;
            return;
        }

        $passedOrTaught = TraineeStatus::didPersonPassForYear($this->personId, Position::SANDMAN_TRAINING, $this->year)
            || TrainerStatus::didPersonTeachForYear($this->personId, Position::SANDMAN_TRAINING, $this->year);

        if ($passedOrTaught) {
            $this->milestones['sandman_affidavit_unsigned'] = true;
        }
    }

    private function addBmidQualification(): void
    {
        $this->milestones['bmid_qualified'] =
            Schedule::hasTrainingSignup($this->personId, $this->year)
            || AccessDocument::hasActiveTicket($this->personId)
            || Bmid::hasInProgress($this->personId, $this->year);
    }
}
