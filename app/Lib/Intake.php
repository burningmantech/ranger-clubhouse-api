<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\PersonStatus;
use App\Models\PersonMentor;
use App\Models\PersonIntake;
use App\Models\PersonIntakeNote;
use App\Models\Position;
use App\Models\Training;
use App\Models\Timesheet;

use Carbon\Carbon;

class Intake
{
    /**
     * Retrieve all PNVs in a given year.
     *
     * @param int $year Year to find the PNVs
     * @return array
     */

    const ABOVE_AVERAGE = 1;
    const AVERAGE = 2;
    const BELOW_AVERAGE = 3;
    const FLAG = 4;

    public static function retrieveAllForYear(int $year)
    {
        // Find any PNVs in a given year
        $pnvIds = PersonStatus::select('person_id')
            ->whereYear('created_at', $year)
            ->whereIn('new_status', [Person::PROSPECTIVE, Person::ALPHA])
            ->groupBy('person_id')
            ->get()
            ->pluck('person_id')->toArray();

        return self::retrieveIdsForYear($pnvIds, $year);
    }

    /**
     * Retrieve the intake history for a given set of Ids
     * (this assumes the ids were originally found in self::retrieveAllForYear)
     *
     * @param array $pnvIds PNV ids to find
     * @param int $year
     * @return array
     */

    public static function retrieveIdsForYear(array $pnvIds, int $year, bool $onlyFlagged = true)
    {
        // Find the ALL intake records for the folks in question.
        $peopleIntake = PersonIntake::whereIn('person_id', $pnvIds)
            ->where('year', '<=', $year)
            ->orderBy('person_id')
            ->orderBy('year')
            ->get()
            ->groupBy('person_id');

        $personStatuses = PersonStatus::whereIn('person_id', $pnvIds)
            ->whereIn('new_status', [Person::AUDITOR, Person::PROSPECTIVE, Person::ALPHA])
            ->whereYear('created_at', '<=', $year)
            ->orderBy('person_id')
            ->orderBy('created_at')
            ->get()
            ->groupBy('person_id');

        $peopleIntakeNotes = PersonIntakeNote::retrieveHistoryForPersonIds($pnvIds, $year);

        $trainings = Training::retrieveTrainingHistoryForIds($pnvIds, Position::TRAINING, $year);
        $mentors = PersonMentor::retrieveAllMentorsForIds($pnvIds, $year);
        $alphaEntries = Timesheet::retrieveAllForPositionIds($pnvIds, Position::ALPHA, $year);

        // Find the people
        $people = Person::whereIn('id', $pnvIds)->orderBy('callsign')->get();

        $pnvs = [];
        foreach ($people as $person) {
            $haveFlag = false;

            $personId = $person->id;
            $pnv = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'formerly_known_as' => $person->formerlyKnownAsArray(true),
                'known_rangers' => $person->knownRangersArray(),
                'known_pnvs' => $person->knownPnvsArray(),
                'black_flag_years' => [],
            ];

            $intakeYears = $peopleIntake[$personId] ?? null;
            $intakeNotes = $peopleIntakeNotes[$personId] ?? null;
            foreach (['rrn', 'mentor', 'vc'] as $type) {
                $pnv[$type . '_team'] = self::buildIntakeTeam($type, $intakeYears, $intakeNotes, $haveFlag);
            }

            if (!empty($intakeYears)) {
                foreach ($intakeYears as $r) {
                    if ($r->black_flag) {
                        $pnv['black_flag_years'][] = $r->year;
                        if ($r->year == $year) {
                            $pnv['black_flag'] = true;
                        }
                    }
                }
            }

            /*
             * Build up the PNV mentor history
             */

            $pnvHistory = [];
            if (isset($mentors[$personId])) {
                foreach ($mentors[$personId] as $year => $mentorship) {
                    $status = $mentorship['status'];
                    $pnvHistory[(int) $year] = (object)[
                        'mentor_status' => $mentorship['status'] ?? 'none',
                        'mentors' => $mentorship['mentors'],
                        'training_status' => 'none',
                        'have_alpha_shift' => false
                    ];

                    if ($status == PersonMentor::BONK || $status == PersonMentor::SELF_BONK) {
                        $haveFlag = true;
                    }
                }
            }

            if (isset($trainings[$personId])) {
                $pnv['trainings'] = $trainings[$personId];

                /*
                 * Dig through the training records and find out years passed
                 */
                foreach ($trainings[$personId] as $training) {
                    $trainYear = Carbon::parse($training->slot_begins)->year;

                    if (!isset($pnvHistory[$trainYear])) {
                        $pnvHistory[$trainYear] = (object)[
                            'training_status' => 'none',
                            'mentor_status' => 'none',
                            'have_alpha_shift' => false
                        ];
                        // See if the person was an auditor that year
                        if (isset($personStatuses[$personId]) && self::wasAuditorInYear($personStatuses[$personId], $trainYear)) {
                            $pnvHistory[$trainYear]->was_auditor = true;
                        }
                    }

                    $history = $pnvHistory[$trainYear];

                    if ($training->training_rank >= self::BELOW_AVERAGE) {
                        $haveFlag = true;
                    }

                    if ($history->training_status != 'pass') {
                        if ($training->slot_has_ended) {
                            $history->training_status = $training->training_passed ? 'pass' : 'no pass';
                        } else {
                            $history->training_status = 'pending';
                        }
                    }
                }
            } else {
                $pnv['trainings'] = [];
            }

            // Bail out if only flagged PNVs are being searched for
            if ($onlyFlagged && !$haveFlag) {
                continue;
            }

            /*
              * Find any Alpha shifts
              */

            if (isset($alphaEntries[$personId])) {
                foreach ($alphaEntries[$personId] as $alphaYear => $entries) {
                    if (!isset($pnvHistory[$alphaYear])) {
                        // An alpha shift without a mentor status.. hmmm..
                        $pnvHistory[$alphaYear] = (object)[
                            'training_status' => 'none',
                            'mentor_status' => 'none'
                        ];
                    }
                    $pnvHistory[$alphaYear]->have_alpha_shift = true;
                }
            }

            /*
             * Fill in any years based on status history
             */

            if (isset($personStatuses[$personId])) {
                $statusHistory = $personStatuses[$personId];
                foreach ($statusHistory as $history) {
                    $year = $history->created_at->year;
                    if (!isset($pnvHistory[$year])) {
                        $pnvHistory[$year] = (object)[
                            'training_status' => 'none',
                            'mentor_status' => 'none'
                        ];

                        if (self::wasAuditorInYear($statusHistory, $year)) {
                            $pnvHistory[$year]->was_auditor = true;
                        }
                    }
                }
            }

            $pnv['pnv_history'] = $pnvHistory;

            $pnvs[] = $pnv;
        }

        return $pnvs;
    }

    public static function wasAuditorInYear($statuses, $year)
    {
        $possible = null;
        foreach ($statuses as $row) {
            $status = $row->new_status;
            $statusYear = $row->created_at->year;
            if ($statusYear < $year) {
                if ($status == Person::AUDITOR) {
                    $possible = $row;
                }
            } else if ($statusYear == $year) {
                return ($status == Person::AUDITOR);
            } else if ($statusYear > $year) {
                return ($possible && $possible->new_status == Person::AUDITOR);
            }
        }

        return ($possible && $possible->new_status == Person::AUDITOR);
    }

    public static function buildIntakeTeam($type, $rankings, $notes, &$haveFlag)
    {
        $teamYears = [];
        $rankName = $type . '_rank';

        if (!empty($rankings)) {
            foreach ($rankings as $r) {
                $rank = $r->{$rankName};
                // Skip non-ranking
                if (!$rank) {
                    continue;
                }

                $teamYears[$r->year] = [ 'rank' => $rank ];

                if ($rank >= self::BELOW_AVERAGE) {
                    $haveFlag = true;
                }
            }
        }

        if (!empty($notes)) {
            $haveNote = false;
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

        $result = [];
        foreach ($teamYears as $year => $info) {
            $info['year'] = $year;
            $result[] = $info;
        }

        usort($result, function ($a, $b) { return $a['year'] <=> $b['year']; });
        return $result;
    }
}