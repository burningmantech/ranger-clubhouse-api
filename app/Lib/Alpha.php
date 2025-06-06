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
    const array ALPHA_RELATIONSHIPS = [
        'person',
        'person.person_photo',
        'person.tshirt',
        'person.tshirt_secondary',
        'person.long_sleeve',
        'person.person_fka',
    ];

    /**
     * Find all mentors and indicate if they are on duty.
     *
     * @return Collection
     */

    public static function retrieveMentors(): Collection
    {
        return self::retrieveMentorType(Position::MENTOR);
    }

    /**
     * Find all MITtens and indicate if they are on duty.
     *
     * @return Collection
     */

    public static function retrieveMittens(): Collection
    {
        return self::retrieveMentorType(Position::MENTOR_MITTEN);
    }

    public static function retrieveMentorType($positionId): Collection
    {
        $year = current_year();

        return DB::table('person')
            ->select(
                'person.id',
                'person.callsign',
                DB::raw("EXISTS (SELECT 1 FROM timesheet WHERE timesheet.person_id=person.id AND position_id=$positionId AND YEAR(on_duty)=$year AND off_duty IS NULL) as working")
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
     * @return array
     */

    public static function retrieveMentees(bool $noBonks, int $year, bool $haveTraining): array
    {
        $sql = PersonStatus::whereIn('new_status', [Person::ALPHA, Person::PROSPECTIVE])
            ->whereYear('created_at', $year)
            ->orderBy('person_id')
            ->orderBy('created_at');

        if ($noBonks) {
            $sql->whereRaw("NOT EXISTS (SELECT 1 FROM person_status ps WHERE ps.person_id=person_status.person_id AND YEAR(created_at)=? AND new_status=? LIMIT 1)",
                [$year, Person::BONKED]
            );
        }

        $potentialIds = $sql->get()->groupBy('person_id')->keys();

        $sql = Person::whereIntegerInRaw('id', $potentialIds)
            ->with([
                'person_photo',
                'tshirt',
                'tshirt_secondary',
                'long_sleeve',
                'person_fka'
            ])->orderBy('callsign');

        if ($haveTraining) {
            $sql->whereRaw("EXISTS
                (SELECT 1 FROM person_slot JOIN slot ON person_slot.slot_id=slot.id
                    AND slot.position_id=? AND YEAR(slot.begins)=?
                WHERE person_slot.person_id=person.id LIMIT 1)", [Position::TRAINING, $year]);
        }

        $rows = $sql->get();

        return self::buildAlphaInformation($rows, $year);
    }

    /**
     * Build up info on the given Alphas.
     * - find all Alpha slot sign ups for each Alpha
     * - build up the intake history
     *
     * @param $people
     * @param int $year
     * @return array
     */

    public static function buildAlphaInformation($people, int $year): array
    {
        if ($people->isEmpty()) {
            return [];
        }

        $peopleIds = $people->pluck('id')->toArray();

        list ($intakeHistory, $intakeNotes, $trainings) = self::retrieveIntakeHistory($peopleIds);

        // Find out the signed up shifts
        $alphaSlots = DB::table('person_slot')
            ->select(
                'slot.id as slot_id',
                'slot.description',
                'slot.begins',
                'person_slot.person_id'
            )->join('slot', function ($j) use ($year) {
                $j->on('slot.id', 'person_slot.slot_id');
                $j->where('slot.begins_year', $year);
                $j->where('slot.position_id', Position::ALPHA);
            })
            ->whereIntegerInRaw('person_slot.person_id', $peopleIds)
            ->orderBy('slot.begins')
            ->get()
            ->groupBy('person_id');

        $signedIn = Timesheet::retrieveSignedInPeople(Position::ALPHA);
        $allHistories = PersonMentor::retrieveBulkMentorHistory($peopleIds);

        $potentials = [];
        foreach ($people as $row) {
            $person = self::buildPerson($row, $year, $intakeHistory, $intakeNotes, $trainings, $signedIn, $allHistories);
            $slots = $alphaSlots[$row->id] ?? null;
            if ($slots) {
                $person->alpha_slots = [];
                foreach ($slots as $slot) {
                    $person->alpha_slots[] = [
                        'slot_id' => $slot->slot_id,
                        'begins' => $slot->begins,
                        'description' => $slot->description,
                    ];
                }
            }
            $potentials[] = $person;
        }

        return $potentials;
    }

    /**
     * Retrieve the intake history - {vc,rrn,mentor, trainings} notes & rankings for the given ids
     *
     * @param mixed $pnvIds ids to retrieve the intake history for
     * @return array
     */

    public static function retrieveIntakeHistory($pnvIds): array
    {
        $year = current_year();

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
     * Build up the info on a PNV.
     * - Return personal info (callsign, fkas, name, email, etc.)
     * - Find all mentors
     * - Find all the intake notes and rankings
     * - Figure out if person was trained
     * - Determine if the person is eligible to be granted the Alpha status and/or position.
     *
     * @param Person $person person to build
     * @param int $year the year
     * @param $intakeHistory the intake history keyed by person.id
     * @param $intakeNotes
     * @param $trainings
     * @param $signedIn
     * @param null $allHistories
     * @return object
     */

    public static function buildPerson(Person $person, int $year, $intakeHistory, $intakeNotes, $trainings, $signedIn, $allHistories = null): object
    {
        $personId = $person->id;
        $photoApproved = $person->person_photo && $person->person_photo->status == PersonPhoto::APPROVED;

        if (!$allHistories) {
            $allHistories = PersonMentor::retrieveBulkMentorHistory([$personId]);
        }

        $potential = (object)[
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
            'mentor_history' => PersonMentor::retrieveMentorHistory($person->id, $allHistories),
            'photo_approved' => $photoApproved,
            'has_note_on_file' => $person->has_note_on_file,
            'city' => $person->city,
            'state' => $person->state,
            'country' => $person->country,
            'teeshirt_size_style' => $person->tshirt->title ?? 'Unknown',
            'tshirt_secondary_size' => $person->tshirt_secondary->title ?? 'Unknown',
            'longsleeveshirt_size_style' => $person->long_sleeve->title ?? 'Unknown',
            'trained' => false,
            'trainings' => $trainings[$personId] ?? [],
            'on_alpha_shift' => $signedIn->get($personId),
        ];

        if ($photoApproved) {
            $potential->photo_url = $person->person_photo->image_url ?? null;
        }

        $rrnRanks = [];
        $vcRanks = [];

        $teamHistory = $intakeHistory[$personId] ?? null;
        if (!empty($teamHistory)) {
            foreach ($teamHistory as $r) {
                if ($r->year == $year && $r->personnel_rank == Intake::FLAG) {
                    $potential->personnel_issue = true;
                }

                if ($r->rrn_rank > 0 && $r->rrn_rank != Intake::AVERAGE) {
                    $rrnRanks[] = ['year' => $r->year, 'rank' => $r->rrn_rank];
                }

                if ($r->vc_rank > 0 && $r->vc_rank != Intake::AVERAGE) {
                    $vcRanks[] = ['year' => $r->year, 'rank' => $r->vc_rank];
                }
            }
        }

        $potential->rrn_ranks = $rrnRanks;
        $potential->vc_ranks = $vcRanks;
        $potential->mentor_team = Intake::buildIntakeTeam('mentor', $teamHistory, $intakeNotes[$personId] ?? null, $haveFlag);

        $ignoreFlag = false;
        $potential->rrn_team = Intake::buildIntakeTeam('rrn', $teamHistory, $intakeNotes[$personId] ?? null, $ignoreFlag);
        $potential->vc_team = Intake::buildIntakeTeam('vc', $teamHistory, $intakeNotes[$personId] ?? null, $ignoreFlag);
        $potential->personnel_team = Intake::buildIntakeTeam('personnel', $teamHistory, $intakeNotes[$personId] ?? null, $ignoreFlag);

        if (!empty($teamHistory)) {
            foreach ($teamHistory as $history) {
                if ($history->mentor_rank >= Intake::BELOW_AVERAGE) {
                    $potential->have_mentor_flags = true;
                }

                if ($history->year == $year && $history->personnel_rank == Intake::FLAG) {
                    $potential->personnel_issue = true;
                }
            }
        }

        foreach ($potential->trainings as $training) {
            if ($training->slot_year == $year && $training->training_passed) {
                $potential->trained = true;
            }
        }

        if ($potential->status == Person::PROSPECTIVE && $potential->callsign_approved && $photoApproved) {
            $potential->alpha_status_eligible = true;
        } else if ($potential->status == Person::ALPHA) {
            $potential->alpha_status_eligible = true;
        }

        if ($potential->trained && $potential->callsign_approved && $photoApproved) {
            $potential->alpha_position_eligible = true;
        }

        return $potential;
    }

    /**
     * Retrieve all Alphas (anyone who has the Alpha position) in the current year.
     *
     * @return array
     */

    public static function retrieveAllAlphas(): array
    {
        $alphas = Person::where('status', Person::ALPHA)
            ->with('person_photo')
            ->orderBy('callsign')
            ->get();

        return self::buildAlphaInformation($alphas, current_year());
    }

    /**
     * Retrieve all the Alpha slots in a given year with sign-ups and their intake data.
     *
     * @param int $year
     * @return Collection
     */

    public static function retrieveAlphaScheduleForYear(int $year): Collection
    {
        // Find the Alpha slots
        $slots = Slot::where('begins_year', $year)
            ->where('position_id', Position::ALPHA)
            ->orderBy('begins')
            ->get();

        // Next, find the Alpha sign ups
        $rows = PersonSlot::whereIntegerInRaw('slot_id', $slots->pluck('id')->toArray())
            ->with(self::ALPHA_RELATIONSHIPS)
            ->get()
            ->sortBy('person.callsign', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $slotInfo = $slots->map(function ($slot) {
            return (object)[
                'id' => $slot->id,
                'begins' => (string)$slot->begins,
                'description' => $slot->description,
                'people' => []
            ];
        });


        $slotsById = $slotInfo->keyBy('id');
        $peopleIds = $rows->pluck('person_id');
        list ($intakeHistory, $intakeNotes, $trainings) = self::retrieveIntakeHistory($peopleIds);
        $allHistories = PersonMentor::retrieveBulkMentorHistory($peopleIds);
        $signedIn = Timesheet::retrieveSignedInPeople(Position::ALPHA);
        foreach ($rows as $row) {
            $slotsById[$row->slot_id]->people[] = self::buildPerson($row->person, $year, $intakeHistory, $intakeNotes, $trainings, $signedIn, $allHistories);
        }

        return $slotInfo;
    }

    /**
     * Retrieval all Alphas who had a mentor with a verdict (passed, bonked) in the current year.
     * Pending status is filtered out.
     *
     * @return Collection
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
            DB::raw("(SELECT person_mentor.status FROM person_mentor WHERE person_mentor.person_id=person.id AND mentor_year=$year GROUP BY person_mentor.status LIMIT 1) as mentor_status")
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
     *
     * @param int $year
     * @param array $alphas
     * @return array
     */

    public static function mentorAssignments(int $year, array $alphas): array
    {
        $ids = array_column($alphas, 'person_id');

        $people = Person::findOrFail($ids)->keyBy('id');
        $allMentors = PersonMentor::whereIntegerInRaw('person_id', $ids)
            ->where('mentor_year', $year)
            ->get()
            ->groupBy('person_id');

        $results = [];
        foreach ($alphas as $alpha) {
            $personId = $alpha['person_id'];
            $person = $people[$personId];
            $status = $alpha['status'];
            $desiredMentorIds = $alpha['mentor_ids'];

            if (isset($allMentors[$personId])) {
                $currentMentors = $allMentors[$personId]->pluck('mentor_id')->toArray();
            } else {
                $currentMentors = [];
            }

            $mentorCount = 0;
            foreach ($desiredMentorIds as $mentorId) {
                if (in_array($mentorId, $currentMentors)) {
                    $mentorCount++;
                }
            }

            $mentors = [];
            $mentorIds = [];
            if ($mentorCount == count($desiredMentorIds) && $mentorCount == count($currentMentors)) {
                // Simple status update
                $rows = PersonMentor::where('person_id', $personId)
                    ->where('mentor_year', $year)
                    ->get();

                foreach ($rows as $row) {
                    $row->status = $status;
                    $row->auditReason = 'mentor status change';
                    $row->save();
                }

                foreach ($allMentors[$personId] as $mentor) {
                    $mentorIds[] = $mentor->mentor_id;
                    $mentors[] = [
                        'person_mentor_id' => $mentor->id,
                        'mentor_id' => $mentor->mentor_id
                    ];
                }
            } else {
                // Rebuild the mentors
                PersonMentor::where('person_id', $personId)->where('mentor_year', $year)->delete();
                foreach ($alpha['mentor_ids'] as $mentorId) {
                    $mentor = PersonMentor::create([
                        'person_id' => $person->id,
                        'mentor_id' => $mentorId,
                        'status' => $status,
                        'mentor_year' => $year
                    ]);
                    $mentorIds[] = $mentor->mentor_id;
                    $mentors[] = [
                        'person_mentor_id' => $mentor->id,
                        'mentor_id' => $mentor->mentor_id
                    ];
                }
            }

            $callsigns = Person::select('id', 'callsign')
                ->whereIntegerInRaw('id', $mentorIds)
                ->get()
                ->keyBy('id');
            usort($mentors, fn($a, $b) => strcasecmp($callsigns[$a['mentor_id']]->callsign, $callsigns[$b['mentor_id']]->callsign));

            $results[] = [
                'person_id' => $person->id,
                'mentors' => $mentors,
                'status' => $status
            ];
        }

        return $results;
    }
}
