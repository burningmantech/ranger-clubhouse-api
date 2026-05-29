<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\PersonIntake;
use App\Models\PersonIntakeNote;
use App\Models\PersonMentor;
use App\Models\PersonStatus;
use App\Models\Position;
use App\Models\Timesheet;
use App\Models\Training;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Intake
{
    const int ABOVE_AVERAGE = 1;
    const int AVERAGE = 2;
    const int BELOW_AVERAGE = 3;
    const int FLAG = 4;

    const array INTAKE_STATUSES = [
        Person::AUDITOR,
        Person::PROSPECTIVE,
        Person::ALPHA,
    ];

    /**
     * Retrieve all PNVs in a given year.
     */
    public static function retrieveAllForYear(int $year): array
    {
        $pnvIds = PersonStatus::select('person_id')
            ->whereYear('created_at', $year)
            ->whereIn('new_status', [Person::PROSPECTIVE, Person::ALPHA])
            ->groupBy('person_id')
            ->pluck('person_id')
            ->all();

        return self::retrieveIdsForYear($pnvIds, $year);
    }

    /**
     * Retrieve the intake history for a given set of Ids
     * (this assumes the ids were originally found in self::retrieveAllForYear)
     */
    public static function retrieveIdsForYear(
        array $pnvIds,
        int   $year,
        bool  $onlyFlagged = true,
        ?int  $rrnId = null,
    ): array {
        if (empty($pnvIds)) {
            return [];
        }

        $data = self::loadHistoryData($pnvIds, $year, $rrnId);
        $teamTypes = $rrnId ? ['rrn'] : ['rrn', 'mentor', 'vc', 'personnel'];

        $pnvs = [];
        foreach ($data['people'] as $person) {
            $entry = self::buildPnvEntry($person, $year, $teamTypes, $data);
            if ($onlyFlagged && !$entry['_has_flag']) {
                continue;
            }
            unset($entry['_has_flag']);
            $pnvs[] = $entry;
        }

        return $pnvs;
    }

    private static function loadHistoryData(array $pnvIds, int $year, ?int $rrnId): array
    {
        $intakes = PersonIntake::whereIntegerInRaw('person_id', $pnvIds)
            ->where('year', '<=', $year)
            ->orderBy('person_id')
            ->orderBy('year')
            ->get()
            ->groupBy('person_id');

        $statuses = PersonStatus::whereIntegerInRaw('person_id', $pnvIds)
            ->whereIn('new_status', self::INTAKE_STATUSES)
            ->whereYear('created_at', '<=', $year)
            ->orderBy('person_id')
            ->orderBy('created_at')
            ->get()
            ->groupBy('person_id');

        $alphaSignups = DB::table('person_slot')
            ->select('person_id', 'slot.begins_year')
            ->join('slot', 'person_slot.slot_id', '=', 'slot.id')
            ->where('slot.position_id', Position::ALPHA)
            ->whereIn('person_id', $pnvIds)
            ->get()
            ->groupBy('begins_year')
            ->map(fn($signupYear) => $signupYear->keyBy('person_id'));

        return [
            'intakes' => $intakes,
            'statuses' => $statuses,
            'notes' => PersonIntakeNote::retrieveHistoryForPersonIds($pnvIds, $year),
            'mentors' => PersonMentor::retrieveAllMentorsForIds($pnvIds, $year),
            'trainings' => $rrnId ? [] : Training::retrieveTrainingHistoryForIds($pnvIds, Position::TRAINING, $year),
            'alphaEntries' => Timesheet::retrieveAllForPositionIds($pnvIds, Position::ALPHA),
            'alphaSignups' => $alphaSignups,
            'people' => Person::whereIntegerInRaw('id', $pnvIds)
                ->orderBy('callsign')
                ->with('person_fka')
                ->get(),
        ];
    }

    private static function buildPnvEntry(Person $person, int $year, array $teamTypes, array $data): array
    {
        $personId = $person->id;
        $hasFlag = false;

        $pnv = [
            'id' => $personId,
            'callsign' => $person->callsign,
            'status' => $person->status,
            'first_name' => $person->desired_first_name(),
            'last_name' => $person->last_name,
            'formerly_known_as' => $person->formerlyKnownAsArray(true),
            'known_rangers' => $person->knownRangersArray(),
            'known_pnvs' => $person->knownPnvsArray(),
            'personnel_issues' => [],
        ];

        $intakeYears = $data['intakes'][$personId] ?? null;
        $intakeNotes = $data['notes'][$personId] ?? null;
        $personStatuses = $data['statuses'][$personId] ?? null;
        $personTrainings = $data['trainings'][$personId] ?? null;
        $personMentors = $data['mentors'][$personId] ?? null;
        $personAlphaEntries = $data['alphaEntries'][$personId] ?? null;

        foreach ($teamTypes as $type) {
            $pnv[$type . '_team'] = self::buildIntakeTeam($type, $intakeYears, $intakeNotes, $hasFlag);
        }

        $pnvHistory = [];
        self::applyMentorHistory($pnvHistory, $personMentors, $personId, $year, $data['alphaSignups'], $hasFlag);
        $pnv['trainings'] = self::applyTrainingHistory($pnvHistory, $personTrainings, $personStatuses, $data['alphaSignups'], $personId, $hasFlag);
        self::applyPersonnelFlags($pnvHistory, $intakeYears, $year, $pnv, $hasFlag);
        self::applyAlphaShifts($pnvHistory, $personAlphaEntries);
        self::applyStatusHistory($pnvHistory, $personStatuses);

        $pnv['pnv_history'] = $pnvHistory;
        $pnv['_has_flag'] = $hasFlag;
        return $pnv;
    }

    private static function ensureHistoryYear(array &$pnvHistory, int $year): object
    {
        if (!isset($pnvHistory[$year])) {
            $pnvHistory[$year] = (object)[
                'training_status' => 'none',
                'mentor_status' => 'none',
            ];
        }
        return $pnvHistory[$year];
    }

    private static function applyMentorHistory(
        array &$pnvHistory,
        ?iterable $mentors,
        int $personId,
        int $year,
        $alphaSignups,
        bool &$hasFlag,
    ): void {
        if (!$mentors) {
            return;
        }
        foreach ($mentors as $mentorYear => $mentorship) {
            $mentorYear = (int)$mentorYear;
            $status = $mentorship['status'] ?? 'none';
            $pnvHistory[$mentorYear] = (object)[
                'mentor_status' => $status,
                'mentors' => $mentorship['mentors'],
                'training_status' => 'none',
                'have_alpha_shift' => $mentorYear == $year && $alphaSignups->has($personId),
            ];

            if ($status === PersonMentor::BONK || $status === PersonMentor::SELF_BONK) {
                $hasFlag = true;
            }
        }
    }

    private static function applyTrainingHistory(
        array &$pnvHistory,
        ?iterable $trainings,
        ?iterable $personStatuses,
        $alphaSignups,
        int $personId,
        bool &$hasFlag,
    ): array {
        if (!$trainings) {
            return [];
        }

        foreach ($trainings as $training) {
            $trainYear = Carbon::parse($training->slot_begins)->year;
            $isNew = !isset($pnvHistory[$trainYear]);
            $history = self::ensureHistoryYear($pnvHistory, $trainYear);

            if ($isNew && $personStatuses && self::wasAuditorInYear($personStatuses, $trainYear)) {
                $history->was_auditor = true;
            }

            if ($training->training_rank >= self::BELOW_AVERAGE) {
                $hasFlag = true;
            }

            if ($history->training_status !== 'pass') {
                if ($training->slot_has_ended) {
                    $history->training_status = $training->training_passed ? 'pass' : 'no pass';
                } else {
                    $history->training_status = 'pending';
                }
            }

            $history->have_alpha_shift = ($history->have_alpha_shift ?? false)
                || $alphaSignups->get($trainYear)?->has($personId);
        }

        return is_array($trainings) ? $trainings : $trainings->all();
    }

    private static function applyPersonnelFlags(
        array &$pnvHistory,
        ?iterable $intakeYears,
        int $year,
        array &$pnv,
        bool &$hasFlag,
    ): void {
        if (empty($intakeYears)) {
            return;
        }
        foreach ($intakeYears as $r) {
            if ($r->personnel_rank !== self::FLAG) {
                continue;
            }
            if ($r->year == $year) {
                $pnv['personnel_issue'] = true;
            }
            self::ensureHistoryYear($pnvHistory, $r->year)->personnel_issue = true;
            $hasFlag = true;
        }
    }

    private static function applyAlphaShifts(array &$pnvHistory, ?iterable $alphaEntries): void
    {
        if (!$alphaEntries) {
            return;
        }
        foreach ($alphaEntries as $alphaYear => $entries) {
            self::ensureHistoryYear($pnvHistory, (int)$alphaYear)->have_alpha_shift = true;
        }
    }

    private static function applyStatusHistory(array &$pnvHistory, ?iterable $statusHistory): void
    {
        if (!$statusHistory) {
            return;
        }
        foreach ($statusHistory as $history) {
            $historyYear = $history->created_at->year;
            $isNew = !isset($pnvHistory[$historyYear]);
            $entry = self::ensureHistoryYear($pnvHistory, $historyYear);
            if ($isNew && self::wasAuditorInYear($statusHistory, $historyYear)) {
                $entry->was_auditor = true;
            }
        }
    }

    /**
     * Determine whether a person was an auditor in (or as of) a given year.
     *
     * Returns true if the most recent status entry at or before $year is AUDITOR.
     */
    public static function wasAuditorInYear($statuses, $year): bool
    {
        $latest = null;
        foreach ($statuses as $row) {
            if ($row->created_at->year <= $year) {
                $latest = $row;
            } else {
                break;
            }
        }
        return $latest !== null && $latest->new_status === Person::AUDITOR;
    }

    public static function buildIntakeTeam($type, $rankings, $notes, &$haveFlag): array
    {
        $teamYears = [];
        $rankName = $type . '_rank';

        if (!empty($rankings)) {
            foreach ($rankings as $r) {
                $rank = $r->{$rankName};
                if (!$rank) {
                    continue;
                }

                $teamYears[$r->year] = ['rank' => $rank];

                if ($rank >= self::BELOW_AVERAGE) {
                    $haveFlag = true;
                }
            }
        }

        if (!empty($notes)) {
            foreach ($notes as $note) {
                if ($note->type != $type) {
                    continue;
                }
                $teamYears[$note->year]['notes'][] = $note;
                if (!$note->is_log) {
                    // A year may have only a rank and no text notes (only audit log notes)
                    $teamYears[$note->year]['have_notes'] = true;
                }
            }
        }

        ksort($teamYears);
        $result = [];
        foreach ($teamYears as $teamYear => $info) {
            $info['year'] = $teamYear;
            $result[] = $info;
        }

        return $result;
    }
}
