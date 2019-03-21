<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\Slot;
use App\Models\TraineeStatus;

/*
 * Extended Model
 *
 * Holds the training session information (aka a slot with extras)
 */

class TrainingSession extends Slot
{
    // don't calculate slot credits
    protected $hidden = [ 'credits' ];

    /*
     * Find all the training sessions (slots) for a training (position) and year
     */

    public static function findAllForTrainingYear($trainingId, $year)
    {
        return self::whereYear('begins', $year)
                ->where('position_id', $trainingId)
                ->orderBy('begins')
                ->get();
    }

    /*
     * Find the students for a training session
     *
     * The student information has the structure:
     * id:  person id
     * callsign
     * first_name
     * last_name
     * status: person status
     * email:
     * years: how many years this person has rangered
     * position_ids: integer array of the position the person holds
     * need_ranking: if true the person should be ranked only for non-actives and Dirt Training
     * is_art_alpha: if true the person has not worked an ART position before
     * is_inactive: ifture, the person is inactive
     * is_retired: if true, the person is retired
     * scored: if true, the person has been marked passed or failed
     * notes: trainer notes for this person
     * rank: trainer ranking - NULL, 1 to 4
     * passed: person has passed or failed training
     *
     */

    public function retrieveStudents()
    {
        // Find everyone signed up
        $people = PersonSlot::with([
                'person:id,callsign,first_name,last_name,email,status',
                'person.person_position:person_id,position_id'
            ])
            ->where('slot_id', $this->id)->get();

        $personIds = $people->pluck('person_id');
        $people = $people->sortBy(function ($p) { return $p->person->callsign; }, SORT_NATURAL|SORT_FLAG_CASE)->values();

        $isDirtTraining = ($this->position_id == Position::DIRT_TRAINING);

        $peopleYearsRangered = Timesheet::yearsRangeredCountForIds($personIds);
        if (!$isDirtTraining) {
            $artAlphaIds = Training::findArtAlphas($this->position_id, $personIds);
        }

        // Next, find the trainee status if any.
        $traineeStatusByIds = TraineeStatus::where('slot_id', $this->id)->get()->keyBy('person_id');

        $students = [];

        $pending = 0;
        $passed = 0;

        foreach ($people as $row) {
            $traineeStatus = @$traineeStatusByIds[$row->person_id];
            $person = $row->person;
            $status = $person->status;

            $info = [
                'id'         => $person->id,
                'callsign'   => $person->callsign,
                'first_name' => $person->first_name,
                'last_name'  => $person->last_name,
                'status'     => $status,
                'email'      => $person->email,
                'years'      => $peopleYearsRangered[$person->id] ?? 0,
                'position_ids'  => $person->person_position->pluck('position_id'),
            ];


            // Does the person need ranking?
            if ($isDirtTraining) {
                if ($status == Person::ALPHA
                || $status == Person::PROSPECTIVE
                || $status == Person::PROSPECTIVE_WAITLIST
                || $status == Person::BONKED
                || $status == Person::UBERBONKED
                || $status == Person::AUDITOR) {
                    $info['need_ranking'] = true;
                }
            } else {
                // Session is an ART training module, is the person an ART alpha?
                if (in_array($person->id, $artAlphaIds)) {
                    $info['is_art_alpha'] = true;
                }
            }

            if ($status == Person::INACTIVE || $status == Person::INACTIVE_EXTENSION) {
                $info['is_inactive'] = true;
            } else if ($status == Person::RETIRED) {
                $info['is_retired'] = true;
            }

            // Provide the trainee status (notes, rankning, passed)
            // if the record exists
            if ($traineeStatus) {
                $info['scored'] = true;
                $info['notes']  = $traineeStatus->notes;
                $info['rank'] = $traineeStatus->rank;
                $info['passed']  = $traineeStatus->passed;
            } else {
                $info['scored'] = false;
            }

            $students[] = $info;
        }

        return $students;
    }

    /*
     * Retrieve all the trainers for this session
     *
     * The trainer's information has  structure:
     *
     * slot: the trainer's slot found (full slot record: id, description, begins, etc.)
     * position_title: the position title for the slot
     * trainers: found trainer array (id, callsign, first_name, last_name, email)
     *
     */

    public function retrieveTrainers()
    {
        $trainerPositions = @Position::TRAINERS[$this->position_id];
        if (!$trainerPositions) {
            throw new \InvalidArgumentException('No trainer positions are associated.');
        }

        $trainers = [];
        // Attempt to find the matching trainer's slots
        foreach ($trainerPositions as $trainerPositionId) {
            $trainerPosition = Position::find($trainerPositionId);
            // Find the trainer's slot that begins within a hour of the slot start time.
            $trainerSlot = Slot::where('description', $this->description)
                ->whereRaw('begins BETWEEN DATE_SUB(?, INTERVAL 1 HOUR) AND ?', [ $this->begins, $this->ends ])
                ->where('position_id', $trainerPositionId)->first();

            if ($trainerSlot == null) {
                continue;
            }

            // Retrieve the trainers
            $rows = PersonSlot::with([ 'person:id,callsign,first_name,last_name,email'])
                        ->where('slot_id', $trainerSlot->id)->get();

            $rows = $rows->sortBy(function ($p) { return $p->person->callsign; })->values();

            $instructors = $rows->map(function($row) {
                $person = $row->person;
                return [
                    'id'         => $person->id,
                    'callsign'   => $person->callsign,
                    'first_name' => $person->first_name,
                    'last_name'  => $person->last_name,
                    'email'      => $person->email,
                ];
            });

            $trainers[] = [
                'slot'           => $trainerSlot,
                'position_title' => $trainerPosition->title,
                'trainers'       => $instructors,
            ];
        }

        return $trainers;
    }

    public function getTrainersAttribute() {
        return $this->retrieveTrainers();
    }
}
