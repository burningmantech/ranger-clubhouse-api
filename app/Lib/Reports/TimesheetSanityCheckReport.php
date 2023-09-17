<?php

namespace App\Lib\Reports;

use App\Models\Position;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;

class TimesheetSanityCheckReport
{
    /*
    * Run through the timesheet entries in a given year, and sniff for problems.
    */

    public static function execute(int $year): array
    {
        $withBase = ['position:id,title', 'person:id,callsign', 'slot:id,description,begins,ends,timezone,duration'];

        $rows = Timesheet::whereYear('on_duty', $year)
            ->whereNull('off_duty')
            ->with($withBase)
            ->get()
            ->sortBy('person.callsign')
            ->values();

        $onDutyEntries = $rows->map(fn($row) => self::buildEntry($row))->values();

        $rows = Timesheet::whereYear('on_duty', $year)
            ->whereRaw('on_duty > off_duty')
            ->whereNotNull('off_duty')
            ->with($withBase)
            ->get()
            ->sortBy('person.callsign')
            ->values();

        /*
         * Do any entries have the end time before the start time?
         * (should never happen..famous last words)
         */

        $endBeforeStartEntries = $rows->map(fn($row) => self::buildEntry($row));

        /*
         * Look for overlapping entries
         */

        $people = Timesheet::whereYear('on_duty', $year)
            ->whereNotNull('off_duty')
            ->with($withBase)
            ->orderBy('person_id')
            ->orderBy('on_duty')
            ->get()
            ->groupBy('person_id');

        $overlappingPeople = [];
        foreach ($people as $personId => $entries) {
            $overlapping = [];

            $prevEntry = null;
            foreach ($entries as $entry) {
                if ($prevEntry) {
                    if ($entry->on_duty->timestamp < ($prevEntry->on_duty->timestamp + $prevEntry->duration)) {
                        $overlapping[] = [
                            [
                                'timesheet_id' => $prevEntry->id,
                                'position' => [
                                    'id' => $prevEntry->position_id,
                                    'title' => $prevEntry->position ? $prevEntry->position->title : 'Position #' . $prevEntry->position_id,
                                ],
                                'on_duty' => (string)$prevEntry->on_duty,
                                'off_duty' => (string)$prevEntry->off_duty,
                                'duration' => $prevEntry->duration,
                            ],
                            [
                                'timesheet_id' => $entry->id,
                                'position' => [
                                    'id' => $entry->position_id,
                                    'title' => $entry->position ? $entry->position->title : 'Position #' . $entry->position_id,
                                ],
                                'on_duty' => (string)$entry->on_duty,
                                'off_duty' => (string)$entry->off_duty,
                                'duration' => $entry->duration,
                            ]
                        ];
                    }
                }
                $prevEntry = $entry;
            }

            if (!empty($overlapping)) {
                $first = $entries[0];
                $overlappingPeople[] = [
                    'person' => [
                        'id' => $first->person_id,
                        'callsign' => $first->person ? $first->person->callsign : 'Person #' . $first->person_id
                    ],
                    'entries' => $overlapping
                ];
            }
        }

        usort($overlappingPeople, fn($a, $b) => strcasecmp($a['person']['callsign'], $b['person']['callsign']));

        $minHour = 24;
        foreach (Position::PROBLEM_HOURS as $positionId => $hours) {
            if ($hours < $minHour) {
                $minHour = $hours;
            }
        }

        $now = now();
        $rows = Timesheet::whereYear('on_duty', $year)
            ->whereNotNull('off_duty')
            ->whereRaw("TIMESTAMPDIFF(HOUR, on_duty, IFNULL(off_duty,'$now')) >= $minHour")
            ->with($withBase)
            ->get();

        /*
         * Look for entries that may be too long. (i.e. a person forgot to signout in a timely manner.)
         */

        $tooLongEntries = $rows->filter(function ($row) use ($minHour) {
            $hours = Position::PROBLEM_HOURS[$row->position_id] ?? $minHour;
            $isProblem = ($hours * 1.5) < ($row->duration / 3600.0);
            if ($isProblem) {
                $row->max_hours = $hours;
            }
            return $isProblem;
        })->values()
            ->map(fn($row) => self::buildEntry($row))
            ->sortByDesc('duration')
            ->values()
            ->toArray();

        $rows = Timesheet::select('timesheet.*')
            ->join('slot', 'slot.id', 'timesheet.slot_id')
            ->whereYear('on_duty', $year)
            ->whereNotNull('timesheet.slot_id')
            ->whereRaw("TIMESTAMPDIFF(SECOND, on_duty, IFNULL(off_duty,'$now')) >= TIMESTAMPDIFF(SECOND, slot.begins, slot.ends)*1.5")
            ->with($withBase)
            ->get();

        $tooLongForShift = $rows->sortByDesc('duration')->values()->map(fn($row) => self::buildEntry($row));

        $rows = Timesheet:: select('timesheet.*', DB::raw("TIMESTAMPDIFF(SECOND, on_duty, IFNULL(off_duty,'$now')) as duration"))
            ->whereYear('on_duty', $year)
            ->whereNotNull('off_duty')
            ->whereRaw("TIMESTAMPDIFF(MINUTE, on_duty, off_duty) <= 15")
            ->with($withBase)
            ->orderBy('duration')
            ->get();

        $tooShort = $rows->map(fn($row) => self::buildEntry($row));

        $alphaIds = DB::table('timesheet')
            ->select('person_id')
            ->whereYear('on_duty', $year)
            ->where('position_id', Position::ALPHA)
            ->groupBy('person_id')
            ->havingRaw("COUNT(person_id) > 1")
            ->get()
            ->pluck('person_id');

        $duplicateAlphas = [];
        if (!empty($alphaIds)) {
            $rows = Timesheet::whereYear('on_duty', $year)
                ->whereIntegerInRaw('person_id', $alphaIds)
                ->where('position_id', Position::ALPHA)
                ->with($withBase)
                ->orderBy('on_duty')
                ->get()
                ->groupBy('person_id');

            foreach ($rows as $personId => $entries) {
                $person = $entries[0]->person;
                $duplicateAlphas[] = [
                    'person' => [
                        'id' => $person->id,
                        'callsign' => $person->callsign,
                    ],
                    'entries' => $entries->map(fn($row) => [
                        'id' => $row->id,
                        'on_duty' => (string)$row->on_duty,
                        'off_duty' => (string)$row->off_duty,
                        'duration' => $row->duration,
                        'credits' => $row->credits,
                    ])->values()
                ];
            }
            usort($duplicateAlphas, fn($a, $b) => strcasecmp($a['person']['callsign'], $b['person']['callsign']));
        }

        return [
            'on_duty' => $onDutyEntries,
            'end_before_start' => $endBeforeStartEntries,
            'overlapping' => $overlappingPeople,
            'too_long' => $tooLongEntries,
            'too_long_for_shift' => $tooLongForShift,
            'too_short' => $tooShort,
            'duplicate_alphas' => $duplicateAlphas,
        ];
    }

    public static function buildEntry($row): array
    {
        $result = [
            'id' => $row->id,
            'person' => [
                'id' => $row->person_id,
                'callsign' => $row->person ? $row->person->callsign : 'Person #' . $row->person_id,
            ],
            'callsign' => $row->person ? $row->person->callsign : 'Person #' . $row->person_id,
            'on_duty' => (string)$row->on_duty,
            'off_duty' => (string)$row->off_duty,
            'duration' => $row->duration,
            'credits' => $row->credits,
            'position' => [
                'id' => $row->position_id,
                'title' => $row->position ? $row->position->title : 'Position #' . $row->position_id,
            ]
        ];

        $slot = $row->slot ?? null;
        if (isset($row->max_hours)) {
            $result['max_hours'] = $row->max_hours;
        }
        if ($slot) {
            $result['slot'] = [
                'id' => $slot->id,
                'begins' => (string)$slot->begins,
                'ends' => (string)$slot->ends,
                'duration' => $slot->duration,
                'description' => $slot->description,
            ];
        }

        return $result;
    }
}