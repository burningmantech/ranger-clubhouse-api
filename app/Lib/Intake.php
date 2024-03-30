<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\PersonIntake;
use App\Models\PersonIntakeNote;
use App\Models\PersonMentor;
use App\Models\PersonOnlineCourse;
use App\Models\PersonPhoto;
use App\Models\PersonSlot;
use App\Models\PersonStatus;
use App\Models\Position;
use App\Models\Slot;
use App\Models\Timesheet;
use App\Models\TraineeStatus;
use App\Models\Training;
use Carbon\Carbon;

class Intake
{
    const int ABOVE_AVERAGE = 1;
    const int AVERAGE = 2;
    const int BELOW_AVERAGE = 3;
    const int FLAG = 4;

    /**
     * Retrieve all PNVs in a given year.
     *
     * @param int $year Year to find the PNVs
     * @return array
     */

    public static function retrieveAllForYear(int $year): array
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
     * Build up the counts for a given year and day.
     * @param int $year
     * @return array
     */

    public static function retrieveSpigotFlowForYear(int $year): array
    {
        $dates = [];
        // Find any PNVs in a given year
        $pnvStatus = PersonStatus::select('person_id', 'created_at')
            ->whereYear('created_at', $year)
            ->where('created_at', '>=', "$year-02-01")
            ->where('new_status', Person::PROSPECTIVE)
            ->orderBy('created_at')
            ->with('person:id,callsign')
            ->get()
            ->groupBy('person_id');

        if ($pnvStatus->isEmpty()) {
            // Too soon?
            return $dates;
        }

        foreach ($pnvStatus as $personId => $rows) {
            self::setSpigotDate($dates, 'imported', $rows[0]->created_at, $rows[0]->person);
        }

        $pnvIds = $pnvStatus->keys()->toArray();

        $droppedStatus = PersonStatus::select('person_id', 'created_at', 'new_status')
            ->whereYear('created_at', $year)
            ->where('created_at', '>=', "$year-02-01")
            ->where('new_status', Person::PAST_PROSPECTIVE)
            ->whereIntegerInRaw('person_id', $pnvIds)
            ->orderBy('created_at')
            ->with('person:id,callsign')
            ->get()
            ->groupBy('person_id');

        foreach ($droppedStatus as $personId => $rows) {
            self::setSpigotDate($dates, 'dropped', $rows[0]->created_at, $rows[0]->person);
        }


        // Retrieve photo approval
        $photos = PersonPhoto::select('person_id', 'uploaded_at', 'reviewed_at')
            ->where('person_photo.status', PersonPhoto::APPROVED)
            ->join('person', 'person.person_photo_id', 'person_photo.id')
            ->whereYear('person_photo.uploaded_at', $year)
            ->whereIntegerInRaw('person_photo.person_id', $pnvIds)
            ->with('person:id,callsign')
            ->get();

        foreach ($photos as $photo) {
            // photos prior to 2020 will not have a review_at date because the information could not be
            // gotten out of Lambase.
            self::setSpigotDate($dates, 'photo_approved', $photo->reviewed_at ?? $photo->uploaded_at, $photo->person);
        }

        $onlineCourse = PersonOnlineCourse::whereIntegerInRaw('person_id', $pnvIds)
            ->where('year', $year)
            ->where('position_id', Position::TRAINING)
            ->with('person:id,callsign')
            ->get();

        foreach ($onlineCourse as $poc) {
            self::setSpigotDate($dates, 'online_trained', $poc->completed_at, $poc->person);
        }

        // Grab the training slots
        $trainingSlotIds = Slot::select('id')
            ->where('position_id', Position::TRAINING)
            ->where('begins_year', $year)
            ->get()
            ->pluck('id')
            ->toArray();

        if (!empty($trainingSlotIds)) {
            $trainingSignups = PersonSlot::whereIntegerInRaw('person_id', $pnvIds)
                ->whereIntegerInRaw('slot_id', $trainingSlotIds)
                ->with('person:id,callsign')
                ->get()
                ->groupBy('person_id');

            foreach ($trainingSignups as $personId => $rows) {
                self::setSpigotDate($dates, 'training_signups', $rows[0]->created_at, $rows[0]->person);
            }

            // Grab the passing PNVs
            $trainingPass = TraineeStatus::select('trainee_status.*', 'slot.begins')
                ->join('slot', 'slot.id', 'trainee_status.slot_id')
                ->whereIntegerInRaw('person_id', $pnvIds)
                ->whereIntegerInRaw('slot_id', $trainingSlotIds)
                ->where('passed', true)
                ->with('person:id,callsign')
                ->get()
                ->groupBy('person_id');

            foreach ($trainingPass as $personId => $rows) {
                self::setSpigotDate($dates, 'training_passed', $rows[0]->begins, $rows[0]->person);
            }
        }

        // Grab the Alpha slots
        $alphaSlotIds = Slot::select('id')
            ->where('position_id', Position::ALPHA)
            ->where('begins_year', $year)
            ->get()
            ->pluck('id')
            ->toArray();

        if (!empty($alphaSlotIds)) {
            $alphaSignups = PersonSlot::whereIntegerInRaw('person_id', $pnvIds)
                ->whereIntegerInRaw('slot_id', $alphaSlotIds)
                ->with('person:id,callsign')
                ->get()
                ->groupBy('person_id');

            foreach ($alphaSignups as $personId => $rows) {
                self::setSpigotDate($dates, 'alpha_signups', $rows[0]->created_at, $rows[0]->person);
            }
        }

        $days = [];
        foreach ($dates as $day => $stats) {
            $stats['day'] = $day;
            $days[] = $stats;
        }

        foreach ($days as &$row) {
            foreach ($row as $key => &$people) {
                if ($key == 'day') {
                    continue;
                }
                usort($people, function ($a, $b) {
                    return strcasecmp($a['callsign'], $b['callsign']);
                });
            }
            unset($people);
        }

        usort($days, function ($a, $b) {
            return strcmp($a['day'], $b['day']);
        });


        return $days;
    }

    private static function setSpigotDate(&$dates, string $type, $date, $person): void
    {
        if (is_numeric($date)) {
            $date = new Carbon($date);
        } else if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        $day = $date->format('Y-m-d');
        if (!isset($dates[$day][$type])) {
            $dates[$day][$type] = [];
        }

        $dates[$day][$type][] = $person;
    }

    /**
     * Retrieve the intake history for a given set of Ids
     * (this assumes the ids were originally found in self::retrieveAllForYear)
     *
     * @param array $pnvIds PNV ids to find
     * @param int $year
     * @param bool $onlyFlagged
     * @param int|null $rrnId
     * @return array
     */

    public static function retrieveIdsForYear(array $pnvIds,
                                              int   $year,
                                              bool  $onlyFlagged = true,
                                              ?int  $rrnId = null): array
    {
        // Find the ALL intake records for the folks in question.
        $peopleIntake = PersonIntake::whereIntegerInRaw('person_id', $pnvIds)
            ->where('year', '<=', $year)
            ->orderBy('person_id')
            ->orderBy('year')
            ->get()
            ->groupBy('person_id');

        $personStatuses = PersonStatus::whereIntegerInRaw('person_id', $pnvIds)
            ->whereIn('new_status', [Person::AUDITOR, Person::PROSPECTIVE, Person::ALPHA])
            ->whereYear('created_at', '<=', $year)
            ->orderBy('person_id')
            ->orderBy('created_at')
            ->get()
            ->groupBy('person_id');

        $peopleIntakeNotes = PersonIntakeNote::retrieveHistoryForPersonIds($pnvIds, $year);
        $mentors = PersonMentor::retrieveAllMentorsForIds($pnvIds, $year);

        if (!$rrnId) {
            $trainings = Training::retrieveTrainingHistoryForIds($pnvIds, Position::TRAINING, $year);
        } else {
            $trainings = [];
        }

        $alphaEntries = Timesheet::retrieveAllForPositionIds($pnvIds, Position::ALPHA);

        // Find the people
        $people = Person::whereIntegerInRaw('id', $pnvIds)
            ->orderBy('callsign')
            ->with('person_fka')
            ->get();

        $pnvs = [];
        foreach ($people as $person) {
            $haveFlag = false;

            $personId = $person->id;
            $pnv = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'first_name' => $person->desired_first_name(),
                'last_name' => $person->last_name,
                'formerly_known_as' => $person->formerlyKnownAsArray(true),
                'known_rangers' => $person->knownRangersArray(),
                'known_pnvs' => $person->knownPnvsArray(),
                'personnel_issues' => [],
            ];

            $intakeYears = $peopleIntake[$personId] ?? null;
            $intakeNotes = $peopleIntakeNotes[$personId] ?? null;

            if ($rrnId) {
                $pnv['rrn_team'] = self::buildIntakeTeam('rrn', $intakeYears, $intakeNotes, $haveFlag);
            } else {
                foreach (['rrn', 'mentor', 'vc', 'personnel'] as $type) {
                    $pnv[$type . '_team'] = self::buildIntakeTeam($type, $intakeYears, $intakeNotes, $haveFlag);
                }
            }

            /*
             * Build up the PNV mentor history
             */

            $pnvHistory = [];
            if (isset($mentors[$personId])) {
                foreach ($mentors[$personId] as $mentorYear => $mentorship) {
                    $status = $mentorship['status'];
                    $pnvHistory[(int)$mentorYear] = (object)[
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

            if (!empty($intakeYears)) {
                foreach ($intakeYears as $r) {
                    if ($r->personnel_rank == Intake::FLAG) {
                        if ($r->year == $year) {
                            $pnv['personnel_issue'] = true;
                        }

                        if (!isset($pnvHistory[$r->year])) {
                            // An alpha shift without a mentor status.. hmmm..
                            $pnvHistory[$r->year] = (object)[
                                'training_status' => 'none',
                                'mentor_status' => 'none'
                            ];
                        }
                        $pnvHistory[$r->year]->personnel_issue = true;
                        $haveFlag = true;
                    }
                }
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
                    $historyYear = $history->created_at->year;
                    if (!isset($pnvHistory[$historyYear])) {
                        $pnvHistory[$historyYear] = (object)[
                            'training_status' => 'none',
                            'mentor_status' => 'none'
                        ];

                        if (self::wasAuditorInYear($statusHistory, $year)) {
                            $pnvHistory[$historyYear]->was_auditor = true;
                        }
                    }
                }
            }


            $pnv['pnv_history'] = $pnvHistory;

            $pnvs[] = $pnv;
        }

        return $pnvs;
    }

    /**
     * Figure out if a person was an auditor in a given year.
     *
     * @param $statuses
     * @param $year
     * @return bool
     */

    public static function wasAuditorInYear($statuses, $year): bool
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

    public static function buildIntakeTeam($type, $rankings, $notes, &$haveFlag): array
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

        $result = [];
        foreach ($teamYears as $teamYear => $info) {
            $info['year'] = $teamYear;
            $result[] = $info;
        }

        usort($result, fn($a, $b) => $a['year'] <=> $b['year']);
        return $result;
    }
}