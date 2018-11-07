<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Helpers\DateHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Position;
use App\Models\PersonPosition;
use App\Models\TraineeStatus;

/*
 * Composite Model
 *
 * Handle trainings for a person or position
 */

class Training extends ApiModel
{
    public $title;
    public $id;
    public $is_art;

    /*
     * Determine if a person is trained for a position
     */

    public static function isPersonTrained($personId, $positionId, $year, & $required)
    {
        // The person has to have passed dirt training
        if (!TraineeStatus::didPersonPassForYear($personId, Position::DIRT_TRAINING, $year)) {
            $required = 'Training';
            return false;
        }

        // Optimization: No additional checks are needed for a dirt shift
        if ($positionId == Position::DIRT) {
            return true;
        }

        // Does the position require training?
        $position = Position::findOrFail($positionId);
        $trainingId = $position->training_position_id;
        if (!$trainingId) {
            // No additional training is required
            return true;
        }

        // And check if person did pass the ART
        if (TraineeStatus::didPersonPassForYear($personId, $trainingId, $year)) {
            return true;
        }

        // "Computer says no..."
        // return the training position title
        $trainingPosition = Position::find($trainingId);
        $required = $trainingPosition->title;

        return false;
    }

    public static function findOrFail($id)
    {
        $position = Position::findOrFail($id);

        if ($position->type != "Training" || stripos($position->title, "trainer") !== false) {
            throw new \InvalidArgumentException("Position is not a training position");
        }

        return $position;
    }

    public static function retrieveMultipleEnrollments($training, $year)
    {
        $rows = DB::table('person')
        ->select(
            'person.id as person_id',
            'person.callsign',
            'person.first_name',
            'person.last_name',
            'person.email',
            'slot.begins AS date',
            'slot.description AS location',
            'slot.id as slot_id'
        )
           ->leftJoin('person_slot', 'person_slot.person_id', '=', 'person.id')
           ->leftJoin('slot', 'slot.id', '=', 'person_slot.slot_id')
           ->leftJoin('position', 'position.id', '=', 'slot.position_id')
           ->whereYear('slot.begins', $year)
           ->where('position.id', $training->id)
        ->whereRaw(
            'person.id IN (
                SELECT
                  p.id
                FROM person p
                  LEFT JOIN person_slot AS ps ON ps.person_id=p.id
                  LEFT JOIN slot        AS s  ON s.id=ps.slot_id
                  LEFT JOIN position    AS po ON po.id = s.position_id
                WHERE YEAR(s.begins) = ? AND po.id = ?
                GROUP BY p.id
                HAVING COUNT(s.id) > 1
              )', [ $year, $training->id ]
        )
           ->orderBy('person.callsign', 'asc')
           ->orderBy('date', 'ASC')
           ->get();

        $people = [];
        foreach ($rows as $row) {
            $personId = $row->person_id;
            if (!isset($people[$personId])) {
                $people[$personId] = [
                    'person_id'   => $personId,
                    'callsign'    => $row->callsign,
                    'first_name'  => $row->first_name,
                    'last_name'   => $row->last_name,
                    'email'       => $row->email,
                    'enrollments' => []
                ];
            }

            $people[$personId]['enrollments'][] = [
                'date'     => $row->date,
                'location' => $row->location,
                'slot_id'  => $row->slot_id,
            ];
        }

        return array_values($people);
    }

    /*
     * Find the signups for all training shifts in a given year
     *
     * The methods returns are:
     *
     * slot_id: slot record id
     * description: slot description
     * date: starts on datetime
     * max: sign up limit
     * signed_up: total signup count
     * filled: how full in a percentage (0 to 100+)
     * alpha_count: alpha & prospective count
     * veteran_count: signups who are not alpha, auditor or prospective
     * auditor_count: signups who are auditors
     *
     * @param Position $training the training position to look up
     * @param int $year the year to search
     */

    public static function retrieveSlotsCapacity($training, $year)
    {
          $rows = DB::table('slot')
          ->select(
              'slot.id as slot_id',
              'slot.description',
              'slot.begins as date',
              'slot.max',
              DB::raw('(
                SELECT COUNT(person.id) FROM person_slot
                LEFT JOIN person ON person.id=person_slot.person_id
                WHERE person_slot.slot_id=slot.id) as signed_up'
              ),
              DB::raw('(
                SELECT COUNT(person.id) FROM person_slot
                LEFT JOIN person ON person.id=person_slot.person_id AND person.status in ("alpha", "prospective")
                WHERE person_slot.slot_id=slot.id) as alpha_count'
              ),
              DB::raw('(
                SELECT COUNT(person.id) FROM person_slot
                LEFT JOIN person ON person.id=person_slot.person_id AND person.status NOT IN ("alpha", "auditor", "prospective")
                WHERE person_slot.slot_id=slot.id) as veteran_count'
              ),
              DB::raw('(
                SELECT COUNT(person.id) FROM person_slot LEFT JOIN person ON person.id=person_slot.person_id AND person.status="auditor"
                WHERE person_slot.slot_id=slot.id) as auditor_count'
              )
          )
          ->whereYear('slot.begins', $year)
          ->where('slot.position_id', $training->id)
          ->orderBy('slot.begins')->get();

          foreach ($rows as $row) {
              if ($row->signed_up > 0 && $row->max > 0) {
                  $row->filled = round(($row->signed_up / $row->max) * 100);
              } else {
                  $row->filled = 0;
              }
          }

          return $rows;
    }
}
