<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonStatus;
use App\Models\Position;
use App\Models\TraineeStatus;
use App\Models\TraineeNote;
use Carbon\Carbon;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Str;
use InvalidArgumentException;

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
     * Is the person trained for a position in a given year?
     *
     * @param \App\Models\Person $person person to check
     * @param int $positionId the position in question
     * @param int $year year of training
     * @param int $requiredPositionId the training position required if person is not trained
     * @return bool true if the person is trained
     */

    public static function isPersonTrained(Person $person, int $positionId, int $year, int &$requiredPositionId): bool
    {
        $personId = $person->id;

        // The person has to have passed dirt training
        if ($person->status != Person::NON_RANGER
            && !self::didPersonPassForYear($personId, Position::TRAINING, $year)) {
            $requiredPositionId = Position::TRAINING;
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
        if (self::didPersonPassForYear($personId, $trainingId, $year)) {
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
     * is_untrained: set true if the person is not trained for this position
     * is_unqualified: set true if the person is not qualified for person
     * unqualified_reason: reason person is not qualified
     * title: position title
     * training_required: set to true if position requires training
     * training_position_id: the position id which person needs to pass training
     * training_title: position title of required training
     *
     * @param Person $person person to retrieve
     * @param int $year year to check against
     * @return array
     */

    public static function findPositionsWithTraining(Person $person, int $year): array
    {
        $personId = $person->id;
        $positions = PersonPosition::findForPerson($personId);

        $personPositions = [];
        foreach ($positions as $position) {
            $info = (object)[
                'id' => $position->id,
                'title' => $position->title,
                'active' => $position->active,
            ];

            $personPositions[] = $info;

            /*
             * Assume the person is trained unless indicated otherwise
             */

            $positionId = $position->id;
            switch ($positionId) {
                case Position::DIRT:
                case Position::DIRT_PRE_EVENT:
                case Position::DIRT_POST_EVENT:
                    $trainingId = Position::TRAINING;
                    break;

                default:
                    $trainingId = $position->training_position_id;
                    break;
            }

            // See if the person taught a training session required by the position
            $trainer = null;
            if ($trainingId) {
                $teachingPositions = Position::TRAINERS[$trainingId] ?? null;
                if ($teachingPositions) {
                    $taught = TrainerStatus::retrieveSessionsForPerson($personId, $teachingPositions, $year);
                    // Find the session the person taught and was marked present.
                    $trainer = $taught->firstWhere('status', TrainerStatus::ATTENDED);
                }
            }


            if ($positionId == Position::SANDMAN) {
                $unqualifiedReason = null;
                $isQualified = Position::isSandmanQualified($person, $unqualifiedReason);

                if (!$isQualified) {
                    $info->is_unqualified = true;
                    $info->unqualified_reason = $unqualifiedReason;
                    $info->unqualified_message = Position::UNQUALIFIED_MESSAGES[$unqualifiedReason];
                }
            }

            /*
             * Mark the person as untrained if:
             * - was NOT a trainer who taught a session
             * - and the position requires training
             * - and the person did not pass/attend training
             */

            if (!$trainer && $trainingId && !self::didPersonPassForYear($personId, $trainingId, $year)) {
                $info->is_untrained = true;
                $info->training_position_id = $trainingId;
                $info->training_title = Position::retrieveTitle($trainingId);
            }

        }

        return $personPositions;
    }

    /**
     * Did the person pass training in a given year? check to see if they were a teach or student.
     *
     * @param int $personId person to check
     * @param int $positionId position in question
     * @param int $year the year to check in
     * @return bool true if the person passed in the given year
     */

    public static function didPersonPassForYear(int $personId, int $positionId, int $year): bool
    {
        return TraineeStatus::didPersonPassForYear($personId, $positionId, $year)
            || TrainerStatus::didPersonTeachForYear($personId, $positionId, $year);
    }

    /**
     * Find a position which should be a training position.
     *
     * @param mixed $id position to find by integer or slug.
     * @return Training
     * @throws InvalidArgumentException if record is not a training position.
     */

    public static function find($id)
    {
        // Can't call parent::findOrFail because an infinite loop happens, bug with Eloquent?
        if (is_numeric($id)) {
            $position = self::where('id', $id)->firstOrfail();
        } elseif ($id == 'dirt') {
            $position = self::where('id', Position::TRAINING)->firstOrfail();
        } else {
            $position = self::where('title', str_replace('-', ' ', $id))->firstOrFail();
        }

        if ($position->type != "Training" || stripos($position->title, "trainer") !== false) {
            throw new InvalidArgumentException("Position is not a training position");
        }

        return $position;
    }

    /**
     * Find a training position and thrown an exception if not.
     *
     * @param mixed $id position to find by id or slug
     * @return Training
     * @throws ModelNotFoundException if position was not found.
     */

    public static function findOrFail($id)
    {
        $position = self::find($id);

        if ($position) {
            return $position;
        }

        throw (new ModelNotFoundException)->setModel(
            Training::class,
            $id
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

    public function retrieveMultipleEnrollments(int $year): array
    {
        $byPerson = DB::table('person')
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
              )',
                [$year, $this->id]
            )
            ->orderBy('person.callsign', 'asc')
            ->orderBy('date', 'ASC')
            ->get()
            ->groupBy('person_id');

        $people = [];
        foreach ($byPerson as $personId => $slots) {
            foreach ($slots as $slot) {
                $slot->isMultiParter = false;
            }

            $haveMultiples = false;

            foreach ($slots as $check) {
                if ($check->isMultiParter) {
                    continue;
                }

                foreach ($slots as $slot) {
                    if ($slot->isMultiParter || $slot->slot_id == $check->slot_id) {
                        continue;
                    }

                    if (Slot::isPartOfSessionGroup($slot->location, $check->location)) {
                        $slot->isMultiParter = true;
                        $check->isMultiParter = true;
                        break;
                    }
                }

                if (!$check->isMultiParter) {
                    $haveMultiples = true;
                    break;
                }
            }

            if ($haveMultiples == false) {
                continue;
            }

            $person = $slots[0];
            $people[] = [
                'person_id' => $personId,
                'callsign' => $person->callsign,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'email' => $person->email,
                'enrollments' => $slots->map(function ($row) {
                    return [
                        'slot_id' => $row->slot_id,
                        'date' => $row->date,
                        'location' => $row->location,
                    ];
                })->values()
            ];
        }

        return $people;
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

    public function retrieveSlotsCapacity(int $year)
    {
        $positionIds = [$this->id];
        if ($this->id == Position::HQ_FULL_TRAINING) {
            $positionIds[] = Position::HQ_REFRESHER_TRAINING;
        }

        $rows = DB::table('slot')
            ->select(
                'slot.id as slot_id',
                'slot.description',
                'slot.begins as date',
                'slot.max',
                DB::raw(
                    '(
                SELECT COUNT(person.id) FROM person_slot
                LEFT JOIN person ON person.id=person_slot.person_id
                WHERE person_slot.slot_id=slot.id) as signed_up'
                ),
                DB::raw(
                    '(
                SELECT COUNT(person.id) FROM person_slot
                LEFT JOIN person ON person.id=person_slot.person_id AND person.status in ("alpha", "prospective")
                WHERE person_slot.slot_id=slot.id) as alpha_count'
                ),
                DB::raw(
                    '(
                SELECT COUNT(person.id) FROM person_slot
                LEFT JOIN person ON person.id=person_slot.person_id AND person.status NOT IN ("alpha", "auditor", "prospective")
                WHERE person_slot.slot_id=slot.id) as veteran_count'
                ),
                DB::raw(
                    '(
                SELECT COUNT(person.id) FROM person_slot LEFT JOIN person ON person.id=person_slot.person_id AND person.status="auditor"
                WHERE person_slot.slot_id=slot.id) as auditor_count'
                )
            )
            ->whereYear('slot.begins', $year)
            ->whereIn('slot.position_id', $positionIds)
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
     * Find out the education status for a given person, position and year
     *
     * @param int $personId
     * @param \App\Models\Position $position
     * @param int $year
     * @param $trained
     * @return object
     */
    public static function retrieveEducation(int $personId, Position $position, int $year)
    {
        $now = now();

        $trainingPositionId = $position->id;
        $ed = (object)[
            'status' => null,
            'date' => null,
            'location' => null,
            'position_id' => $trainingPositionId,
            'position_title' => $position->title,
        ];

        $trainings = TraineeStatus::findForPersonYear($personId, $year, $trainingPositionId);
        $training = $trainings->firstWhere('passed', true);
        if (!$training) {
            $training = $trainings->sortBy('begins')->last();
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
            $ids = [$trainingPositionId];

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
         * The order of precedence is:
         *
         * 1. A training where the person was a trainer, and marked as attended
         * 2. A passed training.
         * 3. If a training wasn't passed but there's a later session signed up use that
         * 4. A future training as student
         * 5. A future training as trainer
         */

        if ($trainer) {
            // Person taught the course
            $ed->location = $trainer->description;
            $ed->date = $trainer->begins;
            $ed->slot_id = $trainer->slot_id;
            $ed->is_trainer = true;
            $ed->status = 'pass';
        } elseif ($training) {
            // If the person did not pass, BUT there is a later sign up use the later sign up.
            if (!$training->passed && $slot && $slot->ends->gt($training->ends)) {
                $ed->location = $slot->description;
                $ed->date = $slot->begins;
                $ed->slot_id = $slot->id;
                $ed->status = self::isTimeWithinGracePeriod($slot->ends, $now) ? 'pending' : 'failed';
            } else {
                $ed->slot_id = $training->id;
                $ed->location = $training->description;
                $ed->date = $training->begins;
                if (!$training->passed && self::isTimeWithinGracePeriod($training->ends, $now)) {
                    $ed->status = 'pending';
                } else {
                    $ed->status = ($training->passed ? 'pass' : 'fail');
                }
            }
        } elseif ($slot) {
            $ed->slot_id = $slot->id;
            $ed->location = $slot->description;
            $ed->date = $slot->begins;
            // Training signed up and no trainee status
            $ed->status = self::isTimeWithinGracePeriod($slot->ends, $now) ? 'pending' : 'fail';
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

            $ed->slot_id = $slot->id;
            $ed->location = $slot->description;
            $ed->date = $slot->begins;
            $ed->status = $slot->status ?? 'pending';
            $ed->is_trainer = true;
        } else {
            // Nothing found.
            $ed->status = 'no-shift';
        }

        if ($ed->date) {
            $ed->date = (string)$ed->date;
        }

        $ed->required_by = $position->training_positions->map(function ($r) {
            return ['id' => $r->id, 'title' => $r->title];
        })->sortBy('title')->values();

        if ($trainingPositionId == Position::GREEN_DOT_TRAINING
            && $ed->status != 'missing') {
            // Is a person a GD PNV? (i.e. does *not* have the GD position)
            $ed->is_green_dot_pnv = !PersonPosition::havePosition($personId, Position::DIRT_GREEN_DOT);
            // Perhaps a GD mentee?
            $ed->is_green_dot_mentee = PersonPosition::havePosition($personId, Position::GREEN_DOT_MENTEE);
            if ($ed->is_green_dot_mentee) {
                // Check to see if the person has signed up for or worked a GD mentee shift.
                $ed->mentee_slot = Slot::findFirstSignUp($personId, Position::GREEN_DOT_MENTEE, $year);
                $ed->mentee_timesheet = Timesheet::findLatestForPersonPosition($personId, Position::GREEN_DOT_MENTEE, $year);
            }
        }

        if ($ed->required_by->isEmpty()) {
            /*
             * Person could be a prospective ART ranger. An ART training is available, yet
             * holds no ART positions which requires training.
             * Let the user know which positions might require training
             */

            $requires = null;
            switch ($trainingPositionId) {
                case Position::GREEN_DOT_TRAINING:
                    $requires = ['id' => Position::GREEN_DOT_MENTEE, 'title' => 'Green Dot Mentee'];
                    break;
                case Position::SANDMAN_TRAINING:
                    $requires = ['id' => Position::SANDMAN, 'title' => 'Sandman'];
                    break;
                case Position::TOW_TRUCK_TRAINING:
                    $requires = ['id' => Position::TOW_TRUCK_MENTEE, 'title' => 'Tow Truck Mentee'];
                    break;
                case Position::HQ_FULL_TRAINING:
                    $requires = ['id' => Position::HQ_WINDOW, 'title' => 'HQ Window'];
                    break;
            }

            if ($requires) {
                $requires['not_granted'] = true;
                $ed->required_by = [$requires];
            }
        }

        return $ed;
    }

    /**
     * Is the given time within a grace period?
     * @param Carbon|string $time
     * @param $now
     * @return bool
     */
    private static function isTimeWithinGracePeriod($time, $now): bool
    {
        $time = is_string($time) ? Carbon::parse($time) : $time->clone();

        return $time->addHours(12)->gt($now);
    }

    /**
     * Find people who hold a training positions but not the trained positions
     *
     * @param int $positionId training position
     * @param array $personIds people to look up
     * @return array person ids who are ART alphas
     */

    public static function findArtAlphas(int $positionId, array $personIds): array
    {
        // TODO: extend to support multiple training positions
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

    public function retrievePeopleForTrainingCompleted(int $year): array
    {
        // TODO: extend to support multiple training positions

        $positionIds = [$this->id];
        if ($this->id == Position::HQ_FULL_TRAINING) {
            $positionIds[] = Position::HQ_REFRESHER_TRAINING;
        }
        $positionIds = implode(',', $positionIds);

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
                     AND position.id IN ($positionIds)
                     AND YEAR(slot.begins) = :year
                     AND passed = 1
                 ORDER BY slot.begins, description, callsign",
            [
                'year' => $year
            ]
        );

        $slots = [];
        $slotsByIds = [];

        foreach ($rows as $person) {
            $slotId = $person->slot_id;

            if (!isset($slotsByIds[$slotId])) {
                $slot = [
                    'slot_id' => $slotId,
                    'slot_description' => $person->slot_description,
                    'slot_begins' => $person->slot_begins,
                    'people' => []
                ];

                $slotsByIds[$slotId] = &$slot;
                $slots[] = &$slot;
                unset($slot);
            }

            $slotsByIds[$slotId]['people'][] = [
                'id' => $person->id,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'callsign' => $person->callsign,
                'email' => $person->email
            ];
        }

        return $slots;
    }

    /**
     * Find all the people who have an ART position(s) and who have not signed up
     * or completed training.
     *
     * @param int $year
     * @return array
     * @throws InvalidArgumentException*
     *       - if no other positions references this one as a needs-training position
     *       - if the position has no slots associated with it for a given year.
     */

    public function retrieveUntrainedPeople(int $year): array
    {
        $trainedPositionIds = Position::where('training_position_id', $this->id)->pluck('id');

        if ($trainedPositionIds->isEmpty()) {
            return [
                'not_signed_up' => [],
                'not_passed' => [],
            ];
            //        throw new \InvalidArgumentException('No other position references this position for training');
        }

        $positionIds = [$this->id];

        if ($this->id == Position::HQ_FULL_TRAINING) {
            $positionIds[] = Position::HQ_REFRESHER_TRAINING;
        }

        $trainingSlotIds = Slot::whereIn('position_id', $positionIds)->whereYear('begins', $year)->pluck('id');

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

        $positionIds = implode(',', $positionIds);
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
                SELECT id FROM slot WHERE position_id IN (" .
            $trainedPositionIds->implode(',') . "
                ) AND YEAR(begins)=$year
              )
             AND person_slot.person_id NOT IN (
                    SELECT person_id FROM person_slot
                     WHERE slot_id IN
                       (SELECT id FROM slot WHERE position_id IN ($positionIds) AND YEAR(begins)=$year)
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
                $row->slots = array_map(function ($slot) {
                    return [
                        'slot_id' => $slot->slot_id,
                        'begins' => $slot->begins,
                        'description' => $slot->description,
                        'title' => $slot->title,
                    ];
                }, $peopleSignedUp[$row->id]);
                $untrainedSignedup[] = $row;
            }
            usort(
                $untrainedSignedup,
                function ($a, $b) {
                    return strcasecmp($a->callsign, $b->callsign);
                }
            );
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
            WHERE person_slot.slot_id IN (" . $trainingSlotIds->implode(',') . ")
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
                 WHERE person_slot.person_id IN (" . $personIds->implode(',') . ")
                 AND  person_slot.slot_id IN (
                     SELECT id FROM slot WHERE position_id IN (" .
                $trainedPositionIds->implode(',') .
                ") AND YEAR(begins)=$year
                   )
                 ORDER BY person_slot.person_id asc,slot.begins asc"
            );

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

            ksort($untrainedNotPassed, SORT_NATURAL | SORT_FLAG_CASE);
            $untrainedNotPassed = array_values($untrainedNotPassed);
        }

        return [
            'not_signed_up' => $untrainedSignedup,
            'not_passed' => $untrainedNotPassed,
        ];
    }

    /**
     * Find all the dirt trainings for a person in a given year
     *
     * @param int $personId person to find
     * @param int $year year to look
     * @return Collection the slots & training status found
     */
    public static function retrieveDirtTrainingsForPersonYear(int $personId, int $year): Collection
    {
        $now = (string)now();
        return DB::table('person_slot')
            ->select(
                'slot.id as slot_id',
                'slot.description',
                'slot.begins',
                DB::raw('IFNULL(trainee_status.passed, FALSE) as passed'),
                'trainee_status.notes',
                'trainee_status.rank',
                DB::raw("('$now' >= slot.begins) as has_started")
            )->join('slot', function ($j) use ($year) {
                $j->on('slot.id', 'person_slot.slot_id');
                $j->whereYear('slot.begins', $year);
                $j->where('slot.position_id', Position::TRAINING);
            })
            ->leftJoin('trainee_status', function ($j) use ($personId) {
                $j->on('slot.id', 'trainee_status.slot_id');
                $j->where('trainee_status.person_id', $personId);
            })
            ->where('person_slot.person_id', $personId)
            ->orderBy('slot.begins')
            ->get();
    }

    /**
     * Retrieve all trainings up to the given year and position for ids
     *
     * @param $peopleIds people to look up
     * @param int $positionId position to find (usually Training)
     * @param int $year find trainings upto and including the year
     * @return Collection
     */

    public static function retrieveTrainingHistoryForIds($peopleIds, int $positionId, int $year): Collection
    {
        $now = (string) now();
        // Find the sign ups
        $rows = DB::table('slot')
            ->select(
                'person_slot.person_id as person_id',
                'slot.id as slot_id',
                'slot.description as slot_description',
                'slot.begins as slot_begins',
                DB::raw('YEAR(slot.begins) as slot_year'),
                DB::raw("IF(slot.ends < '$now', true, false) as slot_has_ended"),
                DB::raw('IFNULL(trainee_status.passed, FALSE) as training_passed'),
                'trainee_status.rank as training_rank',
                'trainee_status.feedback_delivered as feedback_delivered'
            )->join('person_slot', 'person_slot.slot_id', 'slot.id')
            ->join('person', 'person.id', 'person_slot.person_id')
            ->leftJoin('trainee_status', function ($j) {
                $j->on('trainee_status.person_id', '=', 'person_slot.person_id');
                $j->on('trainee_status.slot_id', '=', 'person_slot.slot_id');
            })
            ->whereYear('slot.begins', '<=', $year)
            ->where('slot.position_id', $positionId)
            ->whereIn('person_slot.person_id', $peopleIds)
            ->orderBy('person_slot.person_id')
            ->orderBy('slot.begins')
            ->get();


        foreach ($rows as $row) {
            $row->training_notes = TraineeNote::findAllForPersonSlot($row->person_id, $row->slot_id);
            $ps = PersonStatus::findForTime($row->person_id, $row->slot_begins);
            if ($ps) {
                $row->person_status = ($ps->new_status == Person::VINTAGE) ? Person::ACTIVE : $ps->new_status;
            } else {
                $row->person_status = 'unknown';
            }
        }
        return $rows->groupBy('person_id');
    }


    /**
     * Retrieve all trainers and their attendance for a given year
     *
     * @param int $year
     * @return array
     */
    public function retrieveTrainerAttendanceForYear(int $year): array
    {

        $teachingPositions = Position::TRAINERS[$this->id] ?? null;

        if (!$teachingPositions) {
            return [];
        }

        $slots = Slot::whereYear('begins', $year)
            ->whereIn('position_id', $teachingPositions)
            ->with(['position:id,title', 'person_slot.person:id,callsign', 'trainer_slot'])
            ->orderBy('begins')
            ->get();

        $trainers = [];

        foreach ($slots as $slot) {
            // Find the training slot that begins within a hour of the slot start time.
            $trainingSlot = Slot::where('description', $slot->description)
                ->whereRaw('begins BETWEEN DATE_SUB(?, INTERVAL 1 HOUR) AND ?', [$slot->begins, $slot->ends])
                ->where('position_id', $this->id)
                ->first();

            if ($trainingSlot == null) {
                continue;
            }

            foreach ($slot->person_slot as $ps) {
                if (!isset($trainers[$ps->person_id])) {
                    $trainers[$ps->person_id] = (object)[
                        'id' => $ps->person_id,
                        'callsign' => $ps->person ? $ps->person->callsign : 'Person #' . $ps->person_id,
                        'slots' => []
                    ];
                }

                $trainer = $trainers[$ps->person_id];
                $ts = $slot->trainer_status->firstWhere('person_id', $ps->person_id);
                $trainer->slots[] = (object)[
                    'id' => $slot->id,
                    'begins' => (string)$slot->begins,
                    'description' => $slot->description,
                    'position_title' => $slot->position->title,
                    'status' => $ts ? $ts->status : 'pending',
                    'training_slot_id' => $trainingSlot->id,
                ];
            }
        }

        usort($trainers, function ($a, $b) {
            return strcasecmp($a->callsign, $b->callsign);
        });

        return $trainers;
    }

    /**
     * Is this training an ART training?
     *
     * @return bool
     */
    public function getIsArtAttribute(): bool
    {
        return ($this->id != Position::TRAINING);
    }

    /**
     * Convert the position title into a slug (lower cased, dasherized)
     *
     * @return string "dirt" or title slug
     */

    public function getSlugAttribute(): string
    {
        return ($this->id == Position::TRAINING) ? 'dirt' : Str::slug($this->title);
    }
}
