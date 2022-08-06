<?php

namespace App\Models;

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
        'slug',
        'graduation_info'
    ];

    const FAIL = 'fail';        // Person was not marked as pass
    const PASS = 'pass';        // Person passed or taught a session
    const NO_SHOW = 'no-show';  // person (as a trainer) was no show.
    const PENDING = 'pending'; // Training hasn't happened yet, or still within the grace period.
    const NO_SHIFT = 'no-shift'; // No training sign up was found

    const GRACE_PERIOD_HOURS = 72;

    /**
     * Is the person trained for a position in a given year?
     *
     * @param Person $person person to check
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
            && !self::didPersonPassForYear($person, Position::TRAINING, $year)) {
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
        if (self::didPersonPassForYear($person, $trainingId, $year)) {
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
                'type' => $position->type,
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

            if (!$trainer && $trainingId && !self::didPersonPassForYear($person, $trainingId, $year)) {
                $info->is_untrained = true;
                $info->training_position_id = $trainingId;
                $info->training_title = ($trainingId == Position::TRAINING) ? "In-Person Training" : Position::retrieveTitle($trainingId);
            }
        }

        return $personPositions;
    }

    /**
     * Did the person pass training in a given year? check to see if they were a teacher or student.
     *
     * @param Person|int $person person to check
     * @param int $positionId position in question
     * @param int $year the year to check in
     * @return bool true if the person passed in the given year
     */

    public static function didPersonPassForYear(Person|int $person, int $positionId, int $year): bool
    {
        $personId = is_a($person, Person::class) ? $person->id : $person;

        if ($positionId == Position::TRAINING) {
            $isBinary = Timesheet::isPersonBinary($person);

            $otOnly = setting($isBinary ? 'OnlineTrainingOnlyForBinaries' : 'OnlineTrainingOnlyForVets');
            if ($otOnly) {
                return PersonOnlineTraining::didCompleteForYear($personId, current_year());
            }
        }

        return TraineeStatus::didPersonPassForYear($personId, $positionId, $year)
            || TrainerStatus::didPersonTeachForYear($personId, $positionId, $year);
    }

    /**
     * Did any of the ids pass training? Online Training is not considered.
     *
     * @param array $personIds
     * @param int $positionId
     * @param int $year
     * @return mixed
     */

    public static function didIdsPassForYear(array $personIds, int $positionId, int $year)
    {
        $traineePositionIds = [$positionId];

        if ($positionId == Position::HQ_FULL_TRAINING) {
            $traineePositionIds[] = Position::HQ_REFRESHER_TRAINING;
        }

        $sql = DB::table('trainee_status')
            ->select('trainee_status.person_id')
            ->join('slot', 'slot.id', 'trainee_status.slot_id')
            ->whereIn('trainee_status.person_id', $personIds)
            ->whereIn('slot.position_id', $traineePositionIds)
            ->whereYear('slot.begins', $year)
            ->where('passed', 1);

        $trainerPositionIds = Position::TRAINERS[$positionId] ?? null;
        if ($trainerPositionIds) {
            $trainerSql = DB::table('trainer_status')
                ->select('trainer_status.person_id')
                ->join('slot', 'slot.id', 'trainer_status.slot_id')
                ->whereIn('trainer_status.person_id', $personIds)
                ->whereIn('slot.position_id', $trainerPositionIds)
                ->whereYear('slot.begins', $year)
                ->where('status', TrainerStatus::ATTENDED);
            $sql->union($trainerSql);
        }

        return $sql->get()->reduce(function ($hash, $row) {
            $hash[$row->person_id] = true;
            return $hash;
        }, []);
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
     * Find out the education status for a given person, position and year
     *
     * @param int $personId
     * @param Position $position
     * @param int $year
     * @return object
     */

    public static function retrieveEducation(int $personId, Position $position, int $year): object
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
                ->where('slot.active', true)
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
            $ed->slot_id = $trainer->id;
            $ed->is_trainer = true;
            $ed->status = self::PASS;
        } elseif ($training) {
            // If the person did not pass, BUT there is a later sign up use the later sign up.
            if (!$training->passed && $slot && $slot->id != $training->id && $slot->ends->gt($training->ends)) {
                $ed->location = $slot->description;
                $ed->date = $slot->begins;
                $ed->slot_id = $slot->id;
                $ed->status = self::isTimeWithinGracePeriod($slot->ends, $now) ? self::PENDING : self::FAIL;
            } else {
                $ed->slot_id = $training->id;
                $ed->location = $training->description;
                $ed->date = $training->begins;
                if (!$training->passed && self::isTimeWithinGracePeriod($training->ends, $now)) {
                    $ed->status = self::PENDING;
                } else {
                    $ed->status = ($training->passed ? self::PASS : self::FAIL);
                }
            }
        } elseif ($slot) {
            $ed->slot_id = $slot->id;
            $ed->location = $slot->description;
            $ed->date = $slot->begins;
            // Training signed up and no trainee status
            $ed->status = self::isTimeWithinGracePeriod($slot->ends, $now) ? self::PENDING : self::FAIL;
        } elseif ($teachingPositions && !$taught->isEmpty()) {
            // find the first pending session
            $slot = $taught->firstWhere('status', TrainerStatus::PENDING);

            if (!$slot) {
                // nothing found - try to use a no-show
                $slot = $taught->firstWhere('status', TrainerStatus::NO_SHOW);
                if (!$slot) {
                    // okay, try the first session
                    $slot = $taught->first();
                }
            }

            $ed->slot_id = $slot->id;
            $ed->location = $slot->description;
            $ed->date = $slot->begins;
            $ed->status = $slot->status ?? self::PENDING;
            $ed->is_trainer = true;
        } else {
            // Nothing found.
            $ed->status = self::NO_SHIFT;
        }

        if ($ed->date) {
            $ed->date = (string)$ed->date;
        }

        $ed->required_by = $position->training_positions->map(function ($r) {
            return ['id' => $r->id, 'title' => $r->title];
        })->sortBy('title')->values();

        if ($trainingPositionId == Position::GREEN_DOT_TRAINING
            && $ed->status != self::NO_SHIFT) {
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
             * Person could be a prospective special team ranger. If an ART is available, yet
             * holds no special team positions which requires training.
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
     * Is the given time within a grace period? (up to 12 hours afterwards)
     * @param Carbon|string $time
     * @param $now
     * @return bool
     */
    private static function isTimeWithinGracePeriod($time, $now): bool
    {
        $time = is_string($time) ? Carbon::parse($time) : $time->clone();

        return $time->addHours(self::GRACE_PERIOD_HOURS)->gt($now);
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
            ->whereIntegerInRaw('position_id', $trained->pluck('id')->toArray())
            ->whereIntegerInRaw('person_id', $personIds)
            ->get()
            ->keyBy('person_id');

        $alphaIds = [];
        foreach ($personIds as $id) {
            if (isset($people[$id])) {
                continue;
            }
            $alphaIds[] = $id;
        }

        return $alphaIds;
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
     * @param array|mixed $peopleIds people to look up
     * @param int $positionId position to find (usually Training)
     * @param int $year find trainings upto and including the year
     * @return Collection
     */

    public static function retrieveTrainingHistoryForIds($peopleIds, int $positionId, int $year): Collection
    {
        $now = (string)now();
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

    /**
     * Obtain graduation information if any
     *
     * @return array|null
     */

    public function getGraduationInfoAttribute(): ?array
    {
        $graduate = Position::ART_GRADUATE_TO_POSITIONS[$this->id] ?? null;
        if (!$graduate) {
            return null;
        }

        $result = [
            'positions' => array_map(fn($p) => ['id' => $p, 'title' => Position::retrieveTitle($p)], $graduate['positions'])
        ];

        $fullyGraduatedPosition = $graduate['veteran'] ?? null;
        if ($fullyGraduatedPosition) {
            $result['fully_graduated_position'] = [
                'id' => $fullyGraduatedPosition,
                'title' => Position::retrieveTitle($fullyGraduatedPosition)
            ];
        }

        return $result;
    }
}
