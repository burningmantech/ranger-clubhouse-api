<?php

namespace App\Models;

use App\Models\ApihouseResult;

use App\Models\Bmid;
use App\Models\PersonOnlineTraining;
use App\Models\RadioEligible;
use App\Models\Slot;
use App\Models\TraineeStatus;

use App\Helpers\SqlHelper;

use App\Policies\VehiclePolicy;
use Carbon\Carbon;

class PersonEventInfo extends ApihouseResult
{
    public $person_id;
    public $year;

    public $trainings;
    public $radio_eligible;
    public $radio_max;
    public $meals;
    public $showers;
    public $radio_info_available;

    public $online_training_passed;
    public $online_training_date;

    public $vehicles;

    /*
     * Gather all information related to a given year for a person
     * - training status & location (if any)
     * - radio eligibility
     * - meals & shower privileges
     * @var $personId - person to lookup
     * @var $year -
     * @return PersonEventInfo
     */

    public static function findForPersonYear($personId, $year)
    {
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

        usort($info->trainings, function ($a, $b) {
            return strcmp($a->position_title, $b->position_title);
        });

        $radio = RadioEligible::findForPersonYear($personId, $year);
        $info->radio_info_available = setting('RadioInfoAvailable');
        $info->radio_max = $radio ? $radio->max_radios : 0;
        $info->radio_eligible = $info->radio_max > 0 ? true : false;

        $bmid = Bmid::findForPersonYear($personId, $year);
        if ($bmid) {
            $info->meals = $bmid->meals;
            $info->showers = $bmid->showers;
        } else {
            $info->meals = '';
            $info->showers = false;
        }

        if (current_year() == $year && !setting('MealInfoAvailable')) {
            $info->meals = 'no-info';
        }

        $ot = PersonOnlineTraining::findForPersonYear($personId, $year);

        if ($ot) {
            $info->online_training_passed = true;
            $info->online_training_date = (string)$ot->completed_at;
        } else {
            $info->online_training_passed = false;
        }

        $info->vehicles = Vehicle::findForPersonYear($personId, $year);
        $event = PersonEvent::findForPersonYear($personId, $year);

        if ($event) {
            $info->may_request_stickers = $event->may_request_stickers;
            $info->org_vehicle_insurance = $event->org_vehicle_insurance;
            $info->signed_motorpool_agreement = $event->signed_motorpool_agreement;
        } else {
            $info->may_request_stickers = false;
            $info->org_vehicle_insurance = false;
            $info->signed_motorpool_agreement = false;
        }
        return $info;
    }
}
