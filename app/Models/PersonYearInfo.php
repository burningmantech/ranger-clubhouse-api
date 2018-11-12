<?php

namespace App\Models;

use App\Models\ApihouseResult;
use App\Models\TraineeStatus;
use App\Models\Slot;
use App\Models\RadioEligible;
use App\Models\Bmid;

use App\Helpers\DateHelper;
use App\Helpers\SqlHelper;

use Carbon\Carbon;

class TrainingStatus {
    public $position_title;
    public $position_id;
    public $status;
    public $location;
    public $date;
};

class PersonYearInfo extends ApihouseResult
{
    public $person_id;
    public $year;

    public $trainings;
    public $radio_eligible;
    public $radio_max;
    public $meals;
    public $showers;

    /*
     * Gather all information related to a given year for a person
     * - training status & location (if any)
     * - radio eligibility
     * - meals & shower privileges
     * @var $personId - person to lookup
     * @var $year -
     * @return PersonYearInfo
     */

    static public function findForPersonYear($personId, $year) {
        $yearInfo = new PersonYearInfo();

        $yearInfo->person_id = $personId;
        $yearInfo->year = $year;

        $requireTraining = PersonPosition::findTrainingRequired($personId);
        $trainings = TraineeStatus::findForPersonYear($personId, $year);

        $trained = [];
        foreach ($trainings as $training) {
            $trained[$training->position_id] = $training;
        }

        $yearInfo->trainings = [];
        $now = SqlHelper::now();
        foreach($requireTraining as $need) {
            $status = new TrainingStatus;
            $status->position_title = $need->title;
            $status->position_id = $need->position_id;

            $yearInfo->trainings[] = $status;
            // TODO: Remove this at some point.
            if ($need->position_id == Position::DIRT) {
                $need->training_position_id = Position::DIRT_TRAINING;
            }

            if (isset($trained[$need->training_position_id])) {
                $training = $trained[$need->training_position_id];
                $status->location = $training->description;

                $status->date = DateHelper::formatDate($training->begins);
                if (Carbon::parse($training->begins)->gt($now)) {
                    $status->status = 'pending';
                } else {
                    $status->status = ($training->passed ? 'pass' : 'fail');
                }
            } else {
                $status->status = 'no-shift';
            }
        }


        $radio = RadioEligible::findForPersonYear($personId, $year);
        $yearInfo->radio_max = $radio ? $radio->max_radios : 0;
        $yearInfo->radio_eligible = $yearInfo->radio_max > 0 ? true : false;

        $yearInfo->meals = '';
        $yearInfo->showers = false;

        $bmid = Bmid::findForPersonYear($personId, $year);
        if ($bmid) {
            $yearInfo->meals = $bmid->meals;
            $yearInfo->showers = $bmid->showers;
        }

        if (date('Y') == $year && !config('clubhouse.MealInfoAvailable')) {
            $yearInfo->meals = 'no-info';
        }

        return $yearInfo;
    }
}
