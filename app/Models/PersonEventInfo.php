<?php

namespace App\Models;

use App\Models\ApihouseResult;

use App\Models\Bmid;
use App\Models\PersonOnlineTraining;

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
        $isCurrentYear = (current_year() == $year);

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

        usort($info->trainings, fn($a, $b) => strcmp($a->position_title, $b->position_title));

        $info->radio_info_available = setting('RadioInfoAvailable');

        if ($info->radio_info_available) {
            $radio = AccessDocument::findAvailableTypeForPerson($personId, AccessDocument::EVENT_RADIO);
            if ($radio) {
                $info->radio_eligible = true;
                $info->radio_max = $radio->item_count;
                $info->radio_status = $radio->status;
            } else {
                $info->radio_eligible = false;
            }
        }

        $bmid = Bmid::findForPersonYear($personId, $year);
        $info->meals = '';
        $info->showers = false;

        if ($bmid) {
            $info->meals = $bmid->meals;
            $info->showers = $bmid->showers;
        }

        if ($isCurrentYear) {
            if (setting('MealInfoAvailable')) {
                $meals = AccessDocument::findAvailableTypeForPerson($personId, AccessDocument::ALL_YOU_CAN_EAT);
                if ($meals) {
                    $info->meals_status = $meals->status;
                    if ($meals->status != AccessDocument::BANKED && (!$bmid || empty($bmid->meals))) {
                        $meals->meals = 'all';
                    }
                }
            } else {
                $info->meals = 'no-info';
            }

            $showers = AccessDocument::findAvailableTypeForPerson($personId, AccessDocument::WET_SPOT);
            if ($showers) {
                $info->meals_status = $showers->status;
                if (!$bmid && $showers->status != AccessDocument::BANKED) {
                    $info->showers = true;
                }
            }
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
