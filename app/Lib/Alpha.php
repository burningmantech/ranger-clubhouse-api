<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\PersonIntake;
use App\Models\PersonIntakeNote;
use App\Models\PersonMentor;
use App\Models\PersonPhoto;
use App\Models\PersonSlot;
use App\Models\PersonStatus;
use App\Models\Position;
use App\Models\Slot;
use App\Models\Timesheet;
use App\Models\Training;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Alpha
{
    const array PERSON_RELATIONSHIPS = [
        'person_photo',
        'tshirt',
        'tshirt_secondary',
        'long_sleeve',
        'person_fka',
    ];

    const array ALPHA_SLOT_RELATIONSHIPS = [
        'person',
        'person.person_photo',
        'person.tshirt',
        'person.tshirt_secondary',
        'person.long_sleeve',
        'person.person_fka',
    ];

     /**
     * Find all mentors and indicate if they are on duty.
     */

    public static function retrieveMentors(): Collection
    {
        return self::retrieveMentorType(Position::MENTOR);
    }

    /**
     * Find all MITtens and indicate if they are on duty.
     */
    public static function retrieveMittens(): Collection
    {
        return self::retrieveMentorType(Position::MENTOR_MITTEN);
    }

    public static function retrieveMentorType(int $positionId): Collection
    {
        $year = current_year();

        return DB::table('person')
            ->select('person.id', 'person.callsign')
            ->selectRaw(
                'EXISTS (SELECT 1 FROM timesheet WHERE timesheet.person_id=person.id'
                . ' AND position_id=? AND YEAR(on_duty)=? AND off_duty IS NULL) as working',
                [$positionId, $year]
            )
            ->join('person_position', 'person_position.person_id', '=', 'person.id')
            ->where('person_position.position_id', $positionId)
            ->orderBy('callsign')
            ->get();
    }

    /**
     * Retrieve Mentees (anyone who had the alpha, prospective, or bonked status) in a given year.
     *
     * @param bool $noBonks if true bonked status is to be excluded
     * @param int $year the year to find the potentials
     * @param bool $haveTraining if true the person needs to be signed up (not necessarily pass) for training.
     */
    public static function retrieveMentees(bool $noBonks, int $year, bool $haveTraining): array
    {
        $statusQuery = PersonStatus::whereIn('new_status', [Person::ALPHA, Person::PROSPECTIVE])
            ->whereYear('created_at', $year);

        if ($noBonks) {
            $statusQuery->whereRaw(
                'NOT EXISTS (SELECT 1 FROM person_status ps WHERE ps.person_id=person_status.person_id'
                . ' AND YEAR(created_at)=? AND new_status=? LIMIT 1)',
                [$year, Person::BONKED]
            );
        }

        $potentialIds = $statusQuery->distinct()->pluck('person_id');

        $peopleQuery = Person::whereIntegerInRaw('id', $potentialIds)
            ->with(self::PERSON_RELATIONSHIPS)
            ->orderBy('callsign');

        if ($haveTraining) {
            $peopleQuery->whereRaw(
                'EXISTS (SELECT 1 FROM person_slot JOIN slot ON person_slot.slot_id=slot.id'
                . ' AND slot.position_id=? AND YEAR(slot.begins)=?'
                . ' WHERE person_slot.person_id=person.id LIMIT 1)',
                [Position::TRAINING, $year]
            );
        }

        return self::buildAlphaInformation($peopleQuery->get(), $year);
    }

    /**
     * Build up info on the given Alphas: per-person DTOs plus their Alpha slot sign-ups.
     */
    public static function buildAlphaInformation($people, int $year): array
    {
        if ($people->isEmpty()) {
            return [];
        }

        $peopleIds = $people->pluck('id')->toArray();
        $context = self::loadAlphaContext($peopleIds, $year);
        $slotsByPerson = self::retrieveAlphaSignUps($peopleIds, $year);

        $potentials = [];
        foreach ($people as $row) {
            $person = self::buildPerson($row, $year, $context);
            $slots = $slotsByPerson[$row->id] ?? null;
            if ($slots) {
                $person->alpha_slots = self::formatAlphaSlots($slots);
            }
            $potentials[] = $person;
        }

        return $potentials;
    }

    /**
     * Retrieve the intake history — {vc, rrn, mentor, trainings} notes & rankings for the given ids.
     */
    public static function retrieveIntakeHistory($pnvIds, ?int $year = null): array
    {
        $year ??= current_year();

        $intakeHistory = PersonIntake::whereIntegerInRaw('person_id', $pnvIds)
            ->orderBy('person_id')
            ->orderBy('year')
            ->get()
            ->groupBy('person_id');

        $intakeNotes = PersonIntakeNote::retrieveHistoryForPersonIds($pnvIds, $year);
        $trainings = Training::retrieveTrainingHistoryForIds($pnvIds, Position::TRAINING, $year);

        return [$intakeHistory, $intakeNotes, $trainings];
    }

    /**
     * Retrieve all Alphas (anyone who has the Alpha status) in the given year (defaults to current).
     */
    public static function retrieveAllAlphas(?int $year = null): array
    {
        $year ??= current_year();

        $alphas = Person::where('status', Person::ALPHA)
            ->with(self::PERSON_RELATIONSHIPS)
            ->orderBy('callsign')
            ->get();

        return self::buildAlphaInformation($alphas, $year);
    }

    /**
     * Retrieve all the Alpha slots in a given year with sign-ups and their intake data.
     */
    public static function retrieveAlphaScheduleForYear(int $year): Collection
    {
        $slots = Slot::where('begins_year', $year)
            ->where('position_id', Position::ALPHA)
            ->orderBy('begins')
            ->get();

        $signUps = PersonSlot::whereIntegerInRaw('slot_id', $slots->pluck('id')->toArray())
            ->with(self::ALPHA_SLOT_RELATIONSHIPS)
            ->get()
            ->sortBy('person.callsign', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $slotInfo = $slots->map(fn($slot) => (object) [
            'id' => $slot->id,
            'begins' => (string) $slot->begins,
            'description' => $slot->description,
            'people' => [],
        ]);
        $slotsById = $slotInfo->keyBy('id');

        $context = self::loadAlphaContext($signUps->pluck('person_id'), $year);

        foreach ($signUps as $signUp) {
            $slotsById[$signUp->slot_id]->people[] =
                self::buildPerson($signUp->person, $year, $context);
        }

        return $slotInfo;
    }

    /**
     * Retrieve all Alphas who had a mentor with a verdict (passed, bonked) in the current year.
     * Pending status is filtered out.
     */
    public static function retrieveVerdicts(): Collection
    {
        $year = current_year();

        $people = Person::select(
            'person.id',
            'callsign',
            'person.status',
            'first_name',
            'preferred_name',
            'last_name',
        )->selectRaw(
            '(SELECT person_mentor.status FROM person_mentor'
            . ' WHERE person_mentor.person_id=person.id AND mentor_year=?'
            . ' GROUP BY person_mentor.status LIMIT 1) as mentor_status',
            [$year]
        )->where('status', Person::ALPHA)
            ->orderBy('person.callsign')
            ->having('mentor_status', '!=', PersonMentor::PENDING)
            ->get();

        return $people->map(fn(Person $p) => [
            'id' => $p->id,
            'callsign' => $p->callsign,
            'status' => $p->status,
            'first_name' => $p->desired_first_name(),
            'last_name' => $p->last_name,
            'mentor_status' => $p->mentor_status,
        ])->values();
    }

    /**
     * Create or update the mentor assignments for the given Alphas.
     */
    public static function mentorAssignments(int $year, array $alphas): array
    {
        $personIds = array_column($alphas, 'person_id');
        $allMentorIds = array_values(array_unique(array_merge(
            ...array_map(fn($a) => $a['mentor_ids'], $alphas)
        )));

        $people = Person::whereIntegerInRaw('id', $personIds)->get()->keyBy('id');
        $existingByPerson = PersonMentor::whereIntegerInRaw('person_id', $personIds)
            ->where('mentor_year', $year)
            ->get()
            ->groupBy('person_id');
        $callsigns = Person::select('id', 'callsign')
            ->whereIntegerInRaw('id', $allMentorIds)
            ->get()
            ->keyBy('id');

        $results = [];
        foreach ($alphas as $alpha) {
            $personId = $alpha['person_id'];
            $person = $people[$personId] ?? null;
            if (!$person) {
                $results[] = ['person_id' => $personId, 'error' => 'person not found'];
                continue;
            }

            $desiredMentorIds = array_values(array_unique($alpha['mentor_ids']));
            $existingMentors = $existingByPerson[$personId] ?? collect();

            $mentors = self::syncMentors(
                $person,
                $year,
                $alpha['status'],
                $desiredMentorIds,
                $existingMentors
            );

            usort($mentors, fn($a, $b) => strcasecmp(
                $callsigns[$a['mentor_id']]->callsign ?? '',
                $callsigns[$b['mentor_id']]->callsign ?? ''
            ));

            $results[] = [
                'person_id' => $person->id,
                'mentors' => $mentors,
                'status' => $alpha['status'],
            ];
        }

        return $results;
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    /**
     * Build up the DTO for a single PNV: personal info, mentors, intake notes/rankings,
     * training status, and Alpha eligibility flags.
     *
     * @param array{
     *     intakeHistory?: mixed,
     *     intakeNotes?: mixed,
     *     trainings?: mixed,
     *     signedIn?: Collection,
     *     allHistories?: mixed
     * } $context
     */
    private static function buildPerson(Person $person, int $year, array $context): object
    {
        $intakeHistory = $context['intakeHistory'] ?? collect();
        $intakeNotes = $context['intakeNotes'] ?? [];
        $trainings = $context['trainings'] ?? [];
        $signedIn = $context['signedIn'] ?? collect();
        $allHistories = $context['allHistories']
            ?? PersonMentor::retrieveBulkMentorHistory([$person->id]);

        $personId = $person->id;
        $photoApproved = $person->person_photo
            && $person->person_photo->status == PersonPhoto::APPROVED;

        $potential = (object) [
            'id' => $personId,
            'callsign' => $person->callsign,
            'callsign_approved' => $person->callsign_approved,
            'fkas' => $person->formerlyKnownAsArray(true),
            'pronouns' => $person->pronouns,
            'pronouns_custom' => $person->pronouns_custom,
            'known_rangers' => $person->knownRangersArray(),
            'known_pnvs' => $person->knownPnvsArray(),
            'first_name' => $person->desired_first_name(),
            'last_name' => $person->last_name,
            'email' => $person->email,
            'status' => $person->status,
            'gender_identity' => $person->gender_identity,
            'gender_custom' => $person->gender_custom,
            'mentor_history' => PersonMentor::retrieveMentorHistory($personId, $allHistories),
            'photo_approved' => $photoApproved,
            'city' => $person->city,
            'state' => $person->state,
            'country' => $person->country,
            'teeshirt_size_style' => $person->tshirt->title ?? 'Unknown',
            'tshirt_secondary_size' => $person->tshirt_secondary->title ?? 'Unknown',
            'longsleeveshirt_size_style' => $person->long_sleeve->title ?? 'Unknown',
            'trained' => false,
            'trainings' => $trainings[$personId] ?? [],
            'on_alpha_shift' => $signedIn->get($personId),
            'alpha_status_eligible' => false,
            'alpha_position_eligible' => false,
            'have_mentor_flags' => false,
            'personnel_issue' => false,
        ];

        if ($photoApproved) {
            $potential->photo_url = $person->person_photo->image_url ?? null;
        }

        $teamHistory = $intakeHistory[$personId] ?? null;
        $personNotes = $intakeNotes[$personId] ?? null;

        self::populateIntakeRanks($potential, $teamHistory, $year);
        self::populateIntakeTeams($potential, $teamHistory, $personNotes);
        self::evaluateEligibility($potential, $year, $photoApproved);

        return $potential;
    }

    /**
     * Load the per-person context maps needed to hydrate Alpha DTOs.
     *
     * @return array{intakeHistory:mixed, intakeNotes:mixed, trainings:mixed, signedIn:Collection, allHistories:mixed}
     */
    private static function loadAlphaContext($peopleIds, int $year): array
    {
        [$intakeHistory, $intakeNotes, $trainings] = self::retrieveIntakeHistory($peopleIds, $year);

        return [
            'intakeHistory' => $intakeHistory,
            'intakeNotes' => $intakeNotes,
            'trainings' => $trainings,
            'signedIn' => Timesheet::retrieveSignedInPeople(Position::ALPHA),
            'allHistories' => PersonMentor::retrieveBulkMentorHistory($peopleIds),
        ];
    }

    private static function retrieveAlphaSignUps(array $peopleIds, int $year): Collection
    {
        return DB::table('person_slot')
            ->select('slot.id as slot_id', 'slot.description', 'slot.begins', 'person_slot.person_id')
            ->join('slot', function ($j) use ($year) {
                $j->on('slot.id', 'person_slot.slot_id');
                $j->where('slot.begins_year', $year);
                $j->where('slot.position_id', Position::ALPHA);
            })
            ->whereIntegerInRaw('person_slot.person_id', $peopleIds)
            ->orderBy('slot.begins')
            ->get()
            ->groupBy('person_id');
    }

    private static function formatAlphaSlots(iterable $slots): array
    {
        $formatted = [];
        foreach ($slots as $slot) {
            $formatted[] = [
                'slot_id' => $slot->slot_id,
                'begins' => $slot->begins,
                'description' => $slot->description,
            ];
        }
        return $formatted;
    }

    private static function populateIntakeRanks(object $potential, $teamHistory, int $year): void
    {
        $potential->rrn_ranks = [];
        $potential->vc_ranks = [];

        if (empty($teamHistory)) {
            return;
        }

        foreach ($teamHistory as $row) {
            if ($row->year == $year && $row->personnel_rank == Intake::FLAG) {
                $potential->personnel_issue = true;
            }
            if ($row->rrn_rank > 0 && $row->rrn_rank != Intake::AVERAGE) {
                $potential->rrn_ranks[] = ['year' => $row->year, 'rank' => $row->rrn_rank];
            }
            if ($row->vc_rank > 0 && $row->vc_rank != Intake::AVERAGE) {
                $potential->vc_ranks[] = ['year' => $row->year, 'rank' => $row->vc_rank];
            }
        }
    }

    private static function populateIntakeTeams(object $potential, $teamHistory, $personNotes): void
    {
        $haveMentorFlag = false;
        $discard = false;

        $potential->mentor_team = Intake::buildIntakeTeam('mentor', $teamHistory, $personNotes, $haveMentorFlag);
        $potential->rrn_team = Intake::buildIntakeTeam('rrn', $teamHistory, $personNotes, $discard);
        $potential->vc_team = Intake::buildIntakeTeam('vc', $teamHistory, $personNotes, $discard);
        $potential->personnel_team = Intake::buildIntakeTeam('personnel', $teamHistory, $personNotes, $discard);

        $potential->have_mentor_flags = $haveMentorFlag;
    }

    private static function evaluateEligibility(object $potential, int $year, bool $photoApproved): void
    {
        foreach ($potential->trainings as $training) {
            if ($training->slot_year == $year && $training->training_passed) {
                $potential->trained = true;
                break;
            }
        }

        $isProspectiveReady = $potential->status == Person::PROSPECTIVE
            && $potential->callsign_approved
            && $photoApproved;

        $potential->alpha_status_eligible = $isProspectiveReady
            || $potential->status == Person::ALPHA;

        $potential->alpha_position_eligible = $potential->trained
            && $potential->callsign_approved
            && $photoApproved;
    }

    /**
     * Reconcile the stored mentor set for a person with the desired set.
     *
     * @return array<int, array{person_mentor_id:int, mentor_id:int}>
     * @throws \Throwable
     */

    private static function syncMentors(
        Person $person,
        int $year,
        string $status,
        array $desiredMentorIds,
        $existingMentors
    ): array {
        $currentIds = $existingMentors->pluck('mentor_id')->unique()->values()->toArray();
        $sameSet = !array_diff($desiredMentorIds, $currentIds)
            && !array_diff($currentIds, $desiredMentorIds);

        $mentors = [];

        DB::transaction(function () use (
            $person, $year, $status, $sameSet, $desiredMentorIds, $existingMentors, &$mentors
        ) {
            if ($sameSet) {
                foreach ($existingMentors as $row) {
                    $row->status = $status;
                    $row->auditReason = 'mentor status change';
                    $row->save();
                    $mentors[] = [
                        'person_mentor_id' => $row->id,
                        'mentor_id' => $row->mentor_id,
                    ];
                }
                return;
            }

            PersonMentor::where('person_id', $person->id)
                ->where('mentor_year', $year)
                ->delete();

            foreach ($desiredMentorIds as $mentorId) {
                $created = PersonMentor::create([
                    'person_id' => $person->id,
                    'mentor_id' => $mentorId,
                    'status' => $status,
                    'mentor_year' => $year,
                ]);
                $mentors[] = [
                    'person_mentor_id' => $created->id,
                    'mentor_id' => $created->mentor_id,
                ];
            }
        });

        return $mentors;
    }
}
