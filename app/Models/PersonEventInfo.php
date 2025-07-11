<?php

namespace App\Models;

use App\Lib\MVR;
use App\Lib\PVR;

class PersonEventInfo extends ApihouseResult
{
    public int $person_id = 0;
    public int $year = 0;

    public ?string $event_period = '';
    public ?string $online_course_date = null;
    public bool $is_binary = false;
    public bool $may_request_stickers = false;
    public bool $mvr_eligible = false;
    public bool $online_course_only = false;
    public bool $online_course_passed = false;
    public bool $org_vehicle_insurance = false;
    public bool $radio_eligible = false;
    public bool $radio_info_available = false;
    public bool $showers = false;
    public bool $signed_motorpool_agreement = false;
    public int $radio_max = 0;
    public mixed $trainings;
    public bool $in_person_training_passed = false;
    public mixed $vehicles;
    public ?array $meals = null;

    /*
     * Gather all information related to a given year for a person
     * - training status & location (if any)
     * - radio eligibility
     * - meals & shower privileges
     * @var $personId - person to lookup
     * @var $year -
     * @return PersonEventInfo
     */

    public static function findForPersonYear(Person $person, $year): PersonEventInfo
    {
        $personId = $person->id;
        $info = new PersonEventInfo();

        $info->person_id = $personId;
        $info->year = $year;

        $requireTraining = PersonPosition::findTrainingPositions($personId);
        $info->trainings = [];

        if ($requireTraining->isEmpty()) {
            $requireTraining = [Position::find(Position::TRAINING)];
        }

        foreach ($requireTraining as $position) {
            $info->trainings[] = Training::retrieveEducation($personId, $position, $year);
        }

        usort($info->trainings, fn($a, $b) => strcasecmp($a->position_title, $b->position_title));

        $info->in_person_training_passed = array_any($info->trainings,
            fn($t) => ($t->position_id === Position::TRAINING && $t->status === 'pass')
        );

        $info->radio_info_available = setting('RadioInfoAvailable');
        if ($info->radio_info_available) {
            $package = Provision::buildPackage(Provision::retrieveUsableForPersonIds([$personId]));
            if ($package['radios']) {
                $info->radio_eligible = true;
                $info->radio_max = $package['radios'];
            }
        }

        $bmid = Bmid::findForPersonManage($personId, $year);

        $info->meals = $bmid->meals_granted;
        $info->showers = $bmid->showers_granted;

        $info->event_period = EventDate::retrieveEventOpsPeriod();

        if (in_array($person->status, Person::ACTIVE_STATUSES)) {
            $info->is_binary = Timesheet::isPersonBinary($person);
            $info->online_course_only = setting($info->is_binary ? 'OnlineCourseOnlyForBinaries' : 'OnlineCourseOnlyForVets');
        }

        $poc = PersonOnlineCourse::findForPersonYear($personId, $year, Position::TRAINING);
        if ($poc?->completed_at) {
            $info->online_course_passed = true;
            $info->online_course_date = (string)$poc->completed_at;
        } else {
            $info->online_course_passed = false;
        }

        $info->vehicles = Vehicle::findForPersonYear($personId, $year);
        $event = PersonEvent::findForPersonYear($personId, $year);

        if ($event) {
            $info->org_vehicle_insurance = $event->org_vehicle_insurance;
            $info->signed_motorpool_agreement = $event->signed_motorpool_agreement;
        } else {
            $info->org_vehicle_insurance = false;
            $info->signed_motorpool_agreement = false;
        }

        if (in_array($person->status, Person::ACTIVE_STATUSES) || $person->status == Person::ECHELON) {
            $info->mvr_eligible = MVR::isEligible($person->id, $event, $year);
            $info->may_request_stickers = PVR::isEligible($personId, $event, $year);
        }

        return $info;
    }
}
