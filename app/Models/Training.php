<?php

namespace App\Models;

use App\Helpers\DateHelper;

use App\Models\ApiModel;
use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\TraineeStatus;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;

/*
 * Training is an inherited Position object with more things!
 */

class Training extends Position
{
    protected $appends = [
        'is_art',
        'slug'
    ];

    /**
     * Determine if a person is trained for a position
     *
     * @param int $personId person to lookup
     * @param int $positionId position to check against
     * @param string $required training position title required if the person needs training
     * @param boolean true if person is trained, otherwise $required will be set.
     */

    public static function isPersonTrained($personId, $positionId, $year, & $requiredPositionId)
    {
        // The person has to have passed dirt training
        if (!TraineeStatus::didPersonPassForYear($personId, Position::DIRT_TRAINING, $year)) {
            $requiredPositionId = Position::DIRT_TRAINING;
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
        $requiredPositionId = $trainingId;

        return false;
    }

    /**
     * Find the positions for a person and include training status
     *
     * The array return will have the structure:
     *
     * id: position id
     * training_required: set true if position requires training
     * is_trained: set true if the person has been trained
     * title: position title
     * training_required: set to true if position requires training
     * training_position_id: the position id which person needs to pass training
     * training_title: position title of required training
     *
     * @param int $personId person to retrieve
     * @param int $year year to check against
     * @return array
     */

    public static function findPositionsWithTraining($personId, $year) {
        $positions = PersonPosition::findForPerson($personId);

        $personPositions = [];
        foreach ($positions as $position) {
            $info = [
                'id'    => $position->id,
                'title' => $position->title,
            ];

            if ($position->id == Position::DIRT) {
                $info['training_required'] = true;
                if (!TraineeStatus::didPersonPassForYear($personId, Position::DIRT_TRAINING, $year)) {
                    $info['is_trained'] = false;
                    $info['training_position_id'] = Position::DIRT_TRAINING;
                    $info['training_title'] = 'Training';
                } else {
                    $info['is_trained'] = true;
                }
            } else {
                $trainingId = $position->training_position_id;
                if ($trainingId) {
                    $info['training_required'] = true;
                    if (TraineeStatus::didPersonPassForYear($personId, $trainingId, $year)) {
                        $info['is_trained'] = true;
                    } else {
                        $info['is_trained'] = false;
                        $info['training_position_id'] = $trainingId;
                        $info['training_title'] =  Position::retrieveTitle($trainingId);
                    }
                } else {
                    $info['training_required'] = false;
                }
            }

            $personPositions[] = $info;
        }

        return $personPositions;
    }

    /**
     * Find a position which should be a training position.
     *
     * @param mixed $id position to find by integer or slug.
     * @return Training
     * @throws \InvalidArgumentException if record is not a training position.
     */

    public static function find($id)
    {
        // Can't call parent::findOrFail because an infinite loop happens, bug with Eloquent?
        if (is_numeric($id)) {
            $position = self::where('id', $id)->firstOrfail();
        } else if ($id == 'dirt') {
            $position = self::where('id', Position::DIRT_TRAINING)->firstOrfail();
        } else {
            $position = self::where('title', str_replace('-', ' ', $id))->firstOrFail();
        }

        if ($position->type != "Training" || stripos($position->title, "trainer") !== false) {
            throw new \InvalidArgumentException("Position is not a training position");
        }

        return $position;
    }

    /**
     * Find a training position and thrown an exception if not.
     *
     * @param mixed $id position to find by id or slug
     * @return Training
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException if position was not found.
     */

    public static function findOrFail($id) {
        $position = self::find($id);

        if ($position) {
            return $position;
        }

        throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(
            Training::class, $id
        );
    }

    /**
     * Find multiple enrollments for a training
     *
     * The method returns an array:
     * person_id: person found
     * callsign, first_name, loast_name, email
     * enrollments: array
     *     slot_id: session signed up for
     *     date: begins date of session
     *     location: location of sessions
     *
     * @param int $year which year to find the multiple enrollments
     * @return array of people who are enrolled multiple times.
     */

    public function retrieveMultipleEnrollments($year)
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
           ->where('position.id', $this->id)
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
              )', [ $year, $this->id ]
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
                'slot_id'  => $row->slot_id,
                'date'     => $row->date,
                'location' => $row->location,
            ];
        }

        return array_values($people);
    }

    /*
     * Find the signups for all training shifts in a given year
     *
     * The method returns:
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
     * @param int $year the year to search
     */

    public function retrieveSlotsCapacity($year)
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
          ->where('slot.position_id', $this->id)
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

    /**
     * Find people who hold a training positions but not the trained positions
     *
     * @param int $positionId training position
     * @param array $personIds people to look up
     * @param array person ids who are ART alphas
     */

    public static function findArtAlphas($positionId, $personIds)
    {
        if (empty($personIds)) {
            return [];
        }

        $trained = Position::findTrainedPositions($positionId);
        if ($trained->isEmpty()) {
            return [];
        }

        $people = DB::table('person_position')
                ->whereIn('position_id', $trained->pluck('id')->toArray())
                ->whereIn('person_id', $personIds)
                ->get()
                ->keyBy('person_id');

        $alphaIds = [];
        foreach ($personIds as $id) {
            if (@$people[$id]) {
                continue;
            }
            $alphaIds[] = $id;
        }

        return $alphaIds;
    }

    /**
     * Find everyone who has completed training in a year
     *
     * The return structure is:
     *   slot_id: slot record
     *   slot_description: the description
     *   slot_begins: slot start datetime
     *   people: array of who completed training
     *         (id, first_name, last_name, email)
     *
     * @param int $year which year to look at
     * @return array people who have completed training.
     */

    public function retrievePeopleForTrainingCompleted($year)
    {
        $rows = DB::select(
                "SELECT
                    person.id,
                    callsign,
                    first_name,
                    last_name,
                    email,
                    slot.id as slot_id,
                    slot.description as slot_description,
                    slot.begins as slot_begins
                FROM person, trainee_status, slot
                INNER JOIN position ON slot.position_id = position.id
                WHERE person.id = trainee_status.person_id
                     AND slot.id = trainee_status.slot_id
                     AND position.id = :position_id
                     AND YEAR(slot.begins) = :year
                     AND passed = 1
                 ORDER BY slot.begins, description, callsign",
                 [
                     'position_id'  => $this->id,
                     'year'         => $year
                 ]);

        $slots = [];
        $slotsByIds = [];

        foreach ($rows as $person)  {
            $slotId = $person->slot_id;

            if (!isset($slotsByIds[$slotId])) {
                $slot = [
                    'slot_id'   => $slotId,
                    'slot_description' => $person->slot_description,
                    'slot_begins'   => $person->slot_begins,
                    'people'    => []
                ];

                $slotsByIds[$slotId] = &$slot;
                $slots[] = &$slot;
                unset($slot);
            }

            $slotsByIds[$slotId]['people'][] = [
                'id'         => $person->id,
                'first_name' => $person->first_name,
                'last_name'  => $person->last_name,
                'callsign'   => $person->callsign,
                'email'      => $person->email
            ];
        }

        return $slots;
    }

    /**
     * Find all the people who have an ART position(s) and who have not signed up
     * or completed training.
     *
     * The return structure is:
     *
     * @param int $year
     * @throws \InvalidArgumentException*
     *       - if no other positions references this one as a needs-training position
     *       - if the position has no slots associated with it for a given year.
     */

    public function retrieveUntrainedPeople($year) {
        $trainedPositionIds = Position::where('training_position_id', $this->id)->pluck('id');

        if ($trainedPositionIds->isEmpty()) {
            return [
                'not_signed_up' => [],
                'not_passed' => [],
            ];
    //        throw new \InvalidArgumentException('No other position references this position for training');
        }

        $trainingSlotIds = Slot::where('position_id', $this->id)->whereYear('begins', $year)->pluck('id');

        if ($trainingSlotIds->isEmpty()) {
            return [
                'not_signed_up' => [],
                'not_passed' => [],
            ];
            //throw new \InvalidArgumentException('Position has no slots associated with for the year.');
        }

        /*
         * Find those who did not sign up for a training position, include
         * the slot & position title for a detail report.
         * (yah, not crazy about the sub-selects here.)
         */

        $rows = DB::select("SELECT
                person_slot.person_id,
                person_slot.slot_id,
                slot.description,
                slot.begins,
                position.title
            FROM person_slot
            INNER JOIN slot ON slot.id=person_slot.slot_id
            INNER JOIN position ON position.id = slot.position_id
            WHERE person_slot.slot_id IN (
                SELECT id FROM slot WHERE position_id IN (".
                    $trainedPositionIds->implode(',')."
                ) AND YEAR(begins)=$year
              )
             AND person_slot.person_id NOT IN (
                    SELECT person_id FROM person_slot
                     WHERE slot_id IN
                       (SELECT id FROM slot WHERE position_id={$this->id} AND YEAR(begins)=$year)
               )
            ORDER BY slot.begins asc");

        $peopleSignedUp = [];
        foreach ($rows as $row) {
            $personId = $row->person_id;
            if (!isset($peopleSignedUp[$personId])) {
                $peopleSignedUp[$personId] = [];
            }
            $peopleSignedUp[$personId][] = $row;
        }

        $untrainedSignedup = [];
        if (!empty($peopleSignedUp)) {
            $rows = Person::select('id', 'callsign', 'first_name', 'last_name', 'email')
                ->whereIn('id', array_keys($peopleSignedUp))->get();
            foreach ($rows as $row) {
                $row->slots = array_map(function($slot) {
                    return [
                        'slot_id'     => $slot->slot_id,
                        'begins'      => $slot->begins,
                        'description' => $slot->description,
                        'title'       => $slot->title,
                    ];
                }, $peopleSignedUp[$row->id]);
                $untrainedSignedup[] = $row;
            }

            ksort($untrainedSignedup);
        }

        /*
         * Find those signed up for a training shift, and did not pass (yet)
         * (this list will be filtered later below when looking for the
         * regular shifts)
         */

        $rows = DB::select(
            "SELECT
                    person.id,
                    person.callsign,
                    person.email,
                    person.first_name,
                    person.last_name,
                    slot.description as training_description,
                    slot.begins as training_begins,
                    slot.id as training_slot_id
            FROM person_slot
            INNER JOIN slot ON person_slot.slot_id=slot.id
            INNER JOIN person ON person.id=person_slot.person_id
            LEFT JOIN trainee_status ON person_slot.slot_id=trainee_status.slot_id AND person_slot.person_id=trainee_status.person_id
            WHERE person_slot.slot_id IN (".$trainingSlotIds->implode(',').")
            AND trainee_status.passed != TRUE
            AND NOT EXISTS (SELECT 1 FROM trainee_status ts WHERE ts.slot_id=person_slot.slot_id AND person_slot.person_id=ts.person_id AND ts.passed IS TRUE LIMIT 1)"
        );

        $peopleNotPassed = collect($rows)->keyBy('id');

        $untrainedNotPassed = [];
        if (!$peopleNotPassed->isEmpty()) {
            $peopleInfo = [];
            $personIds = $peopleNotPassed->keys();

            //
            // Find which shifts the person signed up for.
            //
            $rows = DB::select(
                "SELECT
                     person_slot.person_id,
                     person_slot.slot_id,
                     slot.description,
                     slot.begins,
                     position.title as position_title
                 FROM person_slot
                 INNER JOIN slot ON slot.id=person_slot.slot_id
                 INNER JOIN position ON position.id = slot.position_id
                 WHERE person_slot.person_id IN (".$personIds->implode(',').")
                 AND  person_slot.slot_id IN (
                     SELECT id FROM slot WHERE position_id IN (".
                         $trainedPositionIds->implode(',').
                    ") AND YEAR(begins)=$year
                   )
                 ORDER BY person_slot.person_id asc,slot.begins asc");

            foreach ($rows as $row) {
                $personId = $row->person_id;
                if (empty($peopleNotPassed[$personId]->slots)) {
                    $peopleNotPassed[$personId]->slots = [];
                }
                $peopleNotPassed[$personId]->slots[] = $row;
            }

            // Filter out those who have a training shift but no shifts
            foreach ($peopleNotPassed as $personId => $row) {
                if (isset($row->slots)) {
                    $untrainedNotPassed[$row->callsign] = $row;
                }
            }

            ksort($untrainedNotPassed);
            $untrainedNotPassed = array_values($untrainedNotPassed);
        }

        return [
            'not_signed_up' => $untrainedSignedup,
            'not_passed' => $untrainedNotPassed,
        ];
    }

    /*
     * Is this training position an ART module?
     */

    public function getIsArtAttribute() {
        return ($this->id != Position::DIRT_TRAINING);
    }

    /*
     * Convert the position title into a slug (lower cased, dasherized)
     */

    public function getSlugAttribute() {
        return ($this->id == Position::DIRT_TRAINING) ? 'dirt' : str_slug($this->title);
    }
}
