<?php

namespace App\Models;

/*
 * Extended Model
 *
 * Holds the training session information (aka a slot with extras).
 */

use Illuminate\Support\Collection;

class TrainingSession extends Slot
{
    // don't calculate slot credits
    protected $hidden = ['credits'];

    const string ELIGIBILITY_AUDITOR = 'auditor';
    const string ELIGIBILITY_FULLY_GRADUATED = 'fully-graduated';
    const string ELIGIBILITY_GRADUATED = 'graduated';
    const string ELIGIBILITY_CANDIDATE = 'candidate';
    const string ELIGIBILITY_NOT_PASSED = 'not-passed';
    const string ELIGIBILITY_REQUIREMENTS_INCOMPLETE = 'requirements-incomplete';

    private ?array $trainersCache = null;

    /**
     * Find all training sessions (slots) for a training position and year.
     */
    public static function findAllForTrainingYear(int $trainingId, int $year): Collection
    {
        $positionIds = [$trainingId];

        // TODO: Extend to multiple training positions
        if ($trainingId == Position::HQ_FULL_TRAINING) {
            $positionIds[] = Position::HQ_REFRESHER_TRAINING;
        }

        return self::where('begins_year', $year)
            ->whereIn('position_id', $positionIds)
            ->orderBy('begins')
            ->get();
    }

    /**
     * Find the students for a training session.
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
     * is_art_prospective: if true the person has not worked an ART position before
     * is_inactive: if true, the person is inactive
     * is_retired: if true, the person is retired
     * scored: if true, the person has been marked passed or failed
     * notes: trainer notes for this person
     * rank: trainer ranking - NULL, 1 to 4
     * passed: person has passed or failed training
     *
     * @return array<int, array<string, mixed>>
     */
    public function retrieveStudents(): array
    {
        $personSlots = $this->retrieveBasicStudentRoster();
        $personIds = $personSlots->pluck('person_id')->toArray();
        $isDirtTraining = ($this->position_id == Position::TRAINING);

        $peopleYearsRangered = Timesheet::yearsRangeredCountForIds($personIds);
        $artAlphaIds = $isDirtTraining
            ? []
            : Training::findArtAlphas($this->position_id, $personIds);

        $traineeStatuses = TraineeStatus::where('slot_id', $this->id)->get()->keyBy('person_id');
        $traineeNotes = TraineeNote::where('slot_id', $this->id)
            ->orderBy('created_at')
            ->with('person_source:id,callsign')
            ->get()
            ->groupBy('person_id');

        $peopleStatus = PersonStatus::findStatusForIdsTime($personIds, $this->ends);
        $personnelIssues = PersonIntake::retrievePersonnelIssueForIdsYear($personIds, $this->ends->year);
        $positionsToReport = Position::where('on_trainer_report', true)->get()->keyBy('id');

        return $personSlots->map(fn($row) => $this->buildStudentInfo(
            $row,
            $peopleStatus,
            $traineeStatuses->get($row->person_id),
            $traineeNotes,
            $peopleYearsRangered,
            $personnelIssues,
            $positionsToReport,
            $artAlphaIds,
            $isDirtTraining
        ))->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStudentInfo(
        PersonSlot      $row,
        Collection      $peopleStatus,
        ?TraineeStatus  $traineeStatus,
        Collection      $traineeNotes,
        array           $yearsRangered,
        array           $personnelIssues,
        Collection      $positionsToReport,
        array           $artAlphaIds,
        bool            $isDirtTraining
    ): array {
        $person = $row->person;
        $status = $peopleStatus->has($person->id)
            ? $peopleStatus[$person->id]->new_status
            : 'unknown';

        $info = [
            'id' => $person->id,
            'callsign' => $person->callsign,
            'first_name' => $person->first_name,
            'preferred_name' => $person->preferred_name,
            'last_name' => $person->last_name,
            'status' => $status,
            'current_status' => $person->status,
            'photo_url' => $person->person_photo?->profileUrlApproved(),
            'email' => $person->email,
            'years' => $yearsRangered[$person->id] ?? 0,
            // Remove this once the frontend is using team_short_titles.
            'position_ids' => $person->person_position->pluck('position_id'),
            // Currently we're just using position names as stand-ins for team names,
            // but this will eventually be real team short titles.
            'team_short_titles' => $this->teamShortTitles($person, $positionsToReport),
            'notes' => $traineeNotes[$person->id] ?? [],
            'fkas' => $person->formerlyKnownAsArray(true),
            'signed_up_at' => (string)$row->created_at,
        ];

        if (in_array($person->id, $personnelIssues)) {
            $info['personnel_issue'] = true;
        }

        if ($isDirtTraining) {
            if ($status != Person::ACTIVE && $status != 'unknown') {
                $info['need_ranking'] = true;
            }
        } else if (in_array($person->id, $artAlphaIds)) {
            $info['is_art_prospective'] = true;
        }

        $info += $this->statusFlags($status);
        $info += $this->traineeStatusFields($traineeStatus);

        return $info;
    }

    /**
     * @return array<int, string>
     */
    private function teamShortTitles(Person $person, Collection $positionsToReport): array
    {
        return $person->person_position
            ->filter(fn($pp) => $positionsToReport->has($pp->position_id))
            ->map(function ($pp) use ($positionsToReport) {
                $position = $positionsToReport[$pp->position_id];
                return !empty($position->short_title) ? $position->short_title : $position->title;
            })
            ->values()
            ->toArray();
    }

    /**
     * @return array<string, bool>
     */
    private function statusFlags(string $status): array
    {
        if ($status == Person::INACTIVE || $status == Person::INACTIVE_EXTENSION) {
            return ['is_inactive' => true];
        }
        if ($status == Person::RETIRED) {
            return ['is_retired' => true];
        }
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function traineeStatusFields(?TraineeStatus $traineeStatus): array
    {
        if (!$traineeStatus) {
            return ['scored' => false, 'feedback_delivered' => false];
        }
        return [
            'scored' => true,
            'rank' => $traineeStatus->rank,
            'passed' => $traineeStatus->passed,
            'feedback_delivered' => $traineeStatus->feedback_delivered,
        ];
    }

    /**
     * Retrieve the people signed up for this training.
     */
    public function retrieveBasicStudentRoster(): Collection
    {
        $personSlots = PersonSlot::with([
            'person',
            'person.person_position',
            'person.person_photo',
            'person.person_fka'
        ])->where('slot_id', $this->id)->get();

        return $personSlots
            ->sortBy(fn($p) => $p->person->callsign, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Retrieve all the trainers for this session.
     *
     * The trainer's information has structure:
     *
     * slot: the trainer's slot found (full slot record: id, description, begins, etc.)
     * position_title: the position title for the slot
     * trainers: found trainer array (id, callsign, first_name, last_name, email)
     *
     * @return array<int, array<string, mixed>>
     */
    public function retrieveTrainers(): array
    {
        $trainerPositionIds = Position::TRAINERS[$this->position_id] ?? null;
        if (!$trainerPositionIds) {
            return [];
        }

        $trainers = [];
        foreach ($trainerPositionIds as $trainerPositionId) {
            $trainerGroup = $this->buildTrainerGroup($trainerPositionId);
            if ($trainerGroup !== null) {
                $trainers[] = $trainerGroup;
            }
        }

        return $trainers;
    }

    /**
     * Build a single trainer group (one trainer position) for this session.
     *
     * @return array<string, mixed>|null
     */
    private function buildTrainerGroup(int $trainerPositionId): ?array
    {
        // Find the trainer's slot that begins within an hour of the slot start time.
        $trainerSlot = Slot::where('description', $this->description)
            ->whereRaw('begins BETWEEN DATE_SUB(?, INTERVAL 1 HOUR) AND ?', [$this->begins, $this->ends])
            ->where('position_id', $trainerPositionId)
            ->first();

        if ($trainerSlot === null) {
            return null;
        }

        $trainerRows = PersonSlot::with(['person:id,callsign,first_name,preferred_name,last_name,email'])
            ->where('slot_id', $trainerSlot->id)
            ->get()
            ->sortBy(fn($p) => $p->person->callsign, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $trainerStatuses = $trainerRows->isEmpty()
            ? collect()
            : TrainerStatus::findBySlotPersonIds($this->id, $trainerRows->pluck('person_id'))->keyBy('person_id');

        $instructors = $trainerRows->map(function ($row) use ($trainerStatuses, $trainerSlot) {
            $person = $row->person;
            $status = $trainerStatuses->get($person->id);
            return [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'email' => $person->email,
                'status' => $status->status ?? 'pending',
                'trainer_slot_id' => $trainerSlot->id,
                'is_lead' => $status->is_lead ?? false,
            ];
        });

        return [
            'slot' => $trainerSlot,
            'position_title' => Position::retrieveTitle($trainerPositionId),
            'trainers' => $instructors,
            'is_primary_trainer' => $this->isPrimaryTrainerPosition($trainerPositionId),
        ];
    }

    private function isPrimaryTrainerPosition(int $trainerPositionId): bool
    {
        return $this->isArt()
            || $trainerPositionId == Position::TRAINER_UBER
            || $trainerPositionId == Position::TRAINER;
    }

    /**
     * Retrieve the legend for team names to be shown with the report.
     *
     * This information has the following structure:
     *
     * title: the full position name, e.g. "Green Dot Sanctuary"
     * short_title: an optional short position name, e.g. "GDSanc"
     *
     * @return array<int, array<string, mixed>>
     */
    public function retrieveTeamNameLegend(): array
    {
        return Position::where('on_trainer_report', true)
            ->select('title', 'short_title')
            ->orderBy('title')
            ->get()
            ->toArray();
    }

    /**
     * Report on which students can graduate.
     *
     * @return array<string, mixed>|null
     */
    public function graduationCandidates(): ?array
    {
        $graduate = Position::ART_GRADUATE_TO_POSITIONS[$this->position_id] ?? null;
        if (!$graduate) {
            return null;
        }

        $fullyGraduatedPosition = $graduate['veteran'] ?? null;
        $positions = $graduate['positions'];
        $menteeRequirements = $graduate['mentee_requirements'] ?? null;

        $students = $this->retrieveBasicStudentRoster();
        $traineeStatuses = TraineeStatus::where('slot_id', $this->id)->get()->keyBy('person_id');

        $people = [];
        foreach ($students as $student) {
            $person = $student->person;
            $info = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status,
            ];

            $info['eligibility'] = $this->resolveEligibility(
                $person,
                $traineeStatuses,
                $positions,
                $fullyGraduatedPosition,
                $menteeRequirements,
                $info
            );

            $people[] = $info;
        }

        $result = [
            'status' => 'success',
            'people' => $people,
            'positions' => array_map(
                fn($p) => ['id' => $p, 'title' => Position::retrieveTitle($p)],
                $positions
            ),
        ];

        if ($fullyGraduatedPosition) {
            $result['fully_graduated_position'] = [
                'id' => $fullyGraduatedPosition,
                'title' => Position::retrieveTitle($fullyGraduatedPosition),
            ];
        }

        return $result;
    }

    /**
     * Resolve a person's graduation eligibility.
     *
     * @param array<int, int> $positions
     * @param array<string, mixed>|null $menteeRequirements
     * @param array<string, mixed> $info
     */
    private function resolveEligibility(
        Person     $person,
        Collection $traineeStatuses,
        array      $positions,
        ?int       $fullyGraduatedPosition,
        ?array     $menteeRequirements,
        array      &$info
    ): string {
        if ($person->status == Person::AUDITOR) {
            return self::ELIGIBILITY_AUDITOR;
        }

        $positionsGranted = $person->person_position;

        if ($fullyGraduatedPosition && $positionsGranted->firstWhere('position_id', $fullyGraduatedPosition)) {
            return self::ELIGIBILITY_FULLY_GRADUATED;
        }

        if ($positionsGranted->contains(fn($p) => in_array($p->position_id, $positions))) {
            return self::ELIGIBILITY_GRADUATED;
        }

        if (!$traineeStatuses->has($person->id)) {
            return self::ELIGIBILITY_NOT_PASSED;
        }

        if ($menteeRequirements && !$this->applyMenteeRequirements($person, $menteeRequirements, $info)) {
            return self::ELIGIBILITY_REQUIREMENTS_INCOMPLETE;
        }

        return self::ELIGIBILITY_CANDIDATE;
    }

    /**
     * Check mentee requirements for graduation candidates.
     * Candidates must have sufficient recent volunteer shifts and hours to graduate.
     *
     * @param array<string, mixed> $requirements
     * @param array<string, mixed> $info
     */
    private function applyMenteeRequirements(Person $person, array $requirements, array &$info): bool
    {
        $query = Timesheet::query()
            ->join('position', 'position.id', '=', 'timesheet.position_id')
            ->where('timesheet.person_id', $person->id)
            ->groupBy('timesheet.person_id')
            ->selectRaw(
                'timesheet.person_id,
                 COUNT(*) as shift_count,
                 SUM(TIMESTAMPDIFF(SECOND, timesheet.on_duty, COALESCE(timesheet.off_duty, NOW()))) as total_seconds'
            );

        if (!empty($requirements['positions'])) {
            $query->whereIn('position.id', $requirements['positions']);
        }

        $stats = $query->first();

        $shiftCount = $stats?->shift_count ?? 0;
        $totalHours = ($stats?->total_seconds ?? 0) / 3600;
        $requiredShifts = $requirements['shift_count'];
        $requiredHours = $requirements['hour_count'];
        $requiredEvents = $requirements['event_count'] ?? 0;

        $totalEvents = 0;
        if ($requiredEvents) {
            $thisYear = current_year();
            $totalEvents = count(array_filter(
                $person->years_as_ranger,
                fn($year) => $year != $thisYear
            ));
        }

        if ($shiftCount < $requiredShifts || $totalHours < $requiredHours || $totalEvents < $requiredEvents) {
            $info['shifts'] = $shiftCount;
            $info['hours'] = $totalHours;
            $info['events'] = $totalEvents;
            $info['req_hours'] = $requiredHours;
            $info['req_shifts'] = $requiredShifts;
            $info['req_events'] = $requiredEvents;
            return false;
        }

        return true;
    }

    public function getTrainersAttribute(): array
    {
        return $this->trainersCache ??= $this->retrieveTrainers();
    }
}
