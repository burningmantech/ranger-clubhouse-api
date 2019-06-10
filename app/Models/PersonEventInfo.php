<?php

namespace App\Models;

use App\Models\ApihouseResult;
use App\Models\TraineeStatus;
use App\Models\Slot;
use App\Models\RadioEligible;
use App\Models\Bmid;
use App\Models\ManualReview;

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

    public $manual_review_pass;
    public $manual_review_date;

    /*
     * Gather all information related to a given year for a person
     * - training status & location (if any)
     * - radio eligibility
     * - meals & shower privileges
     * @var $personId - person to lookup
     * @var $year -
     * @return PersonEventInfo
     */

    static public function findForPersonYear($personId, $year) {
        $info = new PersonEventInfo();

        $info->person_id = $personId;
        $info->year = $year;

        $requireTraining = PersonPosition::findTrainingRequired($personId);
        $trainings = TraineeStatus::findForPersonYear($personId, $year);

        $trained = [];
        foreach ($trainings as $training) {
            $trained[$training->position_id] = $training;
        }

        $info->trainings = [];
        $now = SqlHelper::now();
        foreach($requireTraining as $need) {
            $status = new TrainingStatus;
            $status->position_title = $need->title;
            $status->position_id = $need->position_id;

            $info->trainings[] = $status;
            // TODO: Remove this at some point.
            if ($need->position_id == Position::DIRT) {
                $need->training_position_id = Position::DIRT_TRAINING;
            }

            $training = $trained[$need->training_position_id] ?? null;

            if (!$training || !$training->passed) {
                $slot = Slot::join('person_slot', 'person_slot.slot_id', 'slot.id')
                        ->where('position_id', $need->training_position_id)
                        ->whereYear('begins', $year)
                        ->where('person_slot.person_id', $personId)
                        ->orderBy('begins', 'desc')
                        ->first();
            } else {
                $slot = null;
            }

            if ($training) {
                // If the person did not pass, BUT there is a later sign up
                // use the later sign up.
                if (!$training->passed && $slot && $slot->begins->gt($training->begins)) {
                    $status->location = $slot->description;
                    $status->date = $slot->begins;
                    if ($status->date->gt($now)) {
                        $status->status = 'pending';
                    } else {
                        $status->status = 'fail';
                    }
                } else {
                    $status->location = $training->description;

                    $status->date = $training->begins;
                    if ($training->begins->gt($now)) {
                        $status->status = 'pending';
                    } else {
                        $status->status = ($training->passed ? 'pass' : 'fail');
                    }
                }
            } else if ($slot) {
                $status->location = $slot->description;
                $status->date = $slot->begins;
                if (Carbon::parse($status->date)->gt($now)) {
                    $status->status = 'pending';
                } else {
                    // Session has passed, fail it.
                    $status->status = 'fail';
                }

            } else {
                // Nothing found.
                $status->status = 'no-shift';
            }

            if ($status->date) {
                $status->date = (string) $status->date;
            }
        }


        $radio = RadioEligible::findForPersonYear($personId, $year);
        $info->radio_info_available = setting('RadioInfoAvailable');
        $info->radio_max = $radio ? $radio->max_radios : 0;
        $info->radio_eligible = $info->radio_max > 0 ? true : false;

        $info->meals = '';
        $info->showers = false;

        $bmid = Bmid::findForPersonYear($personId, $year);
        if ($bmid) {
            $info->meals = $bmid->meals;
            $info->showers = $bmid->showers;
        }

        if (current_year() == $year && !setting('MealInfoAvailable')) {
            $info->meals = 'no-info';
        }

        $manualReview = ManualReview::findForPersonYear($personId, $year);

        if ($manualReview) {
            $info->manual_review_pass = true;
            $info->manual_review_date = (string) $manualReview->passdate;
        } else {
            $info->manual_review_pass = false;
        }

        return $info;
    }
}
