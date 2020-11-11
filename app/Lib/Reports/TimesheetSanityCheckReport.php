<?php

namespace App\Lib\Reports;

use App\Models\Position;
use App\Models\Timesheet;

use Illuminate\Support\Facades\DB;

class TimesheetSanityCheckReport
{
    /*
    * Run through the timesheets in a given year, and sniff for problematic entries.
    */

    public static function execute(int $year)
    {
        $withBase = ['position:id,title', 'person:id,callsign'];

        $rows = Timesheet::whereYear('on_duty', $year)
            ->whereNull('off_duty')
            ->with($withBase)
            ->get()
            ->sortBy('person.callsign')
            ->values();

        $onDutyEntries = $rows->map(function ($row) {
            return self::buildEntry($row);
        })->values();

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

        $endBeforeStartEntries = $rows->map(function ($row) {
            return self::buildEntry($row);
        });

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

        usort($overlappingPeople, function ($a, $b) {
            return strcasecmp($a['person']['callsign'], $b['person']['callsign']);
        });

        $minHour = 24;
        foreach (Position::PROBLEM_HOURS as $positionId => $hours) {
            if ($hours < $minHour) {
                $minHour = $hours;
            }
        }

        $now = now();
        $rows = Timesheet:: select('timesheet.*', DB::raw("TIMESTAMPDIFF(SECOND, on_duty, IFNULL(off_duty,'$now')) as duration"))
            ->whereYear('on_duty', $year)
            ->whereRaw("TIMESTAMPDIFF(HOUR, on_duty, IFNULL(off_duty,'$now')) >= $minHour")
            ->with($withBase)
            ->orderBy('duration', 'desc')
            ->get();

        /*
         * Look for entries that may be too long. (i.e. a person forgot to signout in a timely manner.)
         */

        $tooLongEntries = $rows->filter(function ($row) {
            if (!isset(Position::PROBLEM_HOURS[$row->position_id])) {
                return true;
            }

            return Position::PROBLEM_HOURS[$row->position_id] < ($row->duration / 3600.0);
        })->values()->map(function ($row) {
            return self::buildEntry($row);
        })->toArray();

        return [
            'on_duty' => $onDutyEntries,
            'end_before_start' => $endBeforeStartEntries,
            'overlapping' => $overlappingPeople,
            'too_long' => $tooLongEntries
        ];
    }

    public static function buildEntry($row)
    {
        return [
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
    }
}