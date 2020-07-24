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
        $trainings = TraineeStatus::findForPersonYear($personId, $year);

        $trained = [];
        foreach ($trainings as $training) {
            $trained[$training->position_id] = $training;
        }

        $info->trainings = [];
        $now = SqlHelper::now();

        $trainingPositions = [];

        foreach ($requireTraining as $position) {
            $trainingPositionId = $position->id;
            $status = (object) [
                'position_id'    => $trainingPositionId,
                'position_title' => $position->title,
                'date'           => null,
                'status'         => null,
                'location'       => null,
            ];

            $training = $trained[$trainingPositionId] ?? null;

            // TODO: Support multiple ART training positions
            if (!$training && $trainingPositionId == Position::HQ_FULL_TRAINING) {
                $training = $trained[Position::HQ_REFRESHER_TRAINING] ?? null;
            }

            $teachingPositions = Position::TRAINERS[$trainingPositionId] ?? null;
            if ($teachingPositions) {
                $taught = TrainerStatus::retrieveSessionsForPerson($personId, $teachingPositions, $year);
                $trainer = $taught->firstWhere('status', TrainerStatus::ATTENDED);
            } else {
                $taught = [];
                $trainer = null;
            }

            if (!$training || !$training->passed) {
                $ids = [ $trainingPositionId ];

                // TODO: Support multiple ART training positions
                if ($trainingPositionId == Position::HQ_FULL_TRAINING) {
                    $ids[] = Position::HQ_REFRESHER_TRAINING;
                }
                $slot = Slot::join('person_slot', 'person_slot.slot_id', 'slot.id')
                        ->whereIn('position_id', $ids)
                        ->whereYear('begins', $year)
                        ->where('person_slot.person_id', $personId)
                        ->orderBy('begins', 'desc')
                        ->first();
            } else {
                $slot = null;
            }

            /*
             * The order of presedence is:
             *
             * 1. A training where the person was a trainer, and marked as attended
             * 2. A passed training.
             * 3. If a training wasn't passed but there's a later session signed up use that
             * 4. A future training as student
             * 5. A future training as trainer
             */

            if ($trainer) {
                // Person taught the course
                $status->location = $trainer->description;
                $status->date = $trainer->begins;
                $status->is_trainer = true;
                $status->status = 'pass';
            } elseif ($training) {
                // If the person did not pass, BUT there is a later sign up
                // use the later sign up.
                if (!$training->passed && $slot && $slot->ends->gt($training->ends)) {
                    $status->location = $slot->description;
                    $status->date = $slot->begins;
                    if ($slot->ends->gt($now)) {
                        $status->status = 'pending';
                    } else {
                        $status->status = 'fail';
                    }
                } else {
                    $status->location = $training->description;

                    $status->date = $training->begins;
                    if ($training->ends->gt($now)) {
                        $status->status = 'pending';
                    } else {
                        $status->status = ($training->passed ? 'pass' : 'fail');
                    }
                }
            } elseif ($slot) {
                $status->location = $slot->description;
                $status->date = $slot->begins;
                // Training signed up and no trainee status
                if (Carbon::parse($slot->ends)->gt($now)) {
                    // Session hasn't ended yet
                    $status->status = 'pending';
                } else {
                    // Session has passed, fail it.
                    $status->status = 'fail';
                }
            } elseif ($teachingPositions && !$taught->isEmpty()) {
                // find the first pending session
                $slot = $taught->firstWhere('status', null);
                if (!$slot) {
                    // nothing found - try to use a no-show
                    $slot = $taught->firstWhere('status', 'no-show');
                    if (!$slot) {
                        // okay, try the first session
                        $slot = $taught->first();
                    }
                }

                $status->location = $slot->description;
                $status->date = $slot->begins;
                $status->status = $slot->status ?? 'pending';
                $status->is_trainer = true;
            } else {
                // Nothing found.
                $status->status = 'no-shift';
            }

            if ($status->date) {
                $status->date = (string) $status->date;
            }

            $hasMentee = false;
            $status->required_by = $position->training_positions->map(function ($r) use (&$hasMentee) {
                return [ 'id' => $r->id, 'title' => $r->title ];
            })->sortBy('title')->values();

            if ($trainingPositionId == Position::GREEN_DOT_TRAINING
            && $status->status != 'no-shift') {
                $status->is_green_dot_pnv = !PersonPosition::havePosition($personId, Position::DIRT_GREEN_DOT);
                if ($status->is_green_dot_pnv) {
                    // Check to see if the person has signed up for or worked a GD mentee shift.
                    $status->mentee_slot = Slot::findFirstSignUp($personId, Position::GREEN_DOT_MENTEE, $year);
                    $status->mentee_timesheet = Timesheet::findLatestForPersonPosition($personId, Position::GREEN_DOT_MENTEE, $year);
                }
            }

            if ($status->required_by->isEmpty()) {
                /*
                 * Person could be a prospective ART ranger. An ART training is available, yet
                 * holds no ART positions which requires training.
                 * Let the user know which positions might require training
                 */

                $requires = null;
                switch ($trainingPositionId) {
                    case Position::GREEN_DOT_TRAINING:
                        $requires = [ 'id' => Position::GREEN_DOT_MENTEE, 'title' => 'Green Dot Mentee' ];
                        break;
                    case Position::SANDMAN_TRAINING:
                        $requires = [ 'id' => Position::SANDMAN, 'title' => 'Sandman' ];
                        break;
                    case Position::TOW_TRUCK_TRAINING:
                        $requires = [ 'id' => Position::TOW_TRUCK_MENTEE, 'title' => 'Tow Truck Mentee' ];
                        break;
                    case Position::HQ_FULL_TRAINING:
                        $requires = [ 'id' => Position::HQ_WINDOW, 'title' => 'HQ Window' ];
                        break;
                }

                if ($requires) {
                    $requires['not_granted'] = true;
                    $status->required_by = [ $requires ];
                }
            }

            $info->trainings[] = $status;
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
            $info->online_training_date = (string) $ot->completed_at;
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
